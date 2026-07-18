<?php

namespace Tests\Feature;

use App\Models\PlanFeature;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\PlanFeatureSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BetaReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);
        $this->seed(PlanFeatureSeeder::class);
    }

    private function owner(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['plan' => 'STARTER']);
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);

        return [$user, $workspace];
    }

    public function test_health_endpoint_returns_json_status(): void
    {
        $this->getJson(route('health'))
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_public_respondent_and_shared_report_routes_are_throttled(): void
    {
        $respondentMiddleware = collect(Route::getRoutes()->getByName('respondent.show')->gatherMiddleware());
        $sharedReportMiddleware = collect(Route::getRoutes()->getByName('reports.shared.token')->gatherMiddleware());

        $this->assertTrue($respondentMiddleware->contains('throttle:30,1'));
        $this->assertTrue($sharedReportMiddleware->contains('throttle:60,1'));
    }

    public function test_workspace_admin_can_create_custom_assessment_design(): void
    {
        [$user] = $this->owner();

        $this->actingAs($user)
            ->post(route('custom-assessments.store'), [
                'title' => 'Patient Experience Pulse',
                'purpose' => 'Collect local feedback for service improvement.',
                'scope' => 'Patient experience',
                'questions_text' => "Was staff respectful?\nWas waiting time acceptable?",
                'sections_text' => 'Experience',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('workspace_custom_assessment_designs', [
            'title' => 'Patient Experience Pulse',
            'status' => 'DRAFT',
        ]);
    }

    public function test_custom_assessment_ui_is_blocked_when_plan_feature_is_disabled(): void
    {
        [$user] = $this->owner();
        PlanFeature::where('plan', 'STARTER')->where('feature_key', 'workspace_custom_assessments')->update(['enabled' => false]);

        $this->actingAs($user)
            ->get(route('custom-assessments.create'))
            ->assertForbidden();
    }
}
