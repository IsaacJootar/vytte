<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectSearchTest extends TestCase
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

    public function test_projects_index_renders(): void
    {
        [$user] = $this->userWithWorkspace();

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk();
    }

    public function test_search_returns_matching_project(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        Project::factory()->create([
            'workspace_id' => $workspace->workspace_id,
            'name' => 'Kano Health Survey',
        ]);
        Project::factory()->create([
            'workspace_id' => $workspace->workspace_id,
            'name' => 'Lagos Water Assessment',
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.index', ['search' => 'Kano']));

        $response->assertOk()
            ->assertSee('Kano Health Survey')
            ->assertDontSee('Lagos Water Assessment');
    }

    public function test_search_is_case_insensitive(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        Project::factory()->create([
            'workspace_id' => $workspace->workspace_id,
            'name' => 'Abuja School Project',
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.index', ['search' => 'abuja']));

        $response->assertOk()->assertSee('Abuja School Project');
    }

    public function test_empty_search_returns_all_projects(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        Project::factory()->create(['workspace_id' => $workspace->workspace_id, 'name' => 'Project Alpha']);
        Project::factory()->create(['workspace_id' => $workspace->workspace_id, 'name' => 'Project Beta']);

        $response = $this->actingAs($user)
            ->get(route('projects.index'));

        $response->assertOk()
            ->assertSee('Project Alpha')
            ->assertSee('Project Beta');
    }

    public function test_search_is_scoped_to_current_workspace(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        // Other workspace project with the same name
        $otherWorkspace = Workspace::factory()->create();
        Project::factory()->create([
            'workspace_id' => $otherWorkspace->workspace_id,
            'name' => 'Shared Name Project',
        ]);

        // Current workspace project
        Project::factory()->create([
            'workspace_id' => $workspace->workspace_id,
            'name' => 'My Own Project',
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.index', ['search' => 'Shared']));

        $response->assertOk()
            ->assertDontSee('Shared Name Project');
    }
}
