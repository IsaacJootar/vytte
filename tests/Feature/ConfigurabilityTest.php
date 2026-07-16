<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurabilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);
    }

    private function makeRegularUser(): array
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

    // ---- Access control ----

    public function test_non_admin_cannot_access_platform_settings(): void
    {
        [$user] = $this->makeRegularUser();

        $this->actingAs($user)
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_platform_settings(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk();
    }

    // ---- Link expiry ----

    public function test_platform_admin_can_set_link_expiry_days(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'link_expiry_days' => 14,
            'free_plan_projects' => 1,
            'free_plan_assessments' => 3,
            'pro_plan_projects' => 10,
        ]);

        $this->assertEquals(14, (int) PlatformSetting::get('sharing.link_expiry_days'));
    }

    public function test_link_expiry_must_be_between_1_and_365(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'link_expiry_days' => 400,
        ])->assertSessionHasErrors('link_expiry_days');
    }

    // ---- Payment gateway toggles ----

    public function test_platform_admin_can_enable_flutterwave(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'flutterwave_enabled' => '1',
            'link_expiry_days' => 30,
            'free_plan_projects' => 1,
            'free_plan_assessments' => 3,
            'pro_plan_projects' => 10,
        ]);

        $this->assertTrue((bool) PlatformSetting::get('payment.flutterwave_enabled'));
    }

    public function test_platform_admin_can_disable_paystack(): void
    {
        $admin = $this->makeAdmin();

        // paystack_enabled omitted = unchecked checkbox = false
        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'link_expiry_days' => 30,
            'free_plan_projects' => 1,
            'free_plan_assessments' => 3,
            'pro_plan_projects' => 10,
        ]);

        $this->assertFalse((bool) PlatformSetting::get('payment.paystack_enabled'));
    }

    // ---- Plan limits ----

    public function test_free_plan_project_limit_is_configurable(): void
    {
        PlatformSetting::set('plan.free_projects', 3, 'integer');

        $workspace = Workspace::factory()->create(['plan' => 'FREE']);
        $limit = PlanService::projectLimit($workspace);

        $this->assertEquals(3, $limit);
    }

    public function test_free_plan_assessment_limit_is_configurable(): void
    {
        PlatformSetting::set('plan.free_assessments_per_project', 5, 'integer');

        $workspace = Workspace::factory()->create(['plan' => 'FREE']);
        $limit = PlanService::assessmentLimit($workspace);

        $this->assertEquals(5, $limit);
    }

    public function test_pro_plan_project_limit_is_configurable(): void
    {
        PlatformSetting::set('plan.pro_projects', 20, 'integer');

        $workspace = Workspace::factory()->create(['plan' => 'PRO']);
        $limit = PlanService::projectLimit($workspace);

        $this->assertEquals(20, $limit);
    }

    public function test_agency_plan_has_no_project_limit(): void
    {
        $workspace = Workspace::factory()->create(['plan' => 'AGENCY']);
        $this->assertNull(PlanService::projectLimit($workspace));
    }

    public function test_platform_admin_can_update_plan_limits(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'link_expiry_days' => 30,
            'free_plan_projects' => 2,
            'free_plan_assessments' => 5,
            'pro_plan_projects' => 15,
        ]);

        $this->assertEquals(2, (int) PlatformSetting::get('plan.free_projects'));
        $this->assertEquals(5, (int) PlatformSetting::get('plan.free_assessments_per_project'));
        $this->assertEquals(15, (int) PlatformSetting::get('plan.pro_projects'));
    }
}
