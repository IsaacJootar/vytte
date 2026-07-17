<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentTier;
use App\Models\Project;
use App\Models\Target;
use App\Models\TargetCategory;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        Config::set('services.paystack.secret_key', 'test_secret');
        Config::set('services.paystack.public_key', 'pk_test_placeholder');
        Config::set('services.flutterwave.secret_hash', 'test_flutterwave_hash');
    }

    private function createWorkspaceWithOwner(string $plan = 'FREE'): array
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

    private function createProject(Workspace $workspace, User $user): Project
    {
        return Project::factory()->create([
            'workspace_id' => $workspace->workspace_id,
            'owner_user_id' => $user->user_id,
        ]);
    }

    private function createAssessment(Project $project, Workspace $workspace): Assessment
    {
        $categoryId = TargetCategory::value('category_id');
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Test Community',
            'category_id' => $categoryId,
            'owner_workspace_id' => $workspace->workspace_id,
        ]);

        $tierId = AssessmentTier::value('assessment_tier_id');

        return Assessment::create([
            'target_id' => $target->target_id,
            'project_id' => $project->project_id,
            'assessment_tier_id' => $tierId,
            'scope_type' => 'FULL_TARGET',
            'status' => 'IN_PROGRESS',
            'publish_status' => 'DRAFT',
            'assessor_name' => 'Tester',
            'started_at' => now(),
        ]);
    }

    // ─── Billing page ─────────────────────────────────────────────

    public function test_unauthenticated_cannot_access_billing(): void
    {
        $this->get(route('billing.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_billing_page(): void
    {
        [$user] = $this->createWorkspaceWithOwner();

        $this->actingAs($user)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertSee('Billing & Plan', false)
            ->assertSee('Free')
            ->assertSee('Pro')
            ->assertSee('Agency');
    }

    public function test_billing_page_shows_current_plan(): void
    {
        [$user] = $this->createWorkspaceWithOwner('PRO');

        $this->actingAs($user)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertSee('PRO');
    }

    // ─── Project limit enforcement ────────────────────────────────

    public function test_free_plan_cannot_create_second_project(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner('FREE');
        $this->createProject($workspace, $user);

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'name' => 'Second Project',
                'target_name' => 'Test Target',
                'target_type_code' => 'COMMUNITY',
                'category_id' => TargetCategory::value('category_id'),
            ])
            ->assertRedirect(route('billing.index'));
    }

    public function test_pro_plan_can_create_up_to_10_projects(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner('PRO');

        for ($i = 0; $i < 9; $i++) {
            $this->createProject($workspace, $user);
        }

        $response = $this->actingAs($user)
            ->post(route('projects.store'), [
                'name' => 'Tenth Project',
                'target_name' => 'Test Target',
                'target_type_code' => 'COMMUNITY',
                'category_id' => TargetCategory::value('category_id'),
                'country' => 'Nigeria',
            ]);

        $response->assertRedirect();
        $this->assertNotEquals(route('billing.index'), $response->headers->get('Location'));
        $this->assertDatabaseHas('projects', ['name' => 'Tenth Project', 'workspace_id' => $workspace->workspace_id]);
    }

    public function test_pro_plan_blocked_at_11th_project(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner('PRO');

        for ($i = 0; $i < 10; $i++) {
            $this->createProject($workspace, $user);
        }

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'name' => 'Eleventh Project',
                'target_name' => 'Test Target',
                'target_type_code' => 'COMMUNITY',
                'category_id' => TargetCategory::value('category_id'),
            ])
            ->assertRedirect(route('billing.index'));
    }

    public function test_agency_plan_has_no_project_limit(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner('AGENCY');

        for ($i = 0; $i < 15; $i++) {
            $this->createProject($workspace, $user);
        }

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'name' => 'Many Projects',
                'target_name' => 'Test Target',
                'target_type_code' => 'COMMUNITY',
                'category_id' => TargetCategory::value('category_id'),
                'country' => 'Nigeria',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', ['name' => 'Many Projects', 'workspace_id' => $workspace->workspace_id]);
    }

    // ─── Assessment limit enforcement ─────────────────────────────

    public function test_free_plan_cannot_create_4th_assessment(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner('FREE');
        $project = $this->createProject($workspace, $user);

        for ($i = 0; $i < 3; $i++) {
            $this->createAssessment($project, $workspace);
        }

        $this->actingAs($user)
            ->post(route('assessments.store', $project), [
                'module_id' => 1,
            ])
            ->assertRedirect(route('billing.index'));
    }

    public function test_pro_plan_can_create_unlimited_assessments(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner('PRO');
        $project = $this->createProject($workspace, $user);

        for ($i = 0; $i < 10; $i++) {
            $this->createAssessment($project, $workspace);
        }

        $response = $this->actingAs($user)
            ->post(route('assessments.store', $project), [
                'module_id' => 1,
            ]);

        $this->assertNotEquals(route('billing.index'), $response->headers->get('Location'));
    }

    // ─── Paystack webhook ─────────────────────────────────────────

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = json_encode(['event' => 'charge.success', 'data' => []]);

        $this->post(route('billing.webhook.paystack'), [], [
            'X-Paystack-Signature' => 'invalidsignature',
            'Content-Type' => 'application/json',
        ])->assertStatus(401);
    }

    public function test_webhook_accepts_valid_signature(): void
    {
        $payload = json_encode(['event' => 'ping']);
        $signature = hash_hmac('sha512', $payload, 'test_secret');

        $this->call('POST', route('billing.webhook.paystack'), [], [], [], [
            'HTTP_X-Paystack-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(200);
    }

    public function test_webhook_upgrades_workspace_plan_on_charge_success(): void
    {
        [, $workspace] = $this->createWorkspaceWithOwner('FREE');

        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'metadata' => [
                    'workspace_id' => $workspace->workspace_id,
                    'plan' => 'PRO',
                ],
            ],
        ]);

        $signature = hash_hmac('sha512', $payload, 'test_secret');

        $this->call('POST', route('billing.webhook.paystack'), [], [], [], [
            'HTTP_X-Paystack-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(200);

        $this->assertDatabaseHas('workspaces', [
            'workspace_id' => $workspace->workspace_id,
            'plan' => 'PRO',
        ]);
    }

    public function test_webhook_ignores_unknown_workspace(): void
    {
        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'metadata' => [
                    'workspace_id' => 'non-existent-uuid',
                    'plan' => 'PRO',
                ],
            ],
        ]);

        $signature = hash_hmac('sha512', $payload, 'test_secret');

        $this->call('POST', route('billing.webhook.paystack'), [], [], [], [
            'HTTP_X-Paystack-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(200);
    }

    public function test_webhook_ignores_invalid_plan(): void
    {
        [, $workspace] = $this->createWorkspaceWithOwner('FREE');

        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'metadata' => [
                    'workspace_id' => $workspace->workspace_id,
                    'plan' => 'ENTERPRISE',
                ],
            ],
        ]);

        $signature = hash_hmac('sha512', $payload, 'test_secret');

        $this->call('POST', route('billing.webhook.paystack'), [], [], [], [
            'HTTP_X-Paystack-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(200);

        $this->assertDatabaseHas('workspaces', [
            'workspace_id' => $workspace->workspace_id,
            'plan' => 'FREE',
        ]);
    }

    public function test_flutterwave_webhook_rejects_invalid_signature(): void
    {
        $this->postJson(route('billing.webhook.flutterwave'), ['event' => 'charge.completed'], [
            'verif-hash' => 'invalid',
        ])->assertUnauthorized();
    }

    public function test_flutterwave_webhook_accepts_signature_and_upgrades_workspace(): void
    {
        [, $workspace] = $this->createWorkspaceWithOwner('FREE');

        $this->postJson(route('billing.webhook.flutterwave'), [
            'event' => 'charge.completed',
            'data' => [
                'status' => 'successful',
                'meta' => [
                    'workspace_id' => $workspace->workspace_id,
                    'plan' => 'PRO',
                ],
            ],
        ], [
            'verif-hash' => 'test_flutterwave_hash',
        ])->assertOk();

        $this->assertDatabaseHas('workspaces', [
            'workspace_id' => $workspace->workspace_id,
            'plan' => 'PRO',
        ]);
    }
}
