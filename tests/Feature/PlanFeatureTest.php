<?php

namespace Tests\Feature;

use App\Models\PlanFeature;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\PlanService;
use Database\Seeders\PlanFeatureSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
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

    // ---- workspaceCanAccess ----

    public function test_free_workspace_cannot_access_any_pro_feature(): void
    {
        $workspace = $this->makeWorkspace('FREE');

        foreach (array_keys(PlanService::FEATURES) as $featureKey) {
            $this->assertFalse(
                PlanService::workspaceCanAccess($workspace, $featureKey),
                "Feature '{$featureKey}' should be blocked for FREE plan"
            );
        }
    }

    public function test_pro_workspace_can_access_seeded_pro_features(): void
    {
        $workspace = $this->makeWorkspace('PRO');

        $proFeatures = [
            'team_members',
            'shareable_public_links',
            'shareable_report_links',
            'progress_maturity_tracking',
            'localization',
            'patient_community_voice_module',
            'pdf_export_no_watermark',
            'csv_export',
        ];

        foreach ($proFeatures as $featureKey) {
            $this->assertTrue(
                PlanService::workspaceCanAccess($workspace, $featureKey),
                "Feature '{$featureKey}' should be enabled for PRO plan"
            );
        }
    }

    public function test_agency_workspace_can_access_all_features(): void
    {
        $workspace = $this->makeWorkspace('AGENCY');

        foreach (array_keys(PlanService::FEATURES) as $featureKey) {
            $this->assertTrue(
                PlanService::workspaceCanAccess($workspace, $featureKey),
                "Feature '{$featureKey}' should be enabled for AGENCY plan"
            );
        }
    }

    public function test_toggling_feature_off_immediately_blocks_pro(): void
    {
        $workspace = $this->makeWorkspace('PRO');

        $this->assertTrue(PlanService::workspaceCanAccess($workspace, 'team_members'));

        PlanFeature::where('plan', 'PRO')->where('feature_key', 'team_members')->update(['enabled' => false]);

        $this->assertFalse(PlanService::workspaceCanAccess($workspace, 'team_members'));
    }

    public function test_unknown_feature_key_returns_false(): void
    {
        $workspace = $this->makeWorkspace('AGENCY');

        $this->assertFalse(PlanService::workspaceCanAccess($workspace, 'nonexistent_feature'));
    }

    // ---- Admin UI access ----

    public function test_unauthenticated_cannot_view_plan_features(): void
    {
        $this->get(route('admin.plan-features.index'))
            ->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_view_plan_features(): void
    {
        [$user] = $this->makeUserWithWorkspace('PRO');

        $this->actingAs($user)
            ->get(route('admin.plan-features.index'))
            ->assertForbidden();
    }

    public function test_platform_admin_can_view_plan_features(): void
    {
        $admin = User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);

        $this->actingAs($admin)
            ->get(route('admin.plan-features.index'))
            ->assertOk()
            ->assertSee('Plan Features')
            ->assertSee('Team Members');
    }

    public function test_admin_can_update_plan_features(): void
    {
        $admin = User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);

        $this->actingAs($admin)
            ->put(route('admin.plan-features.update'), [
                'features' => [
                    'FREE' => ['team_members' => '1'],
                    'PRO' => [],
                    'AGENCY' => [],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plan_features', [
            'plan' => 'FREE',
            'feature_key' => 'team_members',
            'enabled' => 1,
        ]);

        $this->assertDatabaseHas('plan_features', [
            'plan' => 'PRO',
            'feature_key' => 'team_members',
            'enabled' => 0,
        ]);
    }

    // ---- Enforcement: team members ----

    public function test_free_workspace_cannot_invite_team_members(): void
    {
        [$user, $workspace] = $this->makeUserWithWorkspace('FREE');

        $this->actingAs($user)
            ->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->post(route('team.invite'), ['email' => 'newuser@example.com', 'role' => 'MEMBER'])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('workspace_invitations', [
            'workspace_id' => $workspace->workspace_id,
            'email' => 'newuser@example.com',
        ]);
    }

    public function test_pro_workspace_can_invite_team_members(): void
    {
        [$user] = $this->makeUserWithWorkspace('PRO');

        $this->actingAs($user)
            ->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->post(route('team.invite'), ['email' => 'invited@example.com', 'role' => 'MEMBER'])
            ->assertSessionMissing('error');
    }

    // ---- Enforcement: CSV export ----

    public function test_free_workspace_cannot_export_csv(): void
    {
        [$user, $workspace] = $this->makeUserWithWorkspace('FREE');

        $project = Project::factory()->create([
            'workspace_id' => $workspace->workspace_id,
            'owner_user_id' => $user->user_id,
        ]);

        $this->actingAs($user)
            ->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->get(route('projects.export.csv', $project))
            ->assertForbidden();
    }

    public function test_pro_workspace_can_export_csv(): void
    {
        [$user, $workspace] = $this->makeUserWithWorkspace('PRO');

        $project = Project::factory()->create([
            'workspace_id' => $workspace->workspace_id,
            'owner_user_id' => $user->user_id,
        ]);

        $this->actingAs($user)
            ->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->get(route('projects.export.csv', $project))
            ->assertOk();
    }
}
