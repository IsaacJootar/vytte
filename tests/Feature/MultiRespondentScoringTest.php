<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentRespondentToken;
use App\Models\AssessmentShareLink;
use App\Models\DepartmentFrameworkVersion;
use App\Models\HealthDomain;
use App\Models\Project;
use App\Models\PublicResponseSession;
use App\Models\Response;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AssessmentCreationService;
use App\Services\CataloguePublishingService;
use App\Services\MultiRespondentAggregationService;
use App\Services\RespondentSubmissionService;
use Database\Seeders\PlanFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MultiRespondentScoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanFeatureSeeder::class);
    }

    public function test_catalogue_release_must_freeze_a_supported_multi_respondent_contract_before_publication(): void
    {
        [, , $release] = $this->context(minimum: null, publish: false);

        try {
            app(CataloguePublishingService::class)->publish($release);
            $this->fail('Publishing should require a completed multi-respondent contract.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('collection_config', $exception->errors());
        }

        $release->update([
            'collection_config' => [
                'allows_multi_respondent' => true,
                'minimum_completed_respondents' => 2,
                'aggregation_method' => 'MEDIAN',
                'scoring_profile_version' => 'vytte-4.0-numeric-bands',
            ],
        ]);

        try {
            app(CataloguePublishingService::class)->publish($release);
            $this->fail('An unsupported aggregation method should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('collection_config', $exception->errors());
        }
    }

    public function test_threshold_does_not_auto_finalize_and_manual_finalization_is_blocked_below_minimum(): void
    {
        [$owner, $assessment] = $this->context(minimum: 2);
        $this->submitRespondent($assessment, 'high');

        $this->assertSame(Assessment::STATUS_IN_PROGRESS, $assessment->fresh()->status);
        $this->assertDatabaseMissing('assessment_aggregation_results', ['assessment_id' => $assessment->assessment_id]);
        $this->actingAs($owner)
            ->post(route('assessments.submit', $assessment))
            ->assertRedirect(route('assessments.respondent-collection', $assessment));
        $this->assertSame(Assessment::STATUS_IN_PROGRESS, $assessment->fresh()->status);

        $this->expectException(ValidationException::class);
        app(MultiRespondentAggregationService::class)->finalize($assessment, $owner->user_id);
    }

    public function test_arithmetic_mean_is_provisional_until_authorized_manual_finalization(): void
    {
        [$owner, $assessment] = $this->context(minimum: 2);
        $low = $this->submitRespondent($assessment, 'low');
        $high = $this->submitRespondent($assessment, 'high');
        $expected = round(((float) $low->overall_score + (float) $high->overall_score) / 2, 2);

        $preview = app(MultiRespondentAggregationService::class)->preview($assessment);
        $this->assertSame(2, $preview['eligible_respondent_count']);
        $this->assertSame($expected, $preview['result']['overall_score']);
        $this->assertSame(Assessment::STATUS_IN_PROGRESS, $assessment->fresh()->status);

        $final = app(MultiRespondentAggregationService::class)->finalize($assessment, $owner->user_id);

        $this->assertSame($expected, (float) $final->overall_score);
        $this->assertSame(Assessment::STATUS_COMPLETE, $assessment->fresh()->status);
        $this->assertSame($expected, (float) $assessment->fresh()->score->overall_score);
        $this->assertNotNull($assessment->fresh()->reportSnapshot);
    }

    public function test_incomplete_test_revoked_and_unconfirmed_sessions_are_excluded_with_reasons(): void
    {
        [$owner, $assessment] = $this->context(minimum: 1, eligibilityRules: [['field' => 'adult', 'operator' => 'equals', 'value' => true]]);
        $incomplete = $this->newSession($assessment);
        $revoked = $this->submitRespondent($assessment, 'high');
        $test = $this->submitRespondent($assessment, 'high');
        $pending = $this->submitRespondent($assessment, 'high');

        $this->actingAs($owner)->patch(
            route('assessments.respondent-sessions.classify', [$assessment, $revoked->responseSession]),
            ['classification' => 'ELIGIBLE'],
        )->assertRedirect();
        $revoked->responseSession->accessToken->update(['revoked_at' => now()]);
        $this->actingAs($owner)->patch(
            route('assessments.respondent-sessions.classify', [$assessment, $test->responseSession]),
            ['classification' => 'TEST', 'reason' => 'Internal dry run'],
        )->assertRedirect();

        $preview = app(MultiRespondentAggregationService::class)->preview($assessment);
        $reasons = collect($preview['excluded_sessions'])->pluck('reason', 'session_id');

        $this->assertSame(0, $preview['eligible_respondent_count']);
        $this->assertSame('INCOMPLETE_SESSION', $reasons[$incomplete->session_id]);
        $this->assertSame('ACCESS_TOKEN_REVOKED', $reasons[$revoked->public_response_session_id]);
        $this->assertSame('TEST_SESSION', $reasons[$test->public_response_session_id]);
        $this->assertSame('ELIGIBILITY_NOT_CONFIRMED', $reasons[$pending->public_response_session_id]);
    }

    public function test_missing_required_answers_are_rejected_and_never_treated_as_zero(): void
    {
        [, $assessment] = $this->context(minimum: 1);
        $session = $this->newSession($assessment);
        $this->writeResponses($assessment, $session, 'low', skipLast: true);

        try {
            app(RespondentSubmissionService::class)->submit($session);
            $this->fail('An incomplete respondent should not be submitted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('responses', $exception->errors());
        }

        $this->assertNull($session->fresh()->submitted_at);
        $this->assertDatabaseMissing('respondent_score_results', [
            'public_response_session_id' => $session->session_id,
        ]);
    }

    public function test_members_cannot_finalize_and_other_tenants_cannot_view_collection(): void
    {
        [, $assessment, , $workspace] = $this->context(minimum: 1);
        $this->submitRespondent($assessment, 'high');
        $member = User::factory()->create(['active_workspace_id' => $workspace->workspace_id]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $member->user_id,
            'role' => 'MEMBER',
        ]);

        $this->actingAs($member)
            ->post(route('assessments.respondent-collection.finalize', $assessment))
            ->assertForbidden();
        $this->assertSame(Assessment::STATUS_IN_PROGRESS, $assessment->fresh()->status);

        $otherUser = User::factory()->create();
        $otherWorkspace = Workspace::factory()->create(['plan' => 'PRO']);
        WorkspaceMember::create([
            'workspace_id' => $otherWorkspace->workspace_id,
            'user_id' => $otherUser->user_id,
            'role' => 'OWNER',
        ]);
        $otherUser->update(['active_workspace_id' => $otherWorkspace->workspace_id]);
        app()->instance('current.workspace', $otherWorkspace);

        $this->actingAs($otherUser)
            ->get(route('assessments.respondent-collection', $assessment))
            ->assertNotFound();
    }

    public function test_final_results_and_response_snapshots_are_immutable(): void
    {
        [$owner, $assessment] = $this->context(minimum: 1);
        $score = $this->submitRespondent($assessment, 'high');
        $aggregation = app(MultiRespondentAggregationService::class)->finalize($assessment, $owner->user_id);
        $report = $assessment->fresh()->reportSnapshot;

        foreach ([
            fn () => $score->update(['overall_score' => 0]),
            fn () => $aggregation->update(['overall_score' => 0]),
            fn () => $report->update(['schema_version' => 'changed']),
            fn () => $score->responseSession->update(['response_snapshot_hash' => str_repeat('0', 64)]),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('Final respondent and report artifacts must be immutable.');
            } catch (\LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_late_submission_cannot_change_a_finalized_report(): void
    {
        [$owner, $assessment] = $this->context(minimum: 1);
        $this->submitRespondent($assessment, 'high');
        $late = $this->newSession($assessment);
        $this->writeResponses($assessment, $late, 'low');
        app(MultiRespondentAggregationService::class)->finalize($assessment, $owner->user_id);
        $hash = $assessment->fresh()->reportSnapshot->content_hash;

        $this->expectException(ValidationException::class);
        try {
            app(RespondentSubmissionService::class)->submit($late);
        } finally {
            $this->assertSame($hash, $assessment->fresh()->reportSnapshot->content_hash);
            $this->assertNull($late->fresh()->submitted_at);
        }
    }

    public function test_final_aggregate_records_reproducible_inputs_and_report_metadata(): void
    {
        [$owner, $assessment, $release] = $this->context(minimum: 2);
        $this->submitRespondent($assessment, 'low');
        $this->submitRespondent($assessment, 'high');
        $preview = app(MultiRespondentAggregationService::class)->preview($assessment);
        $aggregation = app(MultiRespondentAggregationService::class)->finalize($assessment, $owner->user_id);

        $expectedInputHash = hash('sha256', json_encode([
            'method' => $preview['aggregation_method'],
            'minimum' => $preview['minimum_completed_respondents'],
            'eligible_sessions' => $preview['eligible_session_references'],
            'excluded_sessions' => $preview['excluded_sessions'],
        ], JSON_THROW_ON_ERROR));
        $report = $assessment->fresh()->reportSnapshot->payload;

        $this->assertSame($expectedInputHash, $aggregation->input_hash);
        $this->assertSame(
            hash('sha256', json_encode($preview['result'], JSON_THROW_ON_ERROR)),
            $aggregation->result_hash
        );
        $this->assertSame($release->catalogue_release_id, $report['respondent_collection']['catalogue_release_id']);
        $this->assertSame(2, $report['respondent_collection']['eligible_completed_respondents']);
        $this->assertCount(2, $report['respondent_collection']['contributing_session_references']);
        $this->assertSame($owner->user_id, $report['respondent_collection']['finalized_by']['user_id']);
    }

    public function test_multi_respondent_result_uses_ordinary_governed_share_link_without_exposing_sessions(): void
    {
        [$owner, $assessment] = $this->context(minimum: 1);
        $score = $this->submitRespondent($assessment, 'high');
        app(MultiRespondentAggregationService::class)->finalize($assessment, $owner->user_id);

        $this->actingAs($owner)
            ->post(route('assessments.share', $assessment))
            ->assertSessionHas('share_link');
        $share = AssessmentShareLink::where('assessment_id', $assessment->assessment_id)->firstOrFail();

        $this->get(route('reports.shared.token', $share->token))
            ->assertOk()
            ->assertSee('Respondent aggregate')
            ->assertSee('eligible completed respondents')
            ->assertDontSee($score->public_response_session_id);
    }

    private function context(
        ?int $minimum = 2,
        bool $publish = true,
        array $eligibilityRules = [],
    ): array {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['plan' => 'PRO']);
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $owner->user_id,
            'role' => 'OWNER',
        ]);
        $owner->update(['active_workspace_id' => $workspace->workspace_id]);
        app()->instance('current.workspace', $workspace);
        $this->actingAs($owner);

        $project = Project::create(['name' => 'Respondent Collection', 'owner_user_id' => $owner->user_id]);
        $target = Target::create([
            'owner_workspace_id' => $workspace->workspace_id,
            'target_type_code' => 'COMMUNITY',
            'name' => 'Collection setting',
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $release = AssessmentCatalogueRelease::create([
            'release_code' => 'MULTI_'.Str::upper(Str::random(8)),
            'release_name' => 'Patient experience assessment',
            'description' => 'Multi-respondent governed demonstration assessment.',
            'creation_path' => 'FOCUSED',
            'health_domain_id' => HealthDomain::where('domain_code', 'MENTAL_HEALTH')->value('health_domain_id'),
            'aggregation_policy' => [
                'method' => 'MEAN_OF_SCORED_SUB_INDICES',
                'critical_failures' => ['enabled' => false],
            ],
            'composition_rules' => ['latest_resolution' => 'forbidden'],
            'collection_config' => [
                'allows_multi_respondent' => true,
                'minimum_completed_respondents' => $minimum,
                'aggregation_method' => 'ARITHMETIC_MEAN',
                'respondent_eligibility_rules' => $eligibilityRules,
                'scoring_profile_version' => 'vytte-4.0-numeric-bands',
            ],
        ]);
        $framework = DepartmentFrameworkVersion::query()
            ->whereHas('module', fn ($query) => $query->where('module_code', 'DMNH'))
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->firstOrFail();
        $release->departmentFrameworkVersions()->attach($framework->framework_version_id, [
            'module_id' => $framework->module_id,
            'applicability' => 'REQUIRED',
            'display_order' => 1,
            'area_label' => 'Patient experience',
        ]);

        if (! $publish) {
            return [$owner, null, $release, $workspace];
        }

        $release = app(CataloguePublishingService::class)->publish($release, $owner->user_id);
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue(
            $project,
            $release,
            creatorId: $owner->user_id,
        );

        return [$owner, $assessment, $release, $workspace];
    }

    private function newSession(Assessment $assessment): PublicResponseSession
    {
        $token = Str::random(32);
        AssessmentRespondentToken::create([
            'token' => $token,
            'assessment_id' => $assessment->assessment_id,
        ]);

        return PublicResponseSession::create([
            'token' => $token,
            'assessment_id' => $assessment->assessment_id,
            'locale' => 'en',
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);
    }

    private function submitRespondent(Assessment $assessment, string $selection)
    {
        $session = $this->newSession($assessment);
        $this->writeResponses($assessment, $session, $selection);

        return app(RespondentSubmissionService::class)->submit($session)->fresh(['responseSession.accessToken']);
    }

    private function writeResponses(
        Assessment $assessment,
        PublicResponseSession $session,
        string $selection,
        bool $skipLast = false,
    ): void {
        $questions = collect($assessment->snapshot->payload)
            ->flatMap(fn ($module) => $module['questions'] ?? [])
            ->where('is_scored', true)
            ->values();
        if ($skipLast) {
            $questions = $questions->slice(0, max(0, $questions->count() - 1));
        }

        foreach ($questions as $question) {
            $options = collect($question['options'])->whereNotNull('score_weight');
            $option = $selection === 'high'
                ? $options->sortByDesc('score_weight')->first()
                : $options->sortBy('score_weight')->first();
            Response::create([
                'assessment_id' => $assessment->assessment_id,
                'question_id' => $question['question_id'],
                'public_response_session_id' => $session->session_id,
                'value_option_id' => $option['option_id'],
                'answered_at' => now(),
            ]);
        }
    }
}
