<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\PlatformHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Platform Admin operational management (P2).
 *
 * The emphasis is on controls that must actually take effect. A suspension that records
 * a decision without enforcing it is worse than none, because it reads as protection
 * that is not there.
 */
class PlatformOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);
    }

    private function makeCustomer(string $status = 'ACTIVE'): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['status' => $status]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);

        return [$user, $workspace];
    }

    // ─── Workspace management ────────────────────────────────────

    public function test_admin_can_browse_and_search_workspaces(): void
    {
        Workspace::factory()->create(['name' => 'Kano General Hospital']);
        Workspace::factory()->create(['name' => 'Accra Clinic']);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.workspaces.index', ['search' => 'Kano']))
            ->assertOk()
            ->assertSee('Kano General Hospital')
            ->assertDontSee('Accra Clinic');
    }

    public function test_admin_can_filter_workspaces_by_status(): void
    {
        Workspace::factory()->create(['name' => 'Held Workspace', 'status' => 'SUSPENDED']);
        Workspace::factory()->create(['name' => 'Running Workspace', 'status' => 'ACTIVE']);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.workspaces.index', ['status' => 'SUSPENDED']))
            ->assertOk()
            ->assertSee('Held Workspace')
            ->assertDontSee('Running Workspace');
    }

    public function test_workspace_detail_reports_health_in_plain_language(): void
    {
        [, $workspace] = $this->makeCustomer();

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.workspaces.show', $workspace))
            ->assertOk()
            ->assertSee('What we noticed')
            ->assertSee('Only one person has joined');
    }

    public function test_admin_can_suspend_reactivate_and_close_a_workspace(): void
    {
        [, $workspace] = $this->makeCustomer();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->patch(route('admin.workspaces.status', $workspace), ['status' => 'SUSPENDED'])
            ->assertSessionHas('success');
        $this->assertSame('SUSPENDED', $workspace->fresh()->status);

        $this->actingAs($admin)
            ->patch(route('admin.workspaces.status', $workspace), ['status' => 'ACTIVE'])
            ->assertSessionHas('success');
        $this->assertSame('ACTIVE', $workspace->fresh()->status);

        $this->actingAs($admin)
            ->patch(route('admin.workspaces.status', $workspace), ['status' => 'ARCHIVED'])
            ->assertSessionHas('success');
        $this->assertSame('ARCHIVED', $workspace->fresh()->status);
    }

    public function test_a_workspace_status_change_is_recorded_permanently(): void
    {
        [, $workspace] = $this->makeCustomer();

        $this->actingAs($this->makeAdmin())
            ->patch(route('admin.workspaces.status', $workspace), ['status' => 'SUSPENDED']);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'workspace.status_updated',
            'workspace_id' => $workspace->workspace_id,
        ]);
    }

    // ─── Suspension must actually take effect ────────────────────

    public function test_suspending_a_workspace_stops_its_members_using_vytte(): void
    {
        [$user, $workspace] = $this->makeCustomer();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $workspace->update(['status' => 'SUSPENDED']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden()
            ->assertSee('on hold');
    }

    public function test_a_closed_workspace_also_stops_its_members_using_vytte(): void
    {
        [$user, $workspace] = $this->makeCustomer('ARCHIVED');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden()
            ->assertSee('closed');
    }

    public function test_a_platform_admin_can_still_look_into_a_suspended_workspace(): void
    {
        [, $workspace] = $this->makeCustomer('SUSPENDED');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.workspaces.show', $workspace))
            ->assertOk();
    }

    // ─── People ──────────────────────────────────────────────────

    public function test_admin_can_search_people_by_name_or_email(): void
    {
        User::factory()->create(['name' => 'Amina Bello', 'email' => 'amina@example.test']);
        User::factory()->create(['name' => 'Kwame Mensah', 'email' => 'kwame@example.test']);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.platform-users.index', ['search' => 'amina@']))
            ->assertOk()
            ->assertSee('Amina Bello')
            ->assertDontSee('Kwame Mensah');
    }

    public function test_admin_can_suspend_a_person_with_a_recorded_reason(): void
    {
        $person = User::factory()->create();

        $this->actingAs($this->makeAdmin())
            ->patch(route('admin.platform-users.suspend', $person), [
                'suspension_reason' => 'Payment dispute under review',
            ])
            ->assertSessionHas('success');

        $person->refresh();
        $this->assertTrue($person->isSuspended());
        $this->assertSame('Payment dispute under review', $person->suspension_reason);
    }

    public function test_a_suspension_requires_a_reason(): void
    {
        $person = User::factory()->create();

        $this->actingAs($this->makeAdmin())
            ->patch(route('admin.platform-users.suspend', $person), ['suspension_reason' => ''])
            ->assertSessionHasErrors('suspension_reason');

        $this->assertFalse($person->fresh()->isSuspended());
    }

    public function test_a_suspended_person_cannot_sign_in_and_is_told_why(): void
    {
        $person = User::factory()->create([
            'email' => 'held@example.test',
            'password' => bcrypt('correct-horse'),
            'suspended_at' => now(),
            'suspension_reason' => 'Payment dispute under review',
        ]);

        $this->post(route('login'), [
            'email' => $person->email,
            'password' => 'correct-horse',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString(
            'Payment dispute under review',
            session('errors')->first('email')
        );
    }

    public function test_reactivating_a_person_restores_sign_in(): void
    {
        $person = User::factory()->create([
            'email' => 'restored@example.test',
            'password' => bcrypt('correct-horse'),
            'suspended_at' => now(),
            'suspension_reason' => 'Temporary hold',
        ]);

        $this->actingAs($this->makeAdmin())
            ->patch(route('admin.platform-users.reactivate', $person))
            ->assertSessionHas('success');

        // Drop the administrator's session, otherwise the login below is a no-op and the
        // assertion would pass on the wrong user.
        auth()->logout();

        $this->post(route('login'), [
            'email' => $person->email,
            'password' => 'correct-horse',
        ]);

        $this->assertAuthenticatedAs($person->fresh());
    }

    public function test_an_administrator_cannot_suspend_their_own_account(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->patch(route('admin.platform-users.suspend', $admin), ['suspension_reason' => 'Testing'])
            ->assertSessionHasErrors('suspension_reason');

        $this->assertFalse($admin->fresh()->isSuspended());
    }

    public function test_the_last_platform_admin_cannot_be_suspended(): void
    {
        $onlyAdmin = User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);
        $actingAdmin = $this->makeAdmin();

        // Leave exactly one platform admin besides the one acting, then remove that one.
        $actingAdmin->update(['platform_role' => 'PLATFORM_ADMIN']);
        User::where('platform_role', 'PLATFORM_ADMIN')
            ->whereKeyNot($onlyAdmin->getKey())
            ->whereKeyNot($actingAdmin->getKey())
            ->update(['platform_role' => null]);
        $actingAdmin->update(['platform_role' => null]);

        $stillAdmin = $this->makeAdmin();
        $stillAdmin->update(['platform_role' => null]);

        $this->actingAs($onlyAdmin)
            ->patch(route('admin.platform-users.suspend', $onlyAdmin), ['suspension_reason' => 'Testing'])
            ->assertSessionHasErrors('suspension_reason');

        $this->assertFalse($onlyAdmin->fresh()->isSuspended());
    }

    public function test_person_detail_shows_their_history(): void
    {
        $person = User::factory()->create(['name' => 'Amina Bello']);

        AuditLog::create([
            'user_id' => $person->user_id,
            'event' => 'assessment.created',
            'created_at' => now(),
        ]);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.platform-users.show', $person))
            ->assertOk()
            ->assertSee('Amina Bello')
            ->assertSee('History')
            ->assertSee('Assessment created in a workspace');
    }

    // ─── Losing access takes effect immediately ──────────────────

    /**
     * The suite runs with the array session driver, which has no server-side record to
     * delete. Production stores sessions in the database, so these tests assert against
     * that configuration rather than the test default.
     */
    private function useDatabaseSessions(): void
    {
        config(['session.driver' => 'database']);
    }

    public function test_suspending_a_person_ends_the_session_they_already_have(): void
    {
        $this->useDatabaseSessions();
        $person = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'session-under-test',
            'user_id' => $person->user_id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($this->makeAdmin())
            ->patch(route('admin.platform-users.suspend', $person), [
                'suspension_reason' => 'Payment dispute under review',
            ]);

        // Blocking sign-in alone would leave them working until the session expired.
        $this->assertDatabaseMissing('sessions', ['id' => 'session-under-test']);
    }

    public function test_suspending_a_workspace_ends_its_members_sessions(): void
    {
        $this->useDatabaseSessions();
        [$user, $workspace] = $this->makeCustomer();

        DB::table('sessions')->insert([
            'id' => 'workspace-session',
            'user_id' => $user->user_id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($this->makeAdmin())
            ->patch(route('admin.workspaces.status', $workspace), ['status' => 'SUSPENDED']);

        $this->assertDatabaseMissing('sessions', ['id' => 'workspace-session']);
    }

    public function test_reactivating_a_workspace_does_not_end_sessions(): void
    {
        $this->useDatabaseSessions();
        [$user, $workspace] = $this->makeCustomer('SUSPENDED');

        DB::table('sessions')->insert([
            'id' => 'kept-session',
            'user_id' => $user->user_id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($this->makeAdmin())
            ->patch(route('admin.workspaces.status', $workspace), ['status' => 'ACTIVE']);

        $this->assertDatabaseHas('sessions', ['id' => 'kept-session']);
    }

    // ─── Plans ───────────────────────────────────────────────────

    public function test_plans_screen_shows_what_each_plan_is_carrying(): void
    {
        $this->makeCustomer();

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.plan-features.index'))
            ->assertOk()
            ->assertSee('Who is on each plan')
            ->assertSee('Workspaces');
    }

    // ─── Activity centre ─────────────────────────────────────────

    public function test_activity_centre_reads_in_plain_language_not_event_keys(): void
    {
        AuditLog::create([
            'user_id' => null,
            'event' => 'question.version.published',
            'created_at' => now(),
        ]);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Question published')
            ->assertDontSee('question.version.published');
    }

    public function test_activity_can_be_filtered_to_one_kind_of_event(): void
    {
        AuditLog::create(['user_id' => null, 'event' => 'question.version.published', 'created_at' => now()]);
        AuditLog::create(['user_id' => null, 'event' => 'platform.user.suspended', 'created_at' => now()]);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.audit-logs.index', ['category' => 'Security']))
            ->assertOk()
            ->assertSee('Account suspended')
            ->assertDontSee('Question published');
    }

    public function test_activity_can_be_searched_by_who_did_it(): void
    {
        $person = User::factory()->create(['name' => 'Amina Bello']);
        $other = User::factory()->create(['name' => 'Kwame Mensah']);

        AuditLog::create(['user_id' => $person->user_id, 'event' => 'assessment.created', 'created_at' => now()]);
        AuditLog::create(['user_id' => $other->user_id, 'event' => 'assessment.published', 'created_at' => now()]);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.audit-logs.index', ['search' => 'Amina']))
            ->assertOk()
            ->assertSee('Amina Bello')
            ->assertDontSee('Kwame Mensah');
    }

    // ─── The two assessment screens are different things ─────────

    public function test_assessments_in_use_lists_what_customers_run_not_what_vytte_publishes(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.assessment-oversight.index'))
            ->assertOk()
            ->assertSee('Assessments in Use')
            ->assertSee('customers are running in their own workspaces');
    }

    public function test_assessments_in_use_never_exposes_storage_vocabulary(): void
    {
        $response = $this->actingAs($this->makeAdmin())
            ->get(route('admin.assessment-oversight.index'))
            ->assertOk();

        // This screen used to print snapshot presence, raw statuses and creation paths.
        // None of that means anything to a reader, and the product rules forbid exposing it.
        foreach (['Immutable artifacts', 'Snapshot:', 'metadata view'] as $leak) {
            $response->assertDontSee($leak);
        }

        // Storage codes may still appear as filter option values — the query needs them to
        // wire the form up. What matters is that none of them is ever shown as text.
        foreach (['COMPREHENSIVE', 'FOCUSED', 'IN_PROGRESS'] as $code) {
            $response->assertDontSeeText($code);
        }

        $response->assertSee('Whole facility')->assertSee('One health area');
    }

    public function test_assessments_in_use_can_be_searched_by_workspace(): void
    {
        Workspace::factory()->create(['name' => 'Kaduna Teaching Hospital']);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.assessment-oversight.index', ['search' => 'Kaduna']))
            ->assertOk();
    }

    // ─── Shared reports ──────────────────────────────────────────

    public function test_shared_reports_page_summarises_link_status(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.report-shares.index'))
            ->assertOk()
            ->assertSee('Live links')
            ->assertSee('Revoked');
    }

    // ─── Platform monitoring ─────────────────────────────────────

    public function test_platform_health_reports_each_check_in_plain_language(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.monitoring.index'))
            ->assertOk()
            ->assertSee('Platform Health')
            ->assertSee('Database')
            ->assertSee('Background work')
            ->assertSee('Email delivery');
    }

    public function test_platform_health_is_not_reachable_by_a_customer(): void
    {
        [$user] = $this->makeCustomer();

        $this->actingAs($user)
            ->get(route('admin.monitoring.index'))
            ->assertForbidden();
    }

    public function test_platform_health_reports_the_database_as_reachable(): void
    {
        $checks = app(PlatformHealthService::class)->checks();
        $database = collect($checks)->firstWhere('key', 'database');

        $this->assertSame('ok', $database['status']);
    }
}
