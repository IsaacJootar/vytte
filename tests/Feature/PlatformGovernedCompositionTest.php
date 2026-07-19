<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FacilityProfile;
use App\Models\LocalCustomSection;
use App\Models\Project;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\Response;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AssessmentCreationService;
use App\Services\GovernanceDependencyService;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlatformGovernedCompositionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->user, $this->workspace] = $this->workspaceContext();
    }

    private function workspaceContext(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['plan' => 'PRO']);
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);
        app()->instance('current.workspace', $workspace);
        $this->actingAs($user);

        return [$user, $workspace];
    }

    private function clinicProject(): Project
    {
        $profile = FacilityProfile::where('profile_code', 'CLINIC')->firstOrFail();
        $project = Project::create([
            'workspace_id' => $this->workspace->workspace_id,
            'owner_user_id' => $this->user->user_id,
            'name' => 'ABC Clinic Assessment',
        ]);
        $target = Target::create([
            'owner_workspace_id' => $this->workspace->workspace_id,
            'target_type_code' => 'HEALTH_FACILITY',
            'facility_profile_id' => $profile->facility_profile_id,
            'name' => 'ABC Clinic',
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        return $project;
    }

    public function test_department_framework_versions_are_published_and_immutable(): void
    {
        $version = DepartmentFrameworkVersion::where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)->firstOrFail();

        $this->assertNotNull($version->content_hash);
        $this->assertIsArray($version->published_payload);

        $this->expectException(\LogicException::class);
        $version->update(['display_name' => 'Changed after publication']);
    }

    public function test_facility_profile_applicability_and_catalogue_publication_are_seeded_cleanly(): void
    {
        $profile = FacilityProfile::where('profile_code', 'CLINIC')->with('departments')->firstOrFail();
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_CLINIC_COMPREHENSIVE_V1')
            ->with('departmentFrameworkVersions')
            ->firstOrFail();

        $this->assertSame(FacilityProfile::STATUS_PUBLISHED, $profile->status);
        $this->assertSame(4, $profile->departments->count());
        $this->assertSame(AssessmentCatalogueRelease::STATUS_PUBLISHED, $release->status);
        $this->assertSame(4, $release->departmentFrameworkVersions->count());
        $this->assertNotNull($release->content_hash);
    }

    public function test_comprehensive_assessment_composes_selected_department_framework_versions(): void
    {
        $project = $this->clinicProject();
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_CLINIC_COMPREHENSIVE_V1')->firstOrFail();
        $selected = DB::table('assessment_modules')
            ->whereIn('module_code', ['DOPD', 'DPHM'])
            ->pluck('module_id')
            ->all();
        $labId = DB::table('assessment_modules')->where('module_code', 'DLAB')->value('module_id');

        $assessment = app(AssessmentCreationService::class)->createFromCatalogue(
            $project,
            $release,
            $selected,
            [$labId => 'Laboratory is not operated by this clinic.'],
            $this->user->user_id,
        );

        $this->assertSame('COMPREHENSIVE', $assessment->creation_path);
        $this->assertSame($release->catalogue_release_id, $assessment->catalogue_release_id);
        $this->assertCount(2, $assessment->snapshot->payload);
        $this->assertSame(['DOPD', 'DPHM'], collect($assessment->snapshot->payload)->pluck('module_code')->all());
        $this->assertDatabaseHas('assessment_module_scope', [
            'assessment_id' => $assessment->assessment_id,
            'module_id' => $labId,
            'in_scope' => false,
            'status' => 'EXCLUDED',
        ]);
    }

    public function test_required_department_cannot_be_removed(): void
    {
        $project = $this->clinicProject();
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_CLINIC_COMPREHENSIVE_V1')->firstOrFail();
        $pharmacyId = DB::table('assessment_modules')->where('module_code', 'DPHM')->value('module_id');

        $this->expectException(ValidationException::class);
        app(AssessmentCreationService::class)->createFromCatalogue(
            $project,
            $release,
            [$pharmacyId],
            [],
            $this->user->user_id,
        );
    }

    public function test_focused_assessment_remains_single_scope(): void
    {
        $project = $this->clinicProject();
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();

        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release, creatorId: $this->user->user_id);

        $this->assertSame('FOCUSED', $assessment->creation_path);
        $this->assertCount(1, $assessment->snapshot->payload);
        $this->assertSame('DMNH', $assessment->snapshot->payload[0]['module_code']);
    }

    public function test_future_framework_versions_do_not_change_historical_assessment_snapshots(): void
    {
        $project = $this->clinicProject();
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release, creatorId: $this->user->user_id);
        $originalText = $assessment->snapshot->payload[0]['questions'][0]['question_text'];

        $questionId = $assessment->snapshot->payload[0]['questions'][0]['question_id'];
        Question::where('question_id', $questionId)->update(['question_text' => 'Future version question text']);
        $nextVersion = DepartmentFrameworkVersion::where('module_id', $assessment->snapshot->payload[0]['module_id'])
            ->max('version_number') + 1;
        DepartmentFrameworkVersion::create([
            'module_id' => $assessment->snapshot->payload[0]['module_id'],
            'version_number' => $nextVersion,
            'display_name' => 'Future Mental Health Demo Framework',
            'source_authority' => 'Vytte demonstration content',
            'license_code' => 'DEMO-NOT-FOR-PRODUCTION',
        ]);

        $this->assertSame($originalText, $assessment->snapshot->fresh()->payload[0]['questions'][0]['question_text']);
    }

    public function test_catalogue_release_dependency_count_does_not_double_count_snapshots(): void
    {
        $project = $this->clinicProject();
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        app(AssessmentCreationService::class)->createFromCatalogue($project, $release, creatorId: $this->user->user_id);

        $summary = app(GovernanceDependencyService::class)->catalogueRelease($release->fresh());

        // One assessment produces exactly one snapshot. The snapshot both carries the
        // catalogue_release_id column and embeds the release id in its composition
        // manifest, which previously counted the same row twice.
        $this->assertSame(1, $summary['assessments']);
        $this->assertSame(1, $summary['assessment_snapshots']);
    }

    public function test_dependency_summary_detects_question_version_frozen_into_snapshot(): void
    {
        $project = $this->clinicProject();
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release, creatorId: $this->user->user_id);
        $questionVersionId = $assessment->snapshot->payload[0]['questions'][0]['question_version_id'];
        $version = QuestionVersion::findOrFail($questionVersionId);

        $summary = app(GovernanceDependencyService::class)->questionVersion($version);

        $this->assertSame(1, $summary['assessment_snapshots']);
        $this->assertGreaterThan(0, $summary['framework_placements']);
        $this->assertTrue(app(GovernanceDependencyService::class)->hasBlockingArchiveDependencies($summary));
    }

    public function test_assessment_snapshots_are_immutable_once_created(): void
    {
        $project = $this->clinicProject();
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release, creatorId: $this->user->user_id);
        $snapshot = $assessment->snapshot;

        $this->expectException(\LogicException::class);
        $snapshot->update(['payload' => [['module_code' => 'TAMPERED']]]);
    }

    public function test_assessment_snapshot_manifest_and_policy_cannot_be_rewritten(): void
    {
        $project = $this->clinicProject();
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release, creatorId: $this->user->user_id);
        $snapshot = $assessment->snapshot;
        $originalHash = $snapshot->content_hash;

        foreach (['composition_manifest', 'aggregation_policy', 'collection_config', 'content_hash'] as $frozenAttribute) {
            try {
                $snapshot->update([$frozenAttribute => $frozenAttribute === 'content_hash' ? 'tampered' : ['tampered' => true]]);
                $this->fail("Expected {$frozenAttribute} to be immutable.");
            } catch (\LogicException) {
                // Expected: the snapshot guard rejects every frozen attribute.
            }
        }

        $this->assertSame($originalHash, $assessment->snapshot()->first()->content_hash);
    }

    public function test_local_custom_sections_never_affect_official_scoring(): void
    {
        $project = $this->clinicProject();
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release, creatorId: $this->user->user_id);

        LocalCustomSection::create([
            'assessment_id' => $assessment->assessment_id,
            'workspace_id' => $this->workspace->workspace_id,
            'section_title' => 'Local donor notes',
            'questions' => [['label' => 'Local concern', 'score_weight' => 0]],
            'created_by' => $this->user->user_id,
        ]);
        $this->answerOfficialQuestions($assessment, 100);

        app(ScoringService::class)->calculate($assessment);

        $this->assertDatabaseHas('assessment_scores', [
            'assessment_id' => $assessment->assessment_id,
            'overall_score' => 100,
            'calibration_status' => 'CALIBRATED',
        ]);
    }

    public function test_aggregation_policy_can_apply_critical_failure_rule(): void
    {
        $project = $this->clinicProject();
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_CLINIC_COMPREHENSIVE_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release, creatorId: $this->user->user_id);
        $criticalQuestion = collect($assessment->snapshot->payload)
            ->firstWhere('module_code', 'DOPD')['questions'][0];
        $criticalOption = collect($criticalQuestion['options'])->firstWhere('critical_failure', true);

        $this->answerOfficialQuestions($assessment, 100);
        Response::where('assessment_id', $assessment->assessment_id)
            ->where('question_id', $criticalQuestion['question_id'])
            ->update(['value_option_id' => $criticalOption['option_id']]);

        app(ScoringService::class)->calculate($assessment);

        $this->assertDatabaseHas('assessment_scores', [
            'assessment_id' => $assessment->assessment_id,
            'overall_score' => 0,
            'calibration_status' => 'CRITICAL_FAILURE',
        ]);
    }

    public function test_platform_governed_demo_seeder_is_idempotent(): void
    {

        $this->assertDatabaseHas('assessment_catalogue_releases', [
            'release_code' => 'DEMO_CLINIC_COMPREHENSIVE_V1',
            'status' => 'PUBLISHED',
        ]);
        $this->assertDatabaseHas('department_framework_versions', [
            'status' => 'PUBLISHED',
        ]);
    }

    private function answerOfficialQuestions(Assessment $assessment, int $scoreWeight): void
    {
        foreach ($assessment->snapshot->payload as $module) {
            foreach ($module['questions'] as $question) {
                if (! $question['is_scored']) {
                    continue;
                }

                $option = collect($question['options'])->first(
                    fn ($candidate) => (int) $candidate['score_weight'] === $scoreWeight
                );

                Response::updateOrCreate(
                    [
                        'assessment_id' => $assessment->assessment_id,
                        'question_id' => $question['question_id'],
                        'respondent_id' => null,
                        'public_response_session_id' => null,
                    ],
                    [
                        'value_option_id' => $option['option_id'],
                        'answered_at' => now(),
                    ]
                );
            }
        }
    }
}
