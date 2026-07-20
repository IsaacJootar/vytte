<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A platform administrator governs the platform. They are not a customer.
 *
 * DEC-2026-07-18-009 already says the platform admin account is not a customer account, but
 * login sent everyone to the workspace dashboard, so an administrator landed in front of
 * projects and assessments that were not theirs to create.
 */
class PlatformAdminRoleSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_platform_admin_lands_in_platform_admin_after_signing_in(): void
    {
        $admin = User::factory()->create([
            'platform_role' => 'PLATFORM_ADMIN',
            'password' => bcrypt('password'),
        ]);

        $this->post(route('login'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_an_ordinary_user_still_lands_in_their_workspace(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $workspace = Workspace::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_platform_admin_navigation_offers_no_plan_or_way_back_to_a_workspace(): void
    {
        $admin = User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        // A platform administrator has no plan and no workspace to return to.
        $response->assertDontSee('Access level');
        $response->assertDontSee('Back to workspace');
        $response->assertDontSee('Current plan');
    }

    public function test_platform_admin_navigation_offers_no_customer_work(): void
    {
        $admin = User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        // Projects and running assessments belong to customers, not to the platform role.
        $response->assertDontSee(route('projects.index'));
        $response->assertDontSee(route('custom-assessments.index'));
        $response->assertDontSee(route('billing.index'));
    }

    public function test_a_platform_admin_who_also_holds_a_workspace_can_still_cross_between_them(): void
    {
        $admin = User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);
        $workspace = Workspace::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $admin->user_id,
            'role' => 'OWNER',
        ]);
        $admin->update(['active_workspace_id' => $workspace->workspace_id]);

        // The crossing is a navigation item rather than a footnote.
        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Platform Admin')
            ->assertSee(route('admin.dashboard'));
    }
}
