<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\PlanFeatureSeeder;
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
}
