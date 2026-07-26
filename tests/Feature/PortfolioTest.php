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
use Database\Seeders\PlanFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioTest extends TestCase
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

    private function completedProject(User $user, Workspace $workspace, string $name): Project
    {
        $project = Project::create(['name' => $name, 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => $name.' Target',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release);
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
        app(ScoringService::class)->calculate($assessment);
        $assessment->update(['status' => Assessment::STATUS_COMPLETE, 'completed_at' => now()]);

        return $project;
    }

    public function test_portfolio_requires_auth(): void
    {
        $this->get(route('portfolio.index'))->assertRedirect(route('login'));
    }

    public function test_portfolio_shows_empty_state_without_completed_assessments(): void
    {
        [$user] = $this->userWithWorkspace();

        $this->actingAs($user)->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Nothing to harmonise yet');
    }

    public function test_portfolio_harmonises_completed_assessments(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $this->completedProject($user, $workspace, 'Alpha Clinic');
        $this->completedProject($user, $workspace, 'Beta Clinic');

        $this->actingAs($user)->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Program score')
            ->assertSee('Targets, ranked')
            ->assertSee('Alpha Clinic')
            ->assertSee('Beta Clinic');
    }

    public function test_portfolio_only_shows_current_workspace(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $this->completedProject($user, $workspace, 'Mine Clinic');

        [$otherUser, $otherWorkspace] = $this->userWithWorkspace();
        $this->completedProject($otherUser, $otherWorkspace, 'Their Clinic');

        // Acting as the second user, only their workspace's target should appear.
        $this->actingAs($otherUser)->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Their Clinic')
            ->assertDontSee('Mine Clinic');
    }
}
