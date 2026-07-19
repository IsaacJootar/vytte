<?php

namespace Tests\Feature;

use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\User;
use Database\Seeders\PlatformGovernedDemoSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);
    }

    private function platformAdmin(): User
    {
        return User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);
    }

    private function activeModule(): AssessmentModule
    {
        return AssessmentModule::where('is_active', true)->firstOrFail();
    }

    // ---- Authorization ----

    public function test_platform_admin_can_open_the_assessments_landing_page(): void
    {
        $this->actingAs($this->platformAdmin())
            ->get(route('admin.assessments.index'))
            ->assertOk()
            ->assertSee('Assessments')
            ->assertSee('New Assessment');
    }

    public function test_workspace_user_cannot_open_the_assessment_builder(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.assessments.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.assessments.create'))->assertForbidden();
    }

    public function test_guest_cannot_open_the_assessment_builder(): void
    {
        $this->get(route('admin.assessments.index'))->assertRedirect(route('login'));
    }

    public function test_workspace_user_cannot_create_an_assessment(): void
    {
        $module = $this->activeModule();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.assessments.store'), [
                'display_name' => 'Unauthorized assessment',
                'module_id' => $module->module_id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('department_framework_versions', ['display_name' => 'Unauthorized assessment']);
    }

    // ---- Draft creation ----

    public function test_platform_admin_creates_a_draft_assessment_from_basic_information(): void
    {
        $module = $this->activeModule();

        $response = $this->actingAs($this->platformAdmin())
            ->post(route('admin.assessments.store'), [
                'display_name' => 'Outpatient Readiness Assessment',
                'description' => 'Checks readiness of outpatient services.',
                'module_id' => $module->module_id,
                'purpose' => 'Quarterly facility review.',
            ]);

        $assessment = DepartmentFrameworkVersion::where('display_name', 'Outpatient Readiness Assessment')->firstOrFail();

        $response->assertRedirect(route('admin.assessments.show', $assessment));
        $this->assertSame(DepartmentFrameworkVersion::STATUS_DRAFT, $assessment->status);
        $this->assertSame($module->module_id, $assessment->module_id);
        $this->assertSame('Quarterly facility review.', $assessment->purpose);
    }

    public function test_draft_creation_requires_a_name_and_department(): void
    {
        $this->actingAs($this->platformAdmin())
            ->post(route('admin.assessments.store'), ['display_name' => '', 'module_id' => ''])
            ->assertSessionHasErrors(['display_name', 'module_id']);

        $this->assertSame(0, DepartmentFrameworkVersion::where('display_name', '')->count());
    }

    public function test_draft_creation_rejects_an_inactive_department(): void
    {
        $module = AssessmentModule::where('is_active', true)->firstOrFail();
        $module->update(['is_active' => false]);

        $this->actingAs($this->platformAdmin())
            ->post(route('admin.assessments.store'), [
                'display_name' => 'Inactive department assessment',
                'module_id' => $module->module_id,
            ])
            ->assertSessionHasErrors('module_id');
    }

    public function test_creating_a_draft_records_an_audit_event(): void
    {
        $this->actingAs($this->platformAdmin())
            ->post(route('admin.assessments.store'), [
                'display_name' => 'Audited assessment',
                'module_id' => $this->activeModule()->module_id,
            ]);

        $this->assertDatabaseHas('audit_logs', ['event' => 'assessment.draft.created']);
    }

    // ---- Draft resume ----

    public function test_a_saved_draft_is_listed_and_can_be_resumed(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)->post(route('admin.assessments.store'), [
            'display_name' => 'Resumable Draft Assessment',
            'module_id' => $this->activeModule()->module_id,
        ]);

        $assessment = DepartmentFrameworkVersion::where('display_name', 'Resumable Draft Assessment')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.assessments.index'))
            ->assertOk()
            ->assertSee('Resumable Draft Assessment')
            ->assertSee('Continue');

        $this->actingAs($admin)->get(route('admin.assessments.show', $assessment))
            ->assertOk()
            ->assertSee('Resumable Draft Assessment')
            ->assertSee('Basic Information');
    }

    public function test_basic_information_can_be_edited_while_the_assessment_is_a_draft(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)->post(route('admin.assessments.store'), [
            'display_name' => 'Original Name',
            'module_id' => $this->activeModule()->module_id,
        ]);
        $assessment = DepartmentFrameworkVersion::where('display_name', 'Original Name')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.assessments.update', $assessment), [
            'display_name' => 'Renamed Assessment',
            'description' => 'Updated description.',
            'module_id' => $assessment->module_id,
            'purpose' => 'Updated purpose.',
        ])->assertRedirect(route('admin.assessments.show', $assessment));

        $assessment->refresh();
        $this->assertSame('Renamed Assessment', $assessment->display_name);
        $this->assertSame('Updated description.', $assessment->description);
    }

    // ---- Published protection ----

    public function test_a_published_assessment_cannot_be_edited_through_the_builder(): void
    {
        $admin = $this->platformAdmin();
        $published = DepartmentFrameworkVersion::where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)->firstOrFail();
        $originalName = $published->display_name;

        $this->actingAs($admin)->put(route('admin.assessments.update', $published), [
            'display_name' => 'Attempted rename of published content',
            'module_id' => $published->module_id,
        ])->assertSessionHasErrors('status');

        $this->assertSame($originalName, $published->fresh()->display_name);
    }

    public function test_a_published_assessment_shows_as_locked_without_an_edit_action(): void
    {
        $published = DepartmentFrameworkVersion::where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)->firstOrFail();

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.assessments.show', $published))
            ->assertOk()
            ->assertSee('This assessment is locked')
            ->assertDontSee(route('admin.assessments.edit', $published));
    }

    // ---- Wizard shape ----

    public function test_the_wizard_shows_progress_and_marks_unavailable_steps_as_coming_next(): void
    {
        $this->actingAs($this->platformAdmin())
            ->get(route('admin.assessments.create'))
            ->assertOk()
            ->assertSee('Basic Information')
            ->assertSee('Build Assessment')
            ->assertSee('Review')
            ->assertSee('Publish')
            ->assertSee('Coming next');
    }

    public function test_the_builder_form_does_not_ask_for_governance_internals(): void
    {
        $response = $this->actingAs($this->platformAdmin())->get(route('admin.assessments.create'));

        // Governance vocabulary still appears in the Advanced Tools navigation, which is
        // intended. What must not appear is a governance knob the ordinary author is asked
        // to set: those are established by the domain services instead.
        foreach ([
            'name="framework_type"',
            'name="question_version_id"',
            'name="sub_index_id"',
            'name="catalogue_release_id"',
            'name="source_authority"',
            'name="license_code"',
            'name="version_number"',
        ] as $governanceField) {
            $response->assertDontSee($governanceField, false);
        }
    }

    public function test_the_draft_is_created_as_a_focused_framework_version_without_the_author_choosing_one(): void
    {
        $this->actingAs($this->platformAdmin())->post(route('admin.assessments.store'), [
            'display_name' => 'Type Defaulted Assessment',
            'module_id' => $this->activeModule()->module_id,
        ]);

        $assessment = DepartmentFrameworkVersion::where('display_name', 'Type Defaulted Assessment')->firstOrFail();

        $this->assertSame(DepartmentFrameworkVersion::TYPE_FOCUSED, $assessment->framework_type);
        $this->assertGreaterThanOrEqual(1, $assessment->version_number);
    }
}
