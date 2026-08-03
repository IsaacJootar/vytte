<?php

namespace Tests\Feature;

use App\Models\ContentPublisher;
use App\Models\DepartmentFrameworkVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentPublisherGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);
    }

    public function test_legacy_governed_content_has_the_vytte_publisher(): void
    {
        $vytte = ContentPublisher::where('publisher_code', 'VYTTE')->firstOrFail();

        $this->assertSame(ContentPublisher::STATUS_VERIFIED, $vytte->verification_status);
        $this->assertFalse(DepartmentFrameworkVersion::whereNull('content_publisher_id')->exists());
    }

    public function test_platform_admin_can_create_and_verify_a_publisher_identity(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.publishers.store'), [
            'publisher_code' => 'HOSPITAL-A',
            'name' => 'Hospital A',
            'publisher_type' => 'HEALTH_SYSTEM',
            'visibility' => 'ORGANIZATION',
            'website_url' => 'https://hospital-a.test',
            'contact_email' => 'governance@hospital-a.test',
        ])->assertSessionHasNoErrors();

        $publisher = ContentPublisher::where('publisher_code', 'HOSPITAL-A')->firstOrFail();
        $this->assertSame(ContentPublisher::STATUS_UNVERIFIED, $publisher->verification_status);

        $this->actingAs($admin)->patch(route('admin.publishers.verify', $publisher))
            ->assertSessionHasNoErrors();

        $publisher->refresh();
        $this->assertSame(ContentPublisher::STATUS_VERIFIED, $publisher->verification_status);
        $this->assertSame($admin->user_id, $publisher->verified_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'content.publisher.verified']);
    }

    public function test_workspace_user_cannot_manage_publishers(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.publishers.index'))
            ->assertForbidden();
    }

    public function test_identity_verification_is_not_presented_as_content_review(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.publishers.index'))
            ->assertOk()
            ->assertSee('does not automatically verify its sources, scoring, field testing, or translations');
    }
}
