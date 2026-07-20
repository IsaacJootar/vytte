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

    // ─── Invites must work without email ─────────────────────────

    private function makeInvite(Workspace $workspace, User $owner, string $email = 'invitee@example.com'): WorkspaceInvitation
    {
        return WorkspaceInvitation::create([
            'workspace_id' => $workspace->workspace_id,
            'email' => $email,
            'role' => 'MEMBER',
            'token' => Str::random(64),
            'invited_by' => $owner->user_id,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function test_a_pending_invite_keeps_its_link_on_the_page(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $invite = $this->makeInvite($workspace, $owner);

        // Email is off for beta, so a link only shown once at creation would be
        // unrecoverable. It has to survive a plain page load.
        $this->actingAs($owner)
            ->get(route('team.index'))
            ->assertOk()
            ->assertSee(route('invitations.show', $invite->token));
    }

    public function test_a_pending_invite_offers_a_whatsapp_share(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $this->makeInvite($workspace, $owner);

        $this->actingAs($owner)
            ->get(route('team.index'))
            ->assertOk()
            ->assertSee('https://wa.me/?text=', false)
            ->assertSee('WhatsApp');
    }

    public function test_admin_can_issue_a_new_link_for_an_invite(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $invite = $this->makeInvite($workspace, $owner);
        $originalToken = $invite->token;

        $this->actingAs($owner)
            ->patch(route('team.invite.refresh', $invite->id))
            ->assertSessionHas('success');

        $invite->refresh();
        $this->assertNotSame($originalToken, $invite->token);
    }

    public function test_the_previous_link_stops_working_once_a_new_one_is_issued(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $invite = $this->makeInvite($workspace, $owner);
        $originalToken = $invite->token;

        $this->actingAs($owner)->patch(route('team.invite.refresh', $invite->id));

        // Assert as the person receiving the invite, not as the owner — the owner is
        // already a member, so they would be redirected rather than shown the invite.
        auth()->logout();

        // A link shared with the wrong person has to be revocable.
        $this->get(route('invitations.show', $originalToken))->assertNotFound();
        $this->get(route('invitations.show', $invite->fresh()->token))->assertOk();
    }

    public function test_a_member_cannot_issue_a_new_invite_link(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $invite = $this->makeInvite($workspace, $owner);
        $member = $this->addMember($workspace);

        $this->actingAs($member)
            ->patch(route('team.invite.refresh', $invite->id))
            ->assertForbidden();
    }

    public function test_an_invite_from_another_workspace_cannot_be_refreshed(): void
    {
        [$ownerA, $workspaceA] = $this->makeOwner();
        $invite = $this->makeInvite($workspaceA, $ownerA);

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
            ->patch(route('team.invite.refresh', $invite->id))
            ->assertNotFound();
    }

    public function test_a_new_person_joins_the_workspace_after_registering_from_an_invite(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $invite = $this->makeInvite($workspace, $owner, 'newcomer@example.com');

        // Viewing the invitation as a guest is what records where they were headed.
        $this->get(route('invitations.show', $invite->token))->assertOk();

        $this->post(route('register'), [
            'name' => 'New Comer',
            'email' => 'newcomer@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertRedirect(route('invitations.accept', $invite->token));

        // Registration used to end on the dashboard, which lost the invitation silently.
        $this->get(route('invitations.accept', $invite->token));

        $newUser = User::where('email', 'newcomer@example.com')->firstOrFail();
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $newUser->user_id,
        ]);
        $this->assertNotNull($invite->fresh()->accepted_at);
    }

    public function test_an_existing_person_returns_to_the_invite_after_signing_in(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $invite = $this->makeInvite($workspace, $owner, 'existing@example.com');

        $existing = User::factory()->create([
            'email' => 'existing@example.com',
            'password' => bcrypt('correct-horse-battery'),
        ]);

        $this->get(route('invitations.show', $invite->token))->assertOk();

        $this->post(route('login'), [
            'email' => $existing->email,
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('invitations.accept', $invite->token));
    }

    public function test_signing_in_without_an_invite_still_lands_on_the_dashboard(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-horse-battery')]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_a_cancelled_invite_link_stops_working(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $invite = $this->makeInvite($workspace, $owner);
        $token = $invite->token;

        $this->actingAs($owner)->delete(route('team.invite.cancel', $invite->id));

        $this->get(route('invitations.show', $token))->assertNotFound();
    }
}
