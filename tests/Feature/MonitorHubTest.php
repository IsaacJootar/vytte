<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\Project;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AssessmentCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitorHubTest extends TestCase
{
    use RefreshDatabase;

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->workspace_id, 'user_id' => $user->user_id, 'role' => 'OWNER']);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);
        app()->instance('current.workspace', $workspace);

        return [$user, $workspace];
    }

    private function collectingAssessment(User $user, Workspace $workspace): Assessment
    {
        $project = Project::create(['name' => 'Monitor Project', 'owner_user_id' => $user->user_id]);
        $target = Target::create(['target_type_code' => 'COMMUNITY', 'name' => 'Monitor Target', 'owner_workspace_id' => $workspace->workspace_id]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release);
        $assessment->update(['publish_status' => Assessment::PUBLISH_PUBLISHED, 'published_at' => now()]);

        return $assessment;
    }

    public function test_hub_requires_auth(): void
    {
        $this->get(route('monitor.index'))->assertRedirect(route('login'));
    }

    public function test_hub_shows_empty_state_without_collecting_assessments(): void
    {
        [$user] = $this->userWithWorkspace();

        $this->actingAs($user)->get(route('monitor.index'))
            ->assertOk()
            ->assertSee('Nothing collecting right now');
    }

    public function test_hub_lists_collecting_assessments(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $this->collectingAssessment($user, $workspace);

        $this->actingAs($user)->get(route('monitor.index'))
            ->assertOk()
            ->assertSee('Monitor Target');
    }
}
