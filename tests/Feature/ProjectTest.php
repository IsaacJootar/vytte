<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Target;
use App\Models\TargetCategory;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
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

    private function seedReferenceData(): void
    {
        $this->seed(ReferenceDataSeeder::class);
    }

    // ---- Index ----

    public function test_projects_index_requires_auth(): void
    {
        $this->get(route('projects.index'))->assertRedirect(route('login'));
    }

    public function test_projects_index_renders_for_authed_user(): void
    {
        [$user] = $this->userWithWorkspace();

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Projects');
    }

    public function test_projects_index_shows_empty_state_when_no_projects(): void
    {
        [$user] = $this->userWithWorkspace();

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('No projects yet');
    }

    // ---- Create / Store ----

    public function test_create_form_renders(): void
    {
        [$user] = $this->userWithWorkspace();
        $this->seedReferenceData();

        $this->actingAs($user)
            ->get(route('projects.create'))
            ->assertOk()
            ->assertSee('New Project');
    }

    public function test_store_creates_project_and_target_in_same_workspace(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $this->seedReferenceData();

        $categoryId = TargetCategory::where('category_code', 'PHC')->value('category_id');

        $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Q2 Assessment',
            'description' => 'Lagos PHC assessment.',
            'target_name' => 'Ikeja PHC',
            'target_type_code' => 'HEALTH_FACILITY',
            'category_id' => $categoryId,
            'state' => 'Lagos',
            'lga' => 'Ikeja',
        ])->assertRedirect();

        $project = Project::first();
        $this->assertNotNull($project);
        $this->assertEquals('Q2 Assessment', $project->name);
        $this->assertEquals($workspace->workspace_id, $project->workspace_id);
        $this->assertEquals($user->user_id, $project->owner_user_id);

        $target = $project->targets->first();
        $this->assertNotNull($target);
        $this->assertEquals('Ikeja PHC', $target->name);
        $this->assertEquals('HEALTH_FACILITY', $target->target_type_code);
        $this->assertEquals('Lagos', $target->state);
        $this->assertEquals($workspace->workspace_id, $target->owner_workspace_id);
    }

    public function test_store_validates_required_fields(): void
    {
        [$user] = $this->userWithWorkspace();
        $this->seedReferenceData();

        $this->actingAs($user)
            ->post(route('projects.store'), [])
            ->assertSessionHasErrors(['name', 'target_name', 'target_type_code', 'category_id']);
    }

    public function test_store_rejects_invalid_target_type(): void
    {
        [$user] = $this->userWithWorkspace();
        $this->seedReferenceData();

        $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Test',
            'target_name' => 'Test Facility',
            'target_type_code' => 'INVALID_TYPE',
            'category_id' => 1,
        ])->assertSessionHasErrors(['target_type_code']);
    }

    // ---- Show ----

    public function test_show_renders_project_detail(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $this->seedReferenceData();

        $categoryId = TargetCategory::where('category_code', 'PHC')->value('category_id');
        $project = Project::create([
            'name' => 'My Project',
            'owner_user_id' => $user->user_id,
        ]);
        $target = Target::create([
            'target_type_code' => 'HEALTH_FACILITY',
            'name' => 'Lagos PHC',
            'category_id' => $categoryId,
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('My Project')
            ->assertSee('Lagos PHC');
    }

    // ---- Edit / Update ----

    public function test_edit_form_renders(): void
    {
        [$user] = $this->userWithWorkspace();

        $project = Project::create([
            'name' => 'Edit Me',
            'owner_user_id' => $user->user_id,
        ]);

        $this->actingAs($user)
            ->get(route('projects.edit', $project))
            ->assertOk()
            ->assertSee('Edit Me');
    }

    public function test_update_changes_project_name(): void
    {
        [$user] = $this->userWithWorkspace();

        $project = Project::create([
            'name' => 'Old Name',
            'owner_user_id' => $user->user_id,
        ]);

        $this->actingAs($user)
            ->patch(route('projects.update', $project), ['name' => 'New Name'])
            ->assertRedirect(route('projects.show', $project));

        $this->assertEquals('New Name', $project->fresh()->name);
    }

    // ---- Archive ----

    public function test_archive_toggles_project_status(): void
    {
        [$user] = $this->userWithWorkspace();

        $project = Project::create([
            'name' => 'Active Project',
            'owner_user_id' => $user->user_id,
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($user)
            ->patch(route('projects.archive', $project))
            ->assertRedirect();

        $this->assertEquals('ARCHIVED', $project->fresh()->status);

        $this->actingAs($user)
            ->patch(route('projects.archive', $project))
            ->assertRedirect();

        $this->assertEquals('ACTIVE', $project->fresh()->status);
    }

    // ---- Workspace isolation ----

    public function test_workspace_b_cannot_access_workspace_a_project(): void
    {
        [$userA, $workspaceA] = $this->userWithWorkspace();

        $projectA = Project::create([
            'name' => 'Workspace A Project',
            'owner_user_id' => $userA->user_id,
        ]);

        // Create a second user in a different workspace
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
            ->get(route('projects.show', $projectA->project_id))
            ->assertNotFound();
    }

    public function test_project_list_only_shows_current_workspace_projects(): void
    {
        [$userA, $workspaceA] = $this->userWithWorkspace();

        Project::create(['name' => 'Project Alpha', 'owner_user_id' => $userA->user_id]);

        $userB = User::factory()->create();
        $workspaceB = Workspace::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspaceB->workspace_id,
            'user_id' => $userB->user_id,
            'role' => 'OWNER',
        ]);
        $userB->update(['active_workspace_id' => $workspaceB->workspace_id]);
        app()->instance('current.workspace', $workspaceB);

        Project::create(['name' => 'Project Beta', 'owner_user_id' => $userB->user_id]);

        // User B should only see their own project
        $this->actingAs($userB)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Project Beta')
            ->assertDontSee('Project Alpha');
    }
}
