<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Database\Seeders\PlanFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanFeatureSeeder::class);
    }

    private function makeOwner(): array
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

    private function addMember(Workspace $workspace, string $role = 'MEMBER'): User
    {
        $user = User::factory()->create();
        $user->update(['active_workspace_id' => $workspace->workspace_id]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => $role,
        ]);

        return $user;
    }

    // ---- Auth gate ----

    public function test_team_page_requires_auth(): void
    {
        $this->get(route('team.index'))->assertRedirect(route('login'));
    }

    // ---- Index renders ----

    public function test_team_page_renders_for_owner(): void
    {
        [$user] = $this->makeOwner();

        $this->actingAs($user)
            ->get(route('team.index'))
            ->assertOk()
            ->assertSee('Team')
            ->assertSee($user->name);
    }

    public function test_team_page_shows_invite_button_for_admin(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $admin = $this->addMember($workspace, 'ADMIN');
        app()->instance('current.workspace', $workspace);

        $this->actingAs($admin)
            ->get(route('team.index'))
            ->assertOk()
            ->assertSee('Invite Member');
    }

    public function test_team_page_does_not_show_invite_button_for_member(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $member = $this->addMember($workspace, 'MEMBER');
        app()->instance('current.workspace', $workspace);

        $this->actingAs($member)
            ->get(route('team.index'))
            ->assertOk()
            ->assertDontSee('Invite Member');
    }

    // ---- Invite flow ----

    public function test_owner_can_create_invite(): void
    {
        [$user, $workspace] = $this->makeOwner();

        $this->actingAs($user)
            ->post(route('team.invite'), [
                'email' => 'newmember@example.com',
                'role' => 'MEMBER',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $workspace->workspace_id,
            'email' => 'newmember@example.com',
            'role' => 'MEMBER',
        ]);
    }

    public function test_invite_response_includes_invite_link(): void
    {
        [$user] = $this->makeOwner();

        $response = $this->actingAs($user)
            ->post(route('team.invite'), [
                'email' => 'link@example.com',
                'role' => 'MEMBER',
            ]);

        $response->assertSessionHas('invite_link');
    }

    public function test_duplicate_pending_invite_is_blocked(): void
    {
        [$user, $workspace] = $this->makeOwner();

        // First invite
        $this->actingAs($user)->post(route('team.invite'), [
            'email' => 'dup@example.com',
            'role' => 'MEMBER',
        ]);

        // Second invite for same email
        $response = $this->actingAs($user)->post(route('team.invite'), [
            'email' => 'dup@example.com',
            'role' => 'MEMBER',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(1, WorkspaceInvitation::where('email', 'dup@example.com')->count());
    }

    public function test_invite_blocked_when_user_already_member(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $existing = $this->addMember($workspace, 'MEMBER');
        app()->instance('current.workspace', $workspace);

        $response = $this->actingAs($owner)->post(route('team.invite'), [
            'email' => $existing->email,
            'role' => 'MEMBER',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(0, WorkspaceInvitation::count());
    }

    public function test_member_cannot_send_invite(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $member = $this->addMember($workspace, 'MEMBER');
        app()->instance('current.workspace', $workspace);

        $this->actingAs($member)
            ->post(route('team.invite'), [
                'email' => 'someone@example.com',
                'role' => 'MEMBER',
            ])
            ->assertForbidden();
    }

    // ---- Role management ----

    public function test_owner_can_change_member_role(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $member = $this->addMember($workspace, 'MEMBER');
        app()->instance('current.workspace', $workspace);

        $this->actingAs($owner)
            ->patch(route('team.role', $member->user_id), ['role' => 'ADMIN'])
            ->assertRedirect();

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $member->user_id,
            'role' => 'ADMIN',
        ]);
    }

    public function test_owner_cannot_change_own_role(): void
    {
        [$owner] = $this->makeOwner();

        $response = $this->actingAs($owner)
            ->patch(route('team.role', $owner->user_id), ['role' => 'MEMBER']);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('workspace_members', [
            'user_id' => $owner->user_id,
            'role' => 'OWNER',
        ]);
    }

    public function test_admin_cannot_change_roles(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $admin = $this->addMember($workspace, 'ADMIN');
        $member = $this->addMember($workspace, 'MEMBER');
        app()->instance('current.workspace', $workspace);

        $this->actingAs($admin)
            ->patch(route('team.role', $member->user_id), ['role' => 'ADMIN'])
            ->assertForbidden();
    }

    // ---- Remove member ----

    public function test_owner_can_remove_member(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $member = $this->addMember($workspace, 'MEMBER');
        app()->instance('current.workspace', $workspace);

        $this->actingAs($owner)
            ->delete(route('team.destroy', $member->user_id))
            ->assertRedirect();

        $this->assertDatabaseMissing('workspace_members', [
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $member->user_id,
        ]);
    }

    public function test_owner_cannot_remove_self(): void
    {
        [$owner, $workspace] = $this->makeOwner();

        $response = $this->actingAs($owner)
            ->delete(route('team.destroy', $owner->user_id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('workspace_members', [
            'user_id' => $owner->user_id,
        ]);
    }

    public function test_admin_cannot_remove_another_admin(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $admin1 = $this->addMember($workspace, 'ADMIN');
        $admin2 = $this->addMember($workspace, 'ADMIN');
        app()->instance('current.workspace', $workspace);

        $response = $this->actingAs($admin1)
            ->delete(route('team.destroy', $admin2->user_id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('workspace_members', [
            'user_id' => $admin2->user_id,
        ]);
    }

    public function test_member_cannot_remove_anyone(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $member = $this->addMember($workspace, 'MEMBER');
        $other = $this->addMember($workspace, 'MEMBER');
        app()->instance('current.workspace', $workspace);

        $this->actingAs($member)
            ->delete(route('team.destroy', $other->user_id))
            ->assertForbidden();
    }

    // ---- Cancel invite ----

    public function test_admin_can_cancel_invite(): void
    {
        [$owner, $workspace] = $this->makeOwner();

        $invite = WorkspaceInvitation::create([
            'workspace_id' => $workspace->workspace_id,
            'email' => 'cancel@example.com',
            'role' => 'MEMBER',
            'token' => Str::random(64),
            'invited_by' => $owner->user_id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($owner)
            ->delete(route('team.invite.cancel', $invite->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('workspace_invitations', ['id' => $invite->id]);
    }

    // ---- Invitation show page ----

    public function test_invitation_show_page_renders_for_guest(): void
    {
        [$owner, $workspace] = $this->makeOwner();

        $invite = WorkspaceInvitation::create([
            'workspace_id' => $workspace->workspace_id,
            'email' => 'guest@example.com',
            'role' => 'MEMBER',
            'token' => Str::random(64),
            'invited_by' => $owner->user_id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->get(route('invitations.show', $invite->token))
            ->assertOk()
            ->assertSee($workspace->name)
            ->assertSee('Sign in to accept');
    }

    public function test_expired_invitation_shows_expired_page(): void
    {
        [$owner, $workspace] = $this->makeOwner();

        $invite = WorkspaceInvitation::create([
            'workspace_id' => $workspace->workspace_id,
            'email' => 'old@example.com',
            'role' => 'MEMBER',
            'token' => Str::random(64),
            'invited_by' => $owner->user_id,
            'expires_at' => now()->subDay(),
        ]);

        $this->get(route('invitations.show', $invite->token))
            ->assertOk()
            ->assertSee('expired');
    }

    // ---- Accept invite ----

    public function test_accept_invite_adds_user_to_workspace(): void
    {
        [$owner, $workspace] = $this->makeOwner();

        $invite = WorkspaceInvitation::create([
            'workspace_id' => $workspace->workspace_id,
            'email' => 'newbie@example.com',
            'role' => 'MEMBER',
            'token' => Str::random(64),
            'invited_by' => $owner->user_id,
            'expires_at' => now()->addDays(7),
        ]);

        $newUser = User::factory()->create(['email' => 'newbie@example.com']);

        $this->actingAs($newUser)
            ->get(route('invitations.accept', $invite->token))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $newUser->user_id,
            'role' => 'MEMBER',
        ]);

        $invite->refresh();
        $this->assertNotNull($invite->accepted_at);
    }

    public function test_accept_already_accepted_invite_redirects_without_joining(): void
    {
        [$owner, $workspace] = $this->makeOwner();

        $invite = WorkspaceInvitation::create([
            'workspace_id' => $workspace->workspace_id,
            'email' => 'already@example.com',
            'role' => 'MEMBER',
            'token' => Str::random(64),
            'invited_by' => $owner->user_id,
            'accepted_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('invitations.accept', $invite->token))
            ->assertRedirect(route('dashboard'));

        // Not added (invite was already accepted)
        $this->assertDatabaseMissing('workspace_members', [
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
        ]);
    }

    // ---- Workspace isolation ----

    public function test_cancel_invite_for_other_workspace_returns_404(): void
    {
        [$ownerA, $workspaceA] = $this->makeOwner();

        $inviteA = WorkspaceInvitation::create([
            'workspace_id' => $workspaceA->workspace_id,
            'email' => 'isolated@example.com',
            'role' => 'MEMBER',
            'token' => Str::random(64),
            'invited_by' => $ownerA->user_id,
            'expires_at' => now()->addDays(7),
        ]);

        // Workspace B owner tries to cancel workspace A's invite
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
            ->delete(route('team.invite.cancel', $inviteA->id))
            ->assertNotFound();

        $this->assertDatabaseHas('workspace_invitations', ['id' => $inviteA->id]);
    }
}
