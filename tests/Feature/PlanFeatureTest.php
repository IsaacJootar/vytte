<?php

namespace Tests\Feature;

use App\Models\PlanFeature;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\PlanService;
use Database\Seeders\PlanFeatureSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);
        $this->seed(PlanFeatureSeeder::class);
    }

    private function makeWorkspace(string $plan): Workspace
    {
        return Workspace::factory()->create(['plan' => $plan]);
    }

    private function makeUserWithWorkspace(string $plan): array
    {
        $user = User::factory()->create();
        $workspace = $this->makeWorkspace($plan);
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);

        return [$user, $workspace];
    }

    public function test_beta_plans_have_identical_unlocked_feature_access(): void
    {
        foreach (PlanService::PLANS as $plan) {
            $workspace = $this->makeWorkspace($plan);

            foreach (array_keys(PlanService::FEATURES) as $featureKey) {
                $this->assertTrue(
                    PlanService::workspaceCanAccess($workspace, $featureKey),
                    "{$featureKey} should be enabled for {$plan} during beta"
                );
            }
        }
    }

    public function test_legacy_plan_codes_are_normalized(): void
    {
        $this->assertSame('STARTER', PlanService::normalizePlan('FREE'));
        $this->assertSame('PROFESSIONAL', PlanService::normalizePlan('PRO'));
        $this->assertSame('ORGANIZATION', PlanService::normalizePlan('AGENCY'));
    }

    public function test_toggling_feature_off_immediately_blocks_that_plan(): void
    {
        $workspace = $this->makeWorkspace('STARTER');

        $this->assertTrue(PlanService::workspaceCanAccess($workspace, 'team_members'));

        PlanFeature::where('plan', 'STARTER')->where('feature_key', 'team_members')->update(['enabled' => false]);

        $this->assertFalse(PlanService::workspaceCanAccess($workspace, 'team_members'));
    }

    public function test_unknown_feature_key_returns_false(): void
    {
        $workspace = $this->makeWorkspace('ORGANIZATION');

        $this->assertFalse(PlanService::workspaceCanAccess($workspace, 'nonexistent_feature'));
    }

    public function test_platform_admin_can_manage_plan_features(): void
    {
        $admin = User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);

        $this->actingAs($admin)
            ->get(route('admin.plan-features.index'))
            ->assertOk()
            ->assertSee('Plan Management')
            ->assertSee('Starter')
            ->assertSee('Workspace Custom Assessments');

        $this->actingAs($admin)
            ->put(route('admin.plan-features.update'), [
                'plans' => [
                    'STARTER' => [
                        'public_label' => 'Starter',
                        'description' => 'Starter beta plan',
                        'display_order' => 1,
                        'is_active' => '1',
                        'is_beta_unlocked' => '1',
                        'limits_json' => '{"projects":null,"assessments_per_project":null}',
                    ],
                    'PROFESSIONAL' => [
                        'public_label' => 'Professional',
                        'description' => 'Professional beta plan',
                        'display_order' => 2,
                        'is_active' => '1',
                        'is_beta_unlocked' => '1',
                        'limits_json' => '{"projects":null,"assessments_per_project":null}',
                    ],
                    'ORGANIZATION' => [
                        'public_label' => 'Organization',
                        'description' => 'Organization beta plan',
                        'display_order' => 3,
                        'is_active' => '1',
                        'is_beta_unlocked' => '1',
                        'limits_json' => '{"projects":null,"assessments_per_project":null}',
                    ],
                ],
                'features' => [
                    'STARTER' => ['team_members' => '1'],
                    'PROFESSIONAL' => ['team_members' => '1'],
                    'ORGANIZATION' => ['team_members' => '1'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plan_features', [
            'plan' => 'STARTER',
            'feature_key' => 'team_members',
            'enabled' => 1,
        ]);
    }

    public function test_disabled_custom_assessment_feature_is_enforced_server_side(): void
    {
        [$user] = $this->makeUserWithWorkspace('STARTER');
        PlanFeature::where('plan', 'STARTER')->where('feature_key', 'workspace_custom_assessments')->update(['enabled' => false]);

        $this->actingAs($user)
            ->get(route('custom-assessments.index'))
            ->assertForbidden();
    }
}
