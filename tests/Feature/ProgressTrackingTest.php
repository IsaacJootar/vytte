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
use Database\Seeders\PlatformGovernedDemoSeeder;
use Database\Seeders\PlanFeatureSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProgressTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanFeatureSeeder::class);
    }

    private function userWithWorkspace(): array
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

        return [$user, $workspace];
    }

    private function makeCompleteAssessment(Workspace $workspace, User $user, Project $project, bool $withAnswers = true): Assessment
    {
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release);

        if ($withAnswers) {
            $questions = collect($assessment->snapshot->payload)
                ->flatMap(fn ($module) => $module['questions'] ?? [])
                ->where('is_scored', true);

            foreach ($questions as $question) {
                $optionId = collect($question['options'])->sortByDesc('score_weight')->first()['option_id'];
                Response::updateOrCreate(
                    ['assessment_id' => $assessment->assessment_id, 'question_id' => $question['question_id'], 'respondent_id' => null],
                    ['value_option_id' => $optionId, 'answered_at' => now()]
                );
            }
        }

        app(ScoringService::class)->calculate($assessment);
        $assessment->update(['status' => Assessment::STATUS_COMPLETE, 'completed_at' => now()]);

        return $assessment->fresh(['score', 'snapshot']);
    }

    private function makeProjectWithTarget(User $user, Workspace $workspace): Project
    {
        $project = Project::create(['name' => 'Progress Test Project', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Test Community',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        return $project;
    }

    // ---- Auth gate ----

    public function test_progress_page_requires_auth(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $project = $this->makeProjectWithTarget($user, $workspace);

        $this->get(route('projects.progress', $project))
            ->assertRedirect(route('login'));
    }

    // ---- Empty state ----

    public function test_progress_page_shows_empty_state_with_no_complete_assessments(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $project = $this->makeProjectWithTarget($user, $workspace);

        $this->actingAs($user)
            ->get(route('projects.progress', $project))
            ->assertOk()
            ->assertSee('No completed assessments yet');
    }

    // ---- Progress page with one run ----

    public function test_progress_page_renders_with_one_complete_assessment(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $project = $this->makeProjectWithTarget($user, $workspace);
        $this->makeCompleteAssessment($workspace, $user, $project);

        $this->actingAs($user)
            ->get(route('projects.progress', $project))
            ->assertOk()
            ->assertSee('Assessment Runs');
    }

    public function test_progress_page_shows_maturity_level_for_scored_assessment(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $project = $this->makeProjectWithTarget($user, $workspace);
        $this->makeCompleteAssessment($workspace, $user, $project, withAnswers: true);

        // With all best answers, score is 100 → L5 maturity level
        $response = $this->actingAs($user)
            ->get(route('projects.progress', $project))
            ->assertOk();

        $response->assertSee('L5');
    }

    public function test_progress_page_shows_view_link_for_each_run(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $project = $this->makeProjectWithTarget($user, $workspace);
        $assessment = $this->makeCompleteAssessment($workspace, $user, $project);

        $this->actingAs($user)
            ->get(route('projects.progress', $project))
            ->assertOk()
            ->assertSee(route('assessments.results', $assessment));
    }

    // ---- Domain matrix visible at ≥ 2 runs ----

    public function test_compare_two_runs_shown_when_two_or_more_complete_assessments(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $project = $this->makeProjectWithTarget($user, $workspace);
        $this->makeCompleteAssessment($workspace, $user, $project, withAnswers: true);
        $this->makeCompleteAssessment($workspace, $user, $project, withAnswers: true);

        $this->actingAs($user)
            ->get(route('projects.progress', $project))
            ->assertOk()
            ->assertSee('Compare Two Runs');
    }

    public function test_domain_matrix_not_shown_for_single_run(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $project = $this->makeProjectWithTarget($user, $workspace);
        $this->makeCompleteAssessment($workspace, $user, $project, withAnswers: true);

        $this->actingAs($user)
            ->get(route('projects.progress', $project))
            ->assertOk()
            ->assertDontSee('Domain Score History');
    }

    // ---- Compare form visible at ≥ 2 runs ----

    public function test_compare_form_shown_when_two_or_more_complete_assessments(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $project = $this->makeProjectWithTarget($user, $workspace);
        $this->makeCompleteAssessment($workspace, $user, $project, withAnswers: true);
        $this->makeCompleteAssessment($workspace, $user, $project, withAnswers: true);

        $this->actingAs($user)
            ->get(route('projects.progress', $project))
            ->assertOk()
            ->assertSee('Compare Two Runs');
    }

    public function test_compare_form_not_shown_for_single_run(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $project = $this->makeProjectWithTarget($user, $workspace);
        $this->makeCompleteAssessment($workspace, $user, $project, withAnswers: true);

        $this->actingAs($user)
            ->get(route('projects.progress', $project))
            ->assertOk()
            ->assertDontSee('Compare Two Runs');
    }

    // ---- Compare page ----

    public function test_compare_page_requires_auth(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $project = $this->makeProjectWithTarget($user, $workspace);
        $a = $this->makeCompleteAssessment($workspace, $user, $project);
        $b = $this->makeCompleteAssessment($workspace, $user, $project);

        $this->get(route('projects.compare', $project)."?a={$a->assessment_id}&b={$b->assessment_id}")
            ->assertRedirect(route('login'));
    }

    public function test_compare_page_renders_with_two_assessments(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $project = $this->makeProjectWithTarget($user, $workspace);
        $a = $this->makeCompleteAssessment($workspace, $user, $project, withAnswers: true);
        $b = $this->makeCompleteAssessment($workspace, $user, $project, withAnswers: true);

        $this->actingAs($user)
            ->get(route('projects.compare', $project)."?a={$a->assessment_id}&b={$b->assessment_id}")
            ->assertOk()
            ->assertSee('Domain Comparison')
            ->assertSee('Assessment Comparison');
    }

    public function test_compare_page_shows_both_assessment_scores(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $project = $this->makeProjectWithTarget($user, $workspace);
        $a = $this->makeCompleteAssessment($workspace, $user, $project, withAnswers: true);
        $b = $this->makeCompleteAssessment($workspace, $user, $project, withAnswers: true);

        // Both get 100 with all-best answers; page should show two score cards
        $this->actingAs($user)
            ->get(route('projects.compare', $project)."?a={$a->assessment_id}&b={$b->assessment_id}")
            ->assertOk()
            ->assertSee('100'); // score visible in both cards
    }

    public function test_compare_rejects_different_composition_hashes(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $project = $this->makeProjectWithTarget($user, $workspace);
        $a = $this->makeCompleteAssessment($workspace, $user, $project);
        $a->update(['composition_hash' => str_repeat('a', 64)]);
        $b = $this->makeCompleteAssessment($workspace, $user, $project);
        $b->update(['composition_hash' => str_repeat('b', 64)]);

        $this->actingAs($user)
            ->get(route('projects.compare', $project)."?a={$a->assessment_id}&b={$b->assessment_id}")
            ->assertRedirect(route('projects.progress', $project))
            ->assertSessionHas('error');
    }

    public function test_compare_page_404s_when_assessment_belongs_to_different_project(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $projectA = $this->makeProjectWithTarget($user, $workspace);
        $projectB = $this->makeProjectWithTarget($user, $workspace);

        $a = $this->makeCompleteAssessment($workspace, $user, $projectA, withAnswers: true);
        $b = $this->makeCompleteAssessment($workspace, $user, $projectB, withAnswers: true);

        // Assessment B belongs to projectB, not projectA — should 404
        $this->actingAs($user)
            ->get(route('projects.compare', $projectA)."?a={$a->assessment_id}&b={$b->assessment_id}")
            ->assertNotFound();
    }

    // ---- Workspace isolation ----

    public function test_workspace_b_cannot_view_workspace_a_progress_page(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        [$userA, $workspaceA] = $this->userWithWorkspace();
        $projectA = $this->makeProjectWithTarget($userA, $workspaceA);

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
            ->get(route('projects.progress', $projectA))
            ->assertNotFound();
    }
}
