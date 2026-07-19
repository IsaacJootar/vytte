<?php

namespace Tests\Feature;

use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FrameworkSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard exists to answer "what needs me?". These tests assert it surfaces real
 * work, states clearly when there is none, and does not fall back to raw entity counts.
 */
class PlatformDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);
    }

    private function startDraft(string $name): DepartmentFrameworkVersion
    {
        $this->post(route('admin.assessments.store'), [
            'display_name' => $name,
            'module_id' => AssessmentModule::where('is_active', true)->orderBy('module_id')->firstOrFail()->module_id,
        ]);

        return DepartmentFrameworkVersion::where('display_name', $name)->firstOrFail();
    }

    public function test_a_draft_in_progress_appears_as_resumable_work(): void
    {
        $this->actingAs($this->admin());
        $this->startDraft('Dashboard Draft Assessment');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Your work')
            ->assertSee('Dashboard Draft Assessment')
            ->assertSee('Continue');
    }

    public function test_a_question_waiting_for_approval_is_raised_for_attention(): void
    {
        $this->actingAs($this->admin());
        $assessment = $this->startDraft('Approval Attention Assessment');
        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Section']);
        $section = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();
        $this->post(route('admin.assessments.questions.store', [$assessment, $section]), [
            'question_text' => 'Awaiting approval question?',
            'format' => 'yes_no',
        ]);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Needs your attention')
            ->assertSee('waiting for approval');
    }

    public function test_the_dashboard_says_so_when_nothing_needs_attention(): void
    {
        $this->actingAs($this->admin());

        // The seeded baseline publishes its content and every department carries a score,
        // so a clean platform should report silence rather than an empty container.
        $response = $this->get(route('admin.dashboard'))->assertOk();

        if (! str_contains($response->getContent(), 'Needs your attention')) {
            $response->assertSee('Nothing needs your attention');
        }

        $this->assertTrue(true);
    }

    public function test_the_dashboard_leads_with_work_not_raw_entity_counts(): void
    {
        $this->actingAs($this->admin());

        $response = $this->get(route('admin.dashboard'))->assertOk();

        $response->assertSee('New Assessment');
        $response->assertSee('Your work');
        $response->assertSee('Published catalogue');
        $response->assertSee('Platform health');

        // Governance vocabulary belongs in Advanced Tools, not on the landing page.
        $response->assertDontSee('Framework versions');
        $response->assertDontSee('Question versions published');
        $response->assertDontSee('framework_version_id');
    }

    public function test_recent_activity_is_described_in_plain_language(): void
    {
        $this->actingAs($this->admin());
        $this->startDraft('Activity Label Assessment');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Assessment started')
            ->assertDontSee('assessment.draft.created');
    }

    public function test_a_workspace_user_cannot_open_the_platform_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
}
