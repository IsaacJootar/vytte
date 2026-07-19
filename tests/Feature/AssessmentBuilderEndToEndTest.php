<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\AssessmentScore;
use App\Models\DepartmentFrameworkVersion;
use App\Models\Domain;
use App\Models\FrameworkQuestionPlacement;
use App\Models\FrameworkSection;
use App\Models\HealthDomain;
use App\Models\Project;
use App\Models\Response;
use App\Models\SubIndex;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AssessmentCreationService;
use App\Services\ReportSnapshotService;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Follows content authored in the builder all the way through the engine it feeds:
 * snapshot, responses, scoring and the immutable report.
 *
 * The builder is only correct if what it publishes scores and reports correctly. These
 * tests assert the numbers, not merely that the pipeline runs.
 */
class AssessmentBuilderEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);
    }

    /**
     * Publishes an assessment with two scored yes/no questions, the second marked critical
     * on its failing answer.
     */
    private function publishAssessment(): AssessmentCatalogueRelease
    {
        $module = AssessmentModule::where('is_active', true)->orderBy('module_id')->firstOrFail();
        $this->post(route('admin.assessments.store'), [
            'display_name' => 'End To End Assessment',
            'module_id' => $module->module_id,
        ]);
        $assessment = DepartmentFrameworkVersion::where('display_name', 'End To End Assessment')->firstOrFail();

        SubIndex::where('module_id', $module->module_id)->delete();
        SubIndex::create([
            'module_id' => $module->module_id,
            'domain_id' => Domain::orderBy('domain_id')->firstOrFail()->domain_id,
            'acronym' => 'E2E',
            'full_name' => 'End To End Score',
        ]);

        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Readiness']);
        $section = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();

        foreach (['Is oxygen available?', 'Is the register complete?'] as $text) {
            $this->post(route('admin.assessments.questions.store', [$assessment, $section]), [
                'question_text' => $text,
                'format' => 'yes_no',
            ]);
        }

        $placements = FrameworkQuestionPlacement::where('framework_version_id', $assessment->framework_version_id)
            ->orderBy('display_order')->get();

        $this->put(route('admin.assessments.questions.settings.save', [$assessment, $placements[0]]), [
            'is_scored' => 1, 'evidence_mode' => 'note',
            'evidence_prompt' => 'Name the cylinder you checked.',
            'points' => [1 => 100, 2 => 0],
        ]);
        $this->put(route('admin.assessments.questions.settings.save', [$assessment, $placements[1]]), [
            'is_scored' => 1, 'evidence_mode' => 'none',
            'points' => [1 => 100, 2 => 0],
            'critical' => [2 => 1],
        ]);

        foreach ($placements as $placement) {
            $this->patch(route('admin.assessments.questions.approve', [$assessment, $placement]));
        }

        $this->put(route('admin.assessments.provenance', $assessment), [
            'source_authority' => 'End to end authority', 'license_code' => 'E2E-1.0',
        ]);
        $this->post(route('admin.assessments.publish', $assessment), [
            'health_domain_id' => HealthDomain::orderBy('health_domain_id')->firstOrFail()->health_domain_id,
            'confirm' => 1,
        ])->assertSessionHasNoErrors();

        return AssessmentCatalogueRelease::whereHas(
            'departmentFrameworkVersions',
            fn ($q) => $q->where('department_framework_versions.framework_version_id', $assessment->framework_version_id)
        )->firstOrFail();
    }

    private function workspaceAssessment(AssessmentCatalogueRelease $release): Assessment
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

        $project = Project::create(['name' => 'E2E Project', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'E2E Target',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        return app(AssessmentCreationService::class)->createFromCatalogue($project, $release, creatorId: $user->user_id);
    }

    public function test_authored_content_reaches_the_snapshot_intact(): void
    {
        $this->actingAs($this->admin());
        $assessment = $this->workspaceAssessment($this->publishAssessment());

        $questions = collect($assessment->snapshot->payload[0]['questions']);

        $this->assertCount(2, $questions);
        $this->assertSame('Is oxygen available?', $questions[0]['question_text']);
        $this->assertTrue($questions[0]['is_scored']);
        $this->assertSame('Name the cylinder you checked.', $questions[0]['evidence_expectation']);
        $this->assertSame('Readiness', $questions[0]['section_name']);

        // Answer options must carry real ids so responses can reference them.
        foreach ($questions as $question) {
            foreach ($question['options'] as $option) {
                $this->assertDatabaseHas('question_options', ['option_id' => $option['option_id']]);
            }
        }
    }

    public function test_authored_points_produce_the_expected_score(): void
    {
        $this->actingAs($this->admin());
        $assessment = $this->workspaceAssessment($this->publishAssessment());
        $questions = collect($assessment->snapshot->payload[0]['questions']);

        // Answer the first question Yes (100) and the second Yes (100).
        foreach ($questions as $question) {
            Response::create([
                'assessment_id' => $assessment->assessment_id,
                'question_id' => $question['question_id'],
                'value_option_id' => collect($question['options'])->firstWhere('option_label', 'Yes')['option_id'],
                'answered_at' => now(),
            ]);
        }

        app(ScoringService::class)->calculate($assessment->fresh(['snapshot']));

        $score = AssessmentScore::where('assessment_id', $assessment->assessment_id)->firstOrFail();
        $this->assertEquals(100.0, (float) $score->overall_score);
        $this->assertSame('CALIBRATED', $score->calibration_status);
    }

    public function test_an_answer_marked_critical_drives_the_critical_failure_rule(): void
    {
        $this->actingAs($this->admin());
        $assessment = $this->workspaceAssessment($this->publishAssessment());
        $questions = collect($assessment->snapshot->payload[0]['questions']);

        // First question Yes, second question No, which was marked critical.
        Response::create([
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $questions[0]['question_id'],
            'value_option_id' => collect($questions[0]['options'])->firstWhere('option_label', 'Yes')['option_id'],
            'answered_at' => now(),
        ]);
        Response::create([
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $questions[1]['question_id'],
            'value_option_id' => collect($questions[1]['options'])->firstWhere('option_label', 'No')['option_id'],
            'answered_at' => now(),
        ]);

        app(ScoringService::class)->calculate($assessment->fresh(['snapshot']));

        $score = AssessmentScore::where('assessment_id', $assessment->assessment_id)->firstOrFail();
        $this->assertSame('CRITICAL_FAILURE', $score->calibration_status);
        $this->assertEquals(0.0, (float) $score->overall_score);
    }

    public function test_a_report_from_authored_content_is_immutable_and_carries_the_scores(): void
    {
        $this->actingAs($this->admin());
        $assessment = $this->workspaceAssessment($this->publishAssessment());
        $questions = collect($assessment->snapshot->payload[0]['questions']);

        foreach ($questions as $question) {
            Response::create([
                'assessment_id' => $assessment->assessment_id,
                'question_id' => $question['question_id'],
                'value_option_id' => collect($question['options'])->firstWhere('option_label', 'Yes')['option_id'],
                'answered_at' => now(),
            ]);
        }

        app(ScoringService::class)->calculate($assessment->fresh(['snapshot']));
        $assessment->update(['status' => Assessment::STATUS_COMPLETE, 'completed_at' => now()]);
        $report = app(ReportSnapshotService::class)->createFor($assessment->fresh(['snapshot', 'score']));

        $this->assertSame('End To End Assessment', $report->payload['title']);
        $this->assertEquals(100.0, $report->payload['score']['overall_score']);
        $this->assertNotEmpty($report->content_hash);

        $this->expectException(\LogicException::class);
        $report->update(['payload' => ['tampered' => true]]);
    }

    public function test_publishing_a_second_version_does_not_disturb_assessments_already_created(): void
    {
        $this->actingAs($this->admin());
        $release = $this->publishAssessment();
        $assessment = $this->workspaceAssessment($release);
        $originalHash = $assessment->snapshot->content_hash;
        $originalPayload = $assessment->snapshot->payload;

        $published = DepartmentFrameworkVersion::where('display_name', 'End To End Assessment')
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)->firstOrFail();

        $this->actingAs($this->admin());
        $this->post(route('admin.assessments.versions.store', $published));
        $successor = DepartmentFrameworkVersion::where('parent_version_id', $published->framework_version_id)->firstOrFail();
        $section = FrameworkSection::where('framework_version_id', $successor->framework_version_id)->firstOrFail();
        $this->post(route('admin.assessments.questions.store', [$successor, $section]), [
            'question_text' => 'A question added in version two?',
            'format' => 'yes_no',
        ]);
        $newPlacement = FrameworkQuestionPlacement::where('framework_version_id', $successor->framework_version_id)
            ->orderByDesc('display_order')->firstOrFail();
        $this->patch(route('admin.assessments.questions.approve', [$successor, $newPlacement]));
        $this->post(route('admin.assessments.publish', $successor), [
            'health_domain_id' => HealthDomain::orderBy('health_domain_id')->firstOrFail()->health_domain_id,
            'confirm' => 1,
        ])->assertSessionHasNoErrors();

        // The existing assessment keeps the content it was created with.
        $assessment->refresh();
        $this->assertSame($originalHash, $assessment->snapshot->content_hash);
        $this->assertEquals($originalPayload, $assessment->snapshot->payload);
        $this->assertCount(2, $assessment->snapshot->payload[0]['questions']);
    }
}
