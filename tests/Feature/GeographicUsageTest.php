<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentTier;
use App\Models\Project;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeographicUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);
    }

    private function makeUser(): User
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);

        return $user;
    }

    private function createAssessmentForTarget(Target $target, Workspace $workspace, User $user): Assessment
    {
        $project = Project::factory()->create([
            'workspace_id' => $workspace->workspace_id,
            'owner_user_id' => $user->user_id,
        ]);

        $tierId = AssessmentTier::value('assessment_tier_id');

        return Assessment::create([
            'target_id' => $target->target_id,
            'project_id' => $project->project_id,
            'assessment_tier_id' => $tierId,
            'status' => 'IN_PROGRESS',
            'publish_status' => 'DRAFT',
            'started_at' => now(),
        ]);
    }

    private function createTarget(Workspace $workspace, string $country, ?string $region = null): Target
    {

        return Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => "Target in {$country}",
            'owner_workspace_id' => $workspace->workspace_id,
            'country' => $country,
            'region' => $region,
        ]);
    }

    // ---- Access control ----

    public function test_unauthenticated_cannot_access_geographic_usage(): void
    {
        $this->get(route('admin.geographic-usage.index'))
            ->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_access_geographic_usage(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('admin.geographic-usage.index'))
            ->assertForbidden();
    }

    public function test_platform_admin_can_access_geographic_usage(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.geographic-usage.index'))
            ->assertOk()
            ->assertSee('Geographic Usage');
    }

    // ---- Empty state ----

    public function test_shows_empty_state_when_no_assessments(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.geographic-usage.index'))
            ->assertOk()
            ->assertSee('No location data yet');
    }

    // ---- Country counts ----

    public function test_shows_correct_country_assessment_counts(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->workspace_id, 'user_id' => $user->user_id, 'role' => 'OWNER']);

        $targetNigeria1 = $this->createTarget($workspace, 'Nigeria', 'Lagos');
        $targetNigeria2 = $this->createTarget($workspace, 'Nigeria', 'Kano');
        $targetKenya = $this->createTarget($workspace, 'Kenya', 'Nairobi County');

        // 3 Nigeria assessments, 1 Kenya assessment
        $this->createAssessmentForTarget($targetNigeria1, $workspace, $user);
        $this->createAssessmentForTarget($targetNigeria1, $workspace, $user);
        $this->createAssessmentForTarget($targetNigeria2, $workspace, $user);
        $this->createAssessmentForTarget($targetKenya, $workspace, $user);

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('admin.geographic-usage.index'));

        $response->assertOk();
        $response->assertViewHas('countries');
        $response->assertViewHas('totalAssessments', 4);
        $response->assertViewHas('countryCount', 2);

        $countries = $response->viewData('countries');
        $nigeria = collect($countries)->firstWhere('country', 'Nigeria');
        $this->assertNotNull($nigeria);
        $this->assertEquals(3, $nigeria['assessment_count']);

        $kenya = collect($countries)->firstWhere('country', 'Kenya');
        $this->assertNotNull($kenya);
        $this->assertEquals(1, $kenya['assessment_count']);
    }

    // ---- Region breakdown ----

    public function test_shows_region_breakdown_within_country(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->workspace_id, 'user_id' => $user->user_id, 'role' => 'OWNER']);

        $lagos = $this->createTarget($workspace, 'Nigeria', 'Lagos');
        $kano = $this->createTarget($workspace, 'Nigeria', 'Kano');

        $this->createAssessmentForTarget($lagos, $workspace, $user);
        $this->createAssessmentForTarget($lagos, $workspace, $user);
        $this->createAssessmentForTarget($kano, $workspace, $user);

        $countries = $this->actingAs($this->makeAdmin())
            ->get(route('admin.geographic-usage.index'))
            ->viewData('countries');

        $nigeria = collect($countries)->firstWhere('country', 'Nigeria');
        $this->assertCount(2, $nigeria['regions']);

        $lagosRegion = collect($nigeria['regions'])->firstWhere('region', 'Lagos');
        $this->assertEquals(2, $lagosRegion['assessment_count']);

        $kanoRegion = collect($nigeria['regions'])->firstWhere('region', 'Kano');
        $this->assertEquals(1, $kanoRegion['assessment_count']);
    }

    // ---- Privacy boundary ----

    public function test_view_data_contains_no_workspace_or_target_identifiers(): void
    {
        $workspace = Workspace::factory()->create(['name' => 'Secret Workspace']);
        $user = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->workspace_id, 'user_id' => $user->user_id, 'role' => 'OWNER']);

        $target = $this->createTarget($workspace, 'Nigeria', 'Lagos');
        $this->createAssessmentForTarget($target, $workspace, $user);

        $countries = $this->actingAs($this->makeAdmin())
            ->get(route('admin.geographic-usage.index'))
            ->viewData('countries');

        // View data only contains country, assessment_count, regions
        $nigeria = collect($countries)->firstWhere('country', 'Nigeria');
        $this->assertArrayNotHasKey('workspace_id', $nigeria);
        $this->assertArrayNotHasKey('target_id', $nigeria);
        $this->assertArrayNotHasKey('project_id', $nigeria);
        $this->assertArrayNotHasKey('name', $nigeria);

        // Page must not render the workspace name
        $response = $this->actingAs($this->makeAdmin())
            ->get(route('admin.geographic-usage.index'));
        $response->assertDontSee('Secret Workspace');
        $response->assertDontSee('Target in Nigeria');
    }

    // ---- Targets without location are excluded ----

    public function test_targets_without_country_are_excluded(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->workspace_id, 'user_id' => $user->user_id, 'role' => 'OWNER']);
        $noCountryTarget = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'No Country Target',
            'owner_workspace_id' => $workspace->workspace_id,
            'country' => null,
        ]);
        $this->createAssessmentForTarget($noCountryTarget, $workspace, $user);

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('admin.geographic-usage.index'));

        $response->assertViewHas('totalAssessments', 0);
        $response->assertViewHas('countryCount', 0);
    }

    // ---- Countries ordered by count descending ----

    public function test_countries_ordered_by_assessment_count_descending(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->workspace_id, 'user_id' => $user->user_id, 'role' => 'OWNER']);

        $ghana = $this->createTarget($workspace, 'Ghana');
        $nigeria = $this->createTarget($workspace, 'Nigeria');

        $this->createAssessmentForTarget($ghana, $workspace, $user);
        $this->createAssessmentForTarget($nigeria, $workspace, $user);
        $this->createAssessmentForTarget($nigeria, $workspace, $user);

        $countries = $this->actingAs($this->makeAdmin())
            ->get(route('admin.geographic-usage.index'))
            ->viewData('countries');

        $this->assertEquals('Nigeria', $countries[0]['country']);
        $this->assertEquals('Ghana', $countries[1]['country']);
    }
}
