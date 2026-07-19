<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FacilityProfile;
use App\Models\FrameworkIndicator;
use App\Models\FrameworkQuestionPlacement;
use App\Models\FrameworkSection;
use App\Models\LocalCustomSection;
use App\Models\Project;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\Response;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceCustomAssessmentDesign;
use App\Models\WorkspaceMember;
use App\Services\AssessmentCreationService;
use App\Services\DepartmentFrameworkPublishingService;
use App\Services\QuestionVersionPublishingService;
use App\Services\ScoringService;
use App\Services\WorkspaceCustomAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QuestionBankArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceOwner(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);
        app()->instance('current.workspace', $workspace);

        return [$user, $workspace];
    }

    private function clinicProject(User $user, Workspace $workspace): Project
    {
        $profile = FacilityProfile::where('profile_code', 'CLINIC')->firstOrFail();
        $project = Project::create(['name' => 'Question Bank Test', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'HEALTH_FACILITY',
            'facility_profile_id' => $profile->facility_profile_id,
            'name' => 'Question Bank Clinic',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        return $project;
    }

    private function focusedAssessment(User $user, Workspace $workspace): Assessment
    {
        $project = $this->clinicProject($user, $workspace);
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();

        return app(AssessmentCreationService::class)->createFromCatalogue($project, $release, creatorId: $user->user_id);
    }

    public function test_reusable_question_and_published_immutable_question_version_exist(): void
    {
        $question = Question::where('question_code', 'DEMO.SERVICE_PROCESS')->firstOrFail();
        $version = $question->versions()->where('version_number', 1)->firstOrFail();

        $this->assertSame(QuestionVersion::STATUS_PUBLISHED, $version->status);
        $this->assertNotNull($version->content_hash);

        $this->expectException(\LogicException::class);
        $version->update(['question_text' => 'Mutating published wording is not allowed.']);
    }

    public function test_only_approved_question_versions_can_be_published(): void
    {
        $question = Question::firstOrFail();
        $draft = QuestionVersion::create([
            'question_id' => $question->question_id,
            'version_number' => 99,
            'status' => QuestionVersion::STATUS_DRAFT,
            'question_text' => 'Draft text',
            'type_id' => $question->type_id,
            'options' => [],
            'numeric_bands' => [],
        ]);

        $this->expectException(ValidationException::class);
        app(QuestionVersionPublishingService::class)->publish($draft);
    }

    public function test_one_question_version_can_be_placed_in_multiple_frameworks_with_framework_specific_wording(): void
    {
        $question = Question::where('question_code', 'DEMO.SERVICE_PROCESS')->firstOrFail();
        $version = $question->versions()->where('version_number', 1)->firstOrFail();

        $placements = FrameworkQuestionPlacement::where('question_version_id', $version->question_version_id)
            ->with('frameworkVersion')
            ->get();

        $this->assertGreaterThanOrEqual(2, $placements->count());
        $this->assertTrue($placements->pluck('local_display_text')->contains('Does Mental Health have a documented service process available today?'));
        $this->assertTrue($placements->pluck('frameworkVersion.framework_type')->contains(DepartmentFrameworkVersion::TYPE_FOCUSED));
    }

    public function test_draft_placements_can_be_reordered_without_a_unique_display_order_conflict(): void
    {
        $module = DepartmentFrameworkVersion::where('framework_type', DepartmentFrameworkVersion::TYPE_DEPARTMENT)
            ->firstOrFail()
            ->module;
        $framework = DepartmentFrameworkVersion::create([
            'module_id' => $module->module_id,
            'version_number' => 900,
            'display_name' => 'Reorder draft framework',
            'source_authority' => 'Vytte test',
            'license_code' => 'TEST',
        ]);
        $section = FrameworkSection::create([
            'framework_version_id' => $framework->framework_version_id,
            'section_code' => 'ORDER',
            'section_name' => 'Order',
        ]);
        $indicator = FrameworkIndicator::create([
            'framework_version_id' => $framework->framework_version_id,
            'framework_section_id' => $section->framework_section_id,
            'indicator_code' => 'ORDER',
            'indicator_name' => 'Order',
        ]);

        $versions = QuestionVersion::where('status', QuestionVersion::STATUS_PUBLISHED)->take(2)->get();
        $placements = $versions->map(fn ($version, $index) => FrameworkQuestionPlacement::create([
            'framework_version_id' => $framework->framework_version_id,
            'framework_section_id' => $section->framework_section_id,
            'framework_indicator_id' => $indicator->framework_indicator_id,
            'question_id' => $version->question_id,
            'question_version_id' => $version->question_version_id,
            'display_order' => $index + 1,
            'scoring_contribution' => false,
        ]));

        // A straight swap passes through a state where both rows share display_order 2.
        // The former unique constraint made this impossible without renumbering tricks.
        $placements[0]->update(['display_order' => 2]);
        $placements[1]->update(['display_order' => 1]);

        $this->assertSame(2, (int) $placements[0]->fresh()->display_order);
        $this->assertSame(1, (int) $placements[1]->fresh()->display_order);
    }

    public function test_framework_publication_requires_exact_published_question_versions(): void
    {
        $module = DepartmentFrameworkVersion::where('framework_type', DepartmentFrameworkVersion::TYPE_DEPARTMENT)
            ->firstOrFail()
            ->module;
        $draftQuestion = Question::firstOrFail();
        $draftVersion = QuestionVersion::create([
            'question_id' => $draftQuestion->question_id,
            'version_number' => 100,
            'status' => QuestionVersion::STATUS_DRAFT,
            'question_text' => 'Draft framework question',
            'type_id' => $draftQuestion->type_id,
            'options' => [],
            'numeric_bands' => [],
        ]);
        $framework = DepartmentFrameworkVersion::create([
            'module_id' => $module->module_id,
            'version_number' => 99,
            'display_name' => 'Draft blocked framework',
            'source_authority' => 'Vytte test',
            'license_code' => 'TEST',
        ]);
        $section = FrameworkSection::create([
            'framework_version_id' => $framework->framework_version_id,
            'section_code' => 'TEST',
            'section_name' => 'Test',
        ]);
        $indicator = FrameworkIndicator::create([
            'framework_version_id' => $framework->framework_version_id,
            'framework_section_id' => $section->framework_section_id,
            'indicator_code' => 'TEST',
            'indicator_name' => 'Test',
        ]);
        FrameworkQuestionPlacement::create([
            'framework_version_id' => $framework->framework_version_id,
            'framework_section_id' => $section->framework_section_id,
            'framework_indicator_id' => $indicator->framework_indicator_id,
            'question_id' => $draftQuestion->question_id,
            'question_version_id' => $draftVersion->question_version_id,
            'display_order' => 1,
            'scoring_contribution' => false,
        ]);

        $this->expectException(ValidationException::class);
        app(DepartmentFrameworkPublishingService::class)->publish($framework);
    }

    public function test_future_question_versions_do_not_change_published_framework_or_assessment_snapshots(): void
    {
        [$user, $workspace] = $this->workspaceOwner();
        $assessment = $this->focusedAssessment($user, $workspace);
        $snapshotText = $assessment->snapshot->payload[0]['questions'][0]['question_text'];
        $framework = DepartmentFrameworkVersion::where('framework_type', DepartmentFrameworkVersion::TYPE_FOCUSED)
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->firstOrFail();

        $futureVersion = QuestionVersion::where('question_id', $assessment->snapshot->payload[0]['questions'][0]['question_id'])
            ->where('version_number', 2)
            ->firstOrFail();

        $this->assertNotSame($futureVersion->question_text, $snapshotText);
        $this->assertSame($snapshotText, $assessment->snapshot->fresh()->payload[0]['questions'][0]['question_text']);
        $this->assertSame($snapshotText, collect($framework->published_payload['questions'])->first()['question_text']);
    }

    public function test_snapshot_freezes_question_version_evidence_and_scoring_payload(): void
    {
        [$user, $workspace] = $this->workspaceOwner();
        $assessment = $this->focusedAssessment($user, $workspace);
        $question = $assessment->snapshot->payload[0]['questions'][0];

        $this->assertArrayHasKey('question_version_id', $question);
        $this->assertArrayHasKey('question_version_hash', $question);
        $this->assertArrayHasKey('evidence_expectation', $question);
        $this->assertArrayHasKey('options', $question);
        $this->assertArrayHasKey('scoring_profile', $assessment->snapshot->payload[0]);
    }

    public function test_customer_workspace_cannot_manage_official_question_versions(): void
    {
        [$user] = $this->workspaceOwner();
        $version = QuestionVersion::firstOrFail();

        $this->assertFalse($user->can('publish', $version));
        $this->assertFalse($user->can('create', QuestionVersion::class));
    }

    public function test_local_custom_sections_are_excluded_from_official_scoring_and_critical_failures(): void
    {
        [$user, $workspace] = $this->workspaceOwner();
        $assessment = $this->focusedAssessment($user, $workspace);

        LocalCustomSection::create([
            'assessment_id' => $assessment->assessment_id,
            'workspace_id' => $workspace->workspace_id,
            'section_title' => 'Local critical note',
            'questions' => [['label' => 'Local critical issue', 'critical_failure' => true, 'score_weight' => 0]],
            'created_by' => $user->user_id,
        ]);

        collect($assessment->snapshot->payload)
            ->flatMap(fn ($module) => $module['questions'] ?? [])
            ->where('is_scored', true)
            ->each(function (array $question) use ($assessment) {
                $bestOption = collect($question['options'])->sortByDesc('score_weight')->first();
                Response::create([
                    'assessment_id' => $assessment->assessment_id,
                    'question_id' => $question['question_id'],
                    'value_option_id' => $bestOption['option_id'],
                    'answered_at' => now(),
                ]);
            });

        app(ScoringService::class)->calculate($assessment);

        $this->assertDatabaseHas('assessment_scores', [
            'assessment_id' => $assessment->assessment_id,
            'overall_score' => 100,
            'calibration_status' => 'CALIBRATED',
        ]);
    }

    public function test_workspace_custom_assessment_creation_is_isolated_and_not_official(): void
    {
        [$owner, $workspace] = $this->workspaceOwner();
        [$otherOwner, $otherWorkspace] = $this->workspaceOwner();

        $design = app(WorkspaceCustomAssessmentService::class)->createDraft($workspace, $owner, [
            'title' => 'Local Prison Health Review',
            'purpose' => 'Capture local operational observations outside official Vytte methodology.',
            'setting' => 'Prison',
            'sections' => [['title' => 'Local context']],
            'questions' => [['text' => 'Local observation?', 'response_type' => 'OPEN_ENDED']],
            'private_scoring_config' => ['kind' => 'completion_rate'],
            'ai_drafting_context' => ['status' => 'future_safe_boundary'],
        ]);

        $this->assertSame(WorkspaceCustomAssessmentDesign::STATUS_DRAFT, $design->status);
        $this->assertSame($workspace->workspace_id, $design->workspace_id);
        $this->assertFalse($otherOwner->can('view', $design));
        $this->assertDatabaseMissing('assessment_catalogue_releases', ['release_name' => 'Local Prison Health Review']);
        $this->assertDatabaseCount('workspace_custom_assessment_designs', 1);
        $this->assertNotSame($otherWorkspace->workspace_id, $design->workspace_id);
    }

    public function test_workspace_custom_scoring_cannot_claim_official_vytte_score(): void
    {
        [$owner, $workspace] = $this->workspaceOwner();

        $this->expectException(ValidationException::class);
        app(WorkspaceCustomAssessmentService::class)->createDraft($workspace, $owner, [
            'title' => 'Invalid custom score',
            'purpose' => 'Attempt to blur official scoring.',
            'private_scoring_config' => ['claims_official_vytte_score' => true],
        ]);
    }
}
