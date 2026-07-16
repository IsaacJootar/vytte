<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentModule;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentTier;
use App\Models\Project;
use App\Models\Response;
use App\Models\Target;
use App\Models\TargetCategory;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\ScoringService;
use Database\Seeders\HivawQuestionsSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    private function createScoredAssessment(Workspace $workspace, User $user, bool $bestAnswers = true): Assessment
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        $categoryId = TargetCategory::where('category_code', 'GENERAL_COMMUNITY')->value('category_id');
        $project = Project::create(['name' => 'Dashboard Test Project', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Test Community',
            'category_id' => $categoryId,
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $tier = AssessmentTier::where('tier_code', 'TIER_1')->first();
        $module = AssessmentModule::where('module_code', 'HIVAW')->first();

        $assessment = Assessment::create([
            'target_id' => $target->target_id,
            'project_id' => $project->project_id,
            'assessment_tier_id' => $tier->assessment_tier_id,
            'status' => 'COMPLETE',
            'publish_status' => 'DRAFT',
            'assessor_name' => 'Tester',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        AssessmentModuleScope::create([
            'assessment_id' => $assessment->assessment_id,
            'module_id' => $module->module_id,
            'in_scope' => true,
            'is_category_default' => true,
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);

        $questions = DB::table('questions')
            ->where('module_id', $module->module_id)
            ->where('is_scored', true)
            ->pluck('question_id');

        foreach ($questions as $qId) {
            $optionId = $bestAnswers
                ? DB::table('question_options')->where('question_id', $qId)->orderByDesc('score_weight')->value('option_id')
                : DB::table('question_options')->where('question_id', $qId)->orderBy('score_weight')->value('option_id');

            Response::updateOrCreate(
                ['assessment_id' => $assessment->assessment_id, 'question_id' => $qId, 'respondent_id' => null],
                ['value_option_id' => $optionId, 'answered_at' => now()]
            );
        }

        app(ScoringService::class)->calculate($assessment);

        return $assessment;
    }

    // ---- Auth gate ----

    public function test_dashboard_requires_auth(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    // ---- Empty state ----

    public function test_dashboard_renders_with_no_data(): void
    {
        [$user] = $this->userWithWorkspace();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('New Project');
    }

    // ---- Stats accuracy ----

    public function test_dashboard_shows_correct_active_project_count(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        $this->seed(ReferenceDataSeeder::class);
        $categoryId = TargetCategory::where('category_code', 'GENERAL_COMMUNITY')->value('category_id');

        Project::create(['name' => 'Project A', 'owner_user_id' => $user->user_id]);
        Project::create(['name' => 'Project B', 'owner_user_id' => $user->user_id]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertViewHas('activeProjectCount', 2);
    }

    public function test_dashboard_shows_correct_assessment_count(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        $this->createScoredAssessment($workspace, $user);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertViewHas('totalAssessments', 1);
    }

    public function test_dashboard_shows_correct_avg_score(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        // Best answers → score = 100.0
        $this->createScoredAssessment($workspace, $user, bestAnswers: true);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        $avgScore = $response->viewData('avgScore');
        $this->assertEquals(100.0, (float) $avgScore);
    }

    public function test_dashboard_avg_score_is_null_with_no_scored_assessments(): void
    {
        [$user] = $this->userWithWorkspace();

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertViewHas('avgScore', null);
    }

    // ---- Score distribution ----

    public function test_dashboard_score_distribution_counts_correctly(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        // Best answers → Strong (100)
        $this->createScoredAssessment($workspace, $user, bestAnswers: true);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        $distribution = $response->viewData('distribution');
        $this->assertEquals(1, $distribution['strong']);
        $this->assertEquals(0, $distribution['moderate']);
        $this->assertEquals(0, $distribution['weak']);
    }

    // ---- Recent lists ----

    public function test_dashboard_recent_projects_list_shows_projects(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        $this->seed(ReferenceDataSeeder::class);
        Project::create(['name' => 'Alpha Project', 'owner_user_id' => $user->user_id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Alpha Project');
    }

    public function test_dashboard_recent_assessments_links_to_results(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        $assessment = $this->createScoredAssessment($workspace, $user);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('assessments.results', $assessment));
    }

    public function test_recent_projects_capped_at_five(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        $this->seed(ReferenceDataSeeder::class);
        foreach (range(1, 7) as $i) {
            Project::create(['name' => "Project $i", 'owner_user_id' => $user->user_id]);
        }

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        $recentProjects = $response->viewData('recentProjects');
        $this->assertLessThanOrEqual(5, $recentProjects->count());
    }

    // ---- Workspace isolation ----

    public function test_workspace_b_sees_zero_stats_for_workspace_a_data(): void
    {
        // Workspace A: has projects and scored assessments
        [$userA, $workspaceA] = $this->userWithWorkspace();
        $this->createScoredAssessment($workspaceA, $userA);

        // Workspace B: fresh, no data
        $userB = User::factory()->create();
        $workspaceB = Workspace::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspaceB->workspace_id,
            'user_id' => $userB->user_id,
            'role' => 'OWNER',
        ]);
        $userB->update(['active_workspace_id' => $workspaceB->workspace_id]);
        app()->instance('current.workspace', $workspaceB);

        $response = $this->actingAs($userB)->get(route('dashboard'));
        $response->assertOk();

        $this->assertEquals(0, $response->viewData('activeProjectCount'));
        $this->assertEquals(0, $response->viewData('totalAssessments'));
        $this->assertNull($response->viewData('avgScore'));
        $this->assertTrue($response->viewData('recentProjects')->isEmpty());
        $this->assertTrue($response->viewData('recentAssessments')->isEmpty());
    }
}
