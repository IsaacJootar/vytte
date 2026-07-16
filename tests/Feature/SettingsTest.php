<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $workspace = Workspace::factory()->create(['name' => 'Test Workspace']);
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);
        app()->instance('current.workspace', $workspace);

        return [$user, $workspace];
    }

    private function addMember(Workspace $workspace, string $role = 'MEMBER'): User
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => $role,
        ]);

        return $user;
    }

    // ---- Auth gate ----

    public function test_settings_page_requires_auth(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    // ---- Page renders ----

    public function test_settings_page_renders_with_profile_and_workspace_sections(): void
    {
        [$user] = $this->makeOwner();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Profile')
            ->assertSee('Password')
            ->assertSee('Workspace')
            ->assertSee('Danger zone');
    }

    public function test_member_does_not_see_workspace_section(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $member = $this->addMember($workspace, 'MEMBER');
        app()->instance('current.workspace', $workspace);

        $response = $this->actingAs($member)->get(route('profile.edit'));
        $response->assertOk();

        // Workspace settings section heading not shown; Danger zone is always visible
        $this->assertStringNotContainsString('Save workspace', $response->getContent());
    }

    // ---- Profile update ----

    public function test_profile_name_and_email_can_be_updated(): void
    {
        [$user] = $this->makeOwner();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'profile-updated');

        $user->refresh();
        $this->assertEquals('New Name', $user->name);
        $this->assertEquals('new@example.com', $user->email);
    }

    public function test_profile_update_requires_valid_email(): void
    {
        [$user] = $this->makeOwner();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Name',
                'email' => 'not-an-email',
            ])
            ->assertSessionHasErrors('email');
    }

    // ---- Password change ----

    public function test_password_can_be_changed_with_correct_current_password(): void
    {
        [$user] = $this->makeOwner();

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'new-password-123!',
                'password_confirmation' => 'new-password-123!',
            ])
            ->assertSessionHas('status', 'password-updated');

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123!', $user->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        [$user] = $this->makeOwner();

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123!',
                'password_confirmation' => 'new-password-123!',
            ])
            ->assertSessionHasErrors('current_password', errorBag: 'updatePassword');
    }

    // ---- Workspace settings ----

    public function test_owner_can_update_workspace_name(): void
    {
        [$user, $workspace] = $this->makeOwner();

        $this->actingAs($user)
            ->patch(route('settings.workspace.update'), [
                'name' => 'Renamed Workspace',
                'timezone' => '',
            ])
            ->assertSessionHas('status', 'workspace-updated');

        $workspace->refresh();
        $this->assertEquals('Renamed Workspace', $workspace->name);
    }

    public function test_admin_can_update_workspace_name(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $admin = $this->addMember($workspace, 'ADMIN');
        app()->instance('current.workspace', $workspace);

        $this->actingAs($admin)
            ->patch(route('settings.workspace.update'), [
                'name' => 'Admin Renamed',
                'timezone' => '',
            ])
            ->assertSessionHas('status', 'workspace-updated');

        $workspace->refresh();
        $this->assertEquals('Admin Renamed', $workspace->name);
    }

    public function test_member_cannot_update_workspace_settings(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $member = $this->addMember($workspace, 'MEMBER');
        app()->instance('current.workspace', $workspace);

        $this->actingAs($member)
            ->patch(route('settings.workspace.update'), [
                'name' => 'Hacked Name',
                'timezone' => '',
            ])
            ->assertForbidden();

        $workspace->refresh();
        $this->assertEquals('Test Workspace', $workspace->name);
    }

    public function test_workspace_timezone_is_stored_in_settings(): void
    {
        [$user, $workspace] = $this->makeOwner();

        $this->actingAs($user)
            ->patch(route('settings.workspace.update'), [
                'name' => 'Test Workspace',
                'timezone' => 'Africa/Lagos',
            ]);

        $workspace->refresh();
        $this->assertEquals('Africa/Lagos', $workspace->settings['timezone']);
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        [$user] = $this->makeOwner();

        $this->actingAs($user)
            ->patch(route('settings.workspace.update'), [
                'name' => 'Test Workspace',
                'timezone' => 'Not/A/Timezone',
            ])
            ->assertSessionHasErrors('timezone');
    }

    // ---- Delete workspace ----

    public function test_owner_can_delete_workspace_with_correct_confirmation(): void
    {
        [$user, $workspace] = $this->makeOwner();
        $workspaceId = $workspace->workspace_id;

        $this->actingAs($user)
            ->delete(route('settings.workspace.destroy'), [
                'confirm_name' => 'Test Workspace',
                'password' => 'password',
            ])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('workspaces', ['workspace_id' => $workspaceId]);
    }

    public function test_wrong_workspace_name_confirmation_rejects_deletion(): void
    {
        [$user, $workspace] = $this->makeOwner();

        $this->actingAs($user)
            ->delete(route('settings.workspace.destroy'), [
                'confirm_name' => 'Wrong Name',
                'password' => 'password',
            ])
            ->assertSessionHasErrors('confirm_name', errorBag: 'workspaceDeletion');

        $this->assertDatabaseHas('workspaces', ['workspace_id' => $workspace->workspace_id]);
    }

    public function test_wrong_password_rejects_workspace_deletion(): void
    {
        [$user, $workspace] = $this->makeOwner();

        $this->actingAs($user)
            ->delete(route('settings.workspace.destroy'), [
                'confirm_name' => 'Test Workspace',
                'password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('password', errorBag: 'workspaceDeletion');

        $this->assertDatabaseHas('workspaces', ['workspace_id' => $workspace->workspace_id]);
    }

    public function test_admin_cannot_delete_workspace(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $admin = $this->addMember($workspace, 'ADMIN');
        app()->instance('current.workspace', $workspace);

        $this->actingAs($admin)
            ->delete(route('settings.workspace.destroy'), [
                'confirm_name' => 'Test Workspace',
                'password' => 'password',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('workspaces', ['workspace_id' => $workspace->workspace_id]);
    }

    // ---- Delete account ----

    public function test_user_can_delete_own_account_with_correct_password(): void
    {
        [$user] = $this->makeOwner();
        $userId = $user->user_id;

        $this->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['user_id' => $userId]);
    }

    public function test_wrong_password_rejects_account_deletion(): void
    {
        [$user] = $this->makeOwner();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('password', errorBag: 'userDeletion');

        $this->assertDatabaseHas('users', ['user_id' => $user->user_id]);
    }

    // ---- Workspace isolation ----

    public function test_workspace_b_cannot_update_workspace_a_settings(): void
    {
        // Workspace A
        [$ownerA, $workspaceA] = $this->makeOwner();

        // Workspace B owner
        $userB = User::factory()->create(['password' => Hash::make('password')]);
        $workspaceB = Workspace::factory()->create(['name' => 'Workspace B']);
        WorkspaceMember::create([
            'workspace_id' => $workspaceB->workspace_id,
            'user_id' => $userB->user_id,
            'role' => 'OWNER',
        ]);
        $userB->update(['active_workspace_id' => $workspaceB->workspace_id]);
        app()->instance('current.workspace', $workspaceB);

        $this->actingAs($userB)
            ->patch(route('settings.workspace.update'), [
                'name' => 'Hijacked',
                'timezone' => '',
            ])
            ->assertSessionHas('status', 'workspace-updated');

        // Workspace A name is untouched
        $workspaceA->refresh();
        $this->assertEquals('Test Workspace', $workspaceA->name);

        // Workspace B name was updated (correct behaviour)
        $workspaceB->refresh();
        $this->assertEquals('Hijacked', $workspaceB->name);
    }
}
