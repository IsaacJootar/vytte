<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);

        $user->update(['active_workspace_id' => $workspace->workspace_id]);

        return [$user, $workspace];
    }

    public function test_registration_creates_workspace_and_owner_membership(): void
    {
        $this->post('/register', [
            'name' => 'Alice Test',
            'email' => 'alice@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'alice@example.com')->first();

        $this->assertNotNull($user->active_workspace_id);

        $workspace = Workspace::where('workspace_id', $user->active_workspace_id)->first();
        $this->assertNotNull($workspace);

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
    }

    public function test_project_scope_shows_only_current_workspace_projects(): void
    {
        [$userA, $workspaceA] = $this->createUserWithWorkspace();
        [$userB, $workspaceB] = $this->createUserWithWorkspace();

        app()->instance('current.workspace', $workspaceA);
        $projectA = Project::create([
            'workspace_id' => $workspaceA->workspace_id,
            'owner_user_id' => $userA->user_id,
            'name' => 'Alpha Project',
        ]);

        app()->instance('current.workspace', $workspaceB);
        $projectB = Project::create([
            'workspace_id' => $workspaceB->workspace_id,
            'owner_user_id' => $userB->user_id,
            'name' => 'Beta Project',
        ]);

        app()->instance('current.workspace', $workspaceA);
        $projectsForA = Project::all();
        $this->assertCount(1, $projectsForA);
        $this->assertEquals($projectA->project_id, $projectsForA->first()->project_id);

        app()->instance('current.workspace', $workspaceB);
        $projectsForB = Project::all();
        $this->assertCount(1, $projectsForB);
        $this->assertEquals($projectB->project_id, $projectsForB->first()->project_id);
    }

    public function test_workspace_a_cannot_find_workspace_b_project_by_id(): void
    {
        [$userA, $workspaceA] = $this->createUserWithWorkspace();
        [$userB, $workspaceB] = $this->createUserWithWorkspace();

        app()->instance('current.workspace', $workspaceB);
        $projectB = Project::create([
            'workspace_id' => $workspaceB->workspace_id,
            'owner_user_id' => $userB->user_id,
            'name' => 'Secret Project',
        ]);

        app()->instance('current.workspace', $workspaceA);
        $found = Project::find($projectB->project_id);

        $this->assertNull($found, 'Workspace A must not find a project belonging to Workspace B');
    }
}
