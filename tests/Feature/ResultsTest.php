<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\Project;
use App\Models\Response;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AssessmentCreationService;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithWorkspace(): array
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

    private function createGovernedAssessment(Workspace $workspace, User $user): Assessment
    {
        $project = Project::create([
            'name' => 'Results Test Project',
            'owner_user_id' => $user->user_id,
        ]);

        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Test Community',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);

        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();

        return app(AssessmentCreationService::class)->createFromCatalogue($project, $release);
    }

    private function setupCompleteAssessment(Workspace $workspace, User $user, bool $withAnswers = true, string $answerMode = 'best'): Assessment
    {
        $assessment = $this->createGovernedAssessment($workspace, $user);

        if ($withAnswers) {
            $this->answerSnapshotQuestions($assessment, $answerMode);
        }

        app(ScoringService::class)->calculate($assessment);
        $assessment->update(['status' => Assessment::STATUS_COMPLETE, 'completed_at' => now()]);

        return $assessment->fresh(['snapshot', 'score']);
    }

    private function answerSnapshotQuestions(Assessment $assessment, string $mode): void
    {
        $questions = collect($assessment->snapshot->payload)
            ->flatMap(fn ($module) => $module['questions'] ?? [])
            ->where('is_scored', true);

        foreach ($questions as $question) {
            $options = collect($question['options'])->whereNotNull('score_weight');
            $optionId = ($mode === 'worst' ? $options->sortBy('score_weight') : $options->sortByDesc('score_weight'))
                ->first()['option_id'];

            Response::updateOrCreate(
                ['assessment_id' => $assessment->assessment_id, 'question_id' => $question['question_id'], 'respondent_id' => null],
                ['value_option_id' => $optionId, 'answered_at' => now()]
            );
        }
    }

    // ---- Auth gate ----

    public function test_results_page_requires_auth(): void
    {

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspace, $user);

        $this->get(route('assessments.results', $assessment))
            ->assertRedirect(route('login'));
    }

    // ---- Redirect for incomplete assessment ----

    public function test_results_redirects_to_runner_if_not_complete(): void
    {

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createGovernedAssessment($workspace, $user);

        $this->actingAs($user)
            ->get(route('assessments.results', $assessment))
            ->assertRedirect(route('assessments.run', $assessment));
    }

    // ---- Results page renders correctly ----

    public function test_results_page_renders_for_calibrated_assessment(): void
    {

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspace, $user, withAnswers: true);

        $this->actingAs($user)
            ->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('Overall Score')
            ->assertSee('Sub-index Breakdown')
            ->assertSee('Domain Breakdown');
    }

    public function test_results_page_shows_module_name(): void
    {

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspace, $user);

        $this->actingAs($user)
            ->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('Mental Health');
    }

    public function test_results_page_shows_score_for_calibrated_assessment(): void
    {

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspace, $user, withAnswers: true);

        // With all best answers, score = 100.0
        $this->actingAs($user)
            ->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('100');
    }

    // ---- Uncalibrated shown as flagged ----

    public function test_results_page_flags_uncalibrated_when_no_answers(): void
    {

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspace, $user, withAnswers: false);

        $response = $this->actingAs($user)
            ->get(route('assessments.results', $assessment));

        $response->assertOk();
        // The NOT_CALIBRATED warning banner must be visible — never shows a zero
        $response->assertSee('Not enough responses');
        $response->assertDontSee('>0.0<'); // score 0 must not appear
    }

    public function test_results_page_shows_uncalibrated_sub_index_flag(): void
    {

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspace, $user, withAnswers: false);

        $this->actingAs($user)
            ->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('Not calibrated — no answers');
    }

    // ---- Findings shown for weak scores ----

    public function test_findings_shown_for_weak_sub_index_scores(): void
    {

        [$user, $workspace] = $this->userWithWorkspace();

        $assessment = $this->setupCompleteAssessment($workspace, $user, answerMode: 'worst');

        $this->actingAs($user)
            ->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('Findings')
            ->assertSee('Weak');
    }

    // ---- Score history shows when 2+ runs ----

    public function test_score_history_hidden_for_single_run(): void
    {

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspace, $user);

        $this->actingAs($user)
            ->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertDontSee('Score History');
    }

    // ---- Workspace isolation ----

    public function test_workspace_b_cannot_view_workspace_a_results(): void
    {

        [$userA, $workspaceA] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspaceA, $userA);

        $userB = User::factory()->create();
        $workspaceB = Workspace::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspaceB->workspace_id,
            'user_id' => $userB->user_id,
            'role' => 'OWNER',
        ]);
        $userB->update(['active_workspace_id' => $workspaceB->workspace_id]);
        app()->instance('current.workspace', $workspaceB);

        $this->actingAs($userB)
            ->get(route('assessments.results', $assessment))
            ->assertNotFound();
    }

    // ---- Domain breakdown visible ----

    public function test_domain_breakdown_shown_for_calibrated_assessment(): void
    {

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspace, $user, withAnswers: true);

        $this->actingAs($user)
            ->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('Domain Breakdown');
    }

    // ---- Print button present ----

    public function test_print_button_present_on_results_page(): void
    {

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspace, $user);

        $this->actingAs($user)
            ->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('Print');
    }
}
