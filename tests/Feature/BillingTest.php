<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\PlanFeatureSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
        $this->seed(PlanFeatureSeeder::class);
    }

    private function createWorkspaceWithOwner(string $plan = 'STARTER'): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['plan' => $plan]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);

        return [$user, $workspace];
    }

    public function test_unauthenticated_cannot_access_plans_page(): void
    {
        $this->get(route('billing.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_beta_plans_page(): void
    {
        [$user] = $this->createWorkspaceWithOwner();

        $this->actingAs($user)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertSee('Plans')
            ->assertSee('Starter')
            ->assertSee('Professional')
            ->assertSee('Organization')
            ->assertSee('Payments and billing will be connected later');
    }

    public function test_plans_link_appears_after_settings_in_the_sidebar(): void
    {
        [$user] = $this->createWorkspaceWithOwner();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Settings', 'Plans', 'Current plan'], false)
            // The logo link is labelled for screen readers. The label now names the role's
            // home rather than always saying "dashboard", so Platform Admin announces its
            // own destination.
            ->assertSee('aria-label="Go to ', false);
    }

    public function test_beta_plan_has_no_project_limit_when_configured_unlimited(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner('STARTER');

        for ($i = 0; $i < 3; $i++) {
            Project::factory()->create([
                'workspace_id' => $workspace->workspace_id,
                'owner_user_id' => $user->user_id,
            ]);
        }

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'name' => 'Beta Project',
                'target_name' => 'Test Target',
                'target_type_code' => 'COMMUNITY',
                'country' => 'Nigeria',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', ['name' => 'Beta Project', 'workspace_id' => $workspace->workspace_id]);
    }

    public function test_payment_webhook_routes_are_not_registered_for_beta(): void
    {
        $this->assertFalse(Route::has('billing.webhook.paystack'));
        $this->assertFalse(Route::has('billing.webhook.flutterwave'));
    }
}
