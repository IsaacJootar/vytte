<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentShareLink;
use App\Models\AssessmentTier;
use App\Models\DepartmentFrameworkVersion;
use App\Models\Project;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\QuestionType;
use App\Models\QuestionVersion;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\PlatformGovernedDemoSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformAdminControlCenterTest extends TestCase
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

    private function workspaceOwner(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);

        return [$user, $workspace];
    }

    public function test_question_group_schema_replaces_legacy_module_domains(): void
    {
        $this->assertTrue(Schema::hasTable('question_groups'));
        $this->assertFalse(Schema::hasTable('module_domains'));
        $this->assertTrue(Schema::hasColumn('questions', 'question_group_id'));
        $this->assertFalse(Schema::hasColumn('questions', 'module_domain_id'));
    }

    public function test_platform_admin_can_open_control_center_pages(): void
    {
        $admin = $this->platformAdmin();

        foreach ([
            route('admin.dashboard') => 'Vytte Platform Admin Control Center',
            route('admin.official-content.index') => 'Official Vytte Content Control Center',
            route('admin.question-groups.index') => 'Question Groups',
            route('admin.question-identities.index') => 'Reusable Question Identities',
            route('admin.question-versions.index') => 'Question Versions',
            route('admin.framework-versions.index') => 'Framework Versions',
            route('admin.catalogue-releases.index') => 'Catalogue Releases',
            route('admin.facility-profiles.index') => 'Facility Profiles',
            route('admin.scoring-policies.index') => 'Scoring and Aggregation Policies',
            route('admin.platform-users.index') => 'Platform Users and Roles',
            route('admin.assessment-oversight.index') => 'Assessment Oversight',
            route('admin.report-shares.index') => 'Report Share-Link Control',
            route('admin.audit-logs.index') => 'Audit Logs',
        ] as $url => $text) {
            $this->actingAs($admin)->get($url)->assertOk()->assertSee($text);
        }
    }

    public function test_platform_admin_can_create_and_archive_question_group(): void
    {
        $admin = $this->platformAdmin();
        $module = \App\Models\AssessmentModule::firstOrFail();
        $number = ((int) QuestionGroup::where('module_id', $module->module_id)->max('group_number')) + 1;

        $this->actingAs($admin)->post(route('admin.question-groups.store'), [
            'module_id' => $module->module_id,
            'group_number' => $number,
            'group_label' => 'Governance Readiness',
        ])->assertRedirect();

        $group = QuestionGroup::where('group_label', 'Governance Readiness')->firstOrFail();
        $this->assertSame(QuestionGroup::STATUS_ACTIVE, $group->status);

        $this->actingAs($admin)
            ->patch(route('admin.question-groups.archive', $group))
            ->assertRedirect();

        $this->assertSame(QuestionGroup::STATUS_ARCHIVED, $group->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'question_group.archived']);
    }

    public function test_platform_admin_can_create_question_identity_with_first_draft_version(): void
    {
        $admin = $this->platformAdmin();
        $group = QuestionGroup::with('module')->firstOrFail();
        $typeId = \App\Models\QuestionType::where('type_code', 'OPEN_ENDED')->value('type_id');

        $this->actingAs($admin)->post(route('admin.question-identities.store'), [
            'module_id' => $group->module_id,
            'question_group_id' => $group->question_group_id,
            'question_code' => 'ADMIN.Q.NEW',
            'question_text' => 'What should the Platform Admin review?',
            'type_id' => $typeId,
            'is_scored' => '0',
            'methodology_notes' => 'Created through Platform Admin test.',
        ])->assertRedirect();

        $question = Question::where('question_code', 'ADMIN.Q.NEW')->firstOrFail();
        $this->assertSame($group->question_group_id, $question->question_group_id);
        $this->assertDatabaseHas('question_versions', [
            'question_id' => $question->question_id,
            'version_number' => 1,
            'status' => QuestionVersion::STATUS_DRAFT,
        ]);
    }

    public function test_platform_admin_can_approve_question_version(): void
    {
        $admin = $this->platformAdmin();
        $question = Question::firstOrFail();
        $version = QuestionVersion::create([
            'question_id' => $question->question_id,
            'version_number' => ((int) $question->versions()->max('version_number')) + 1,
            'status' => QuestionVersion::STATUS_DRAFT,
            'question_text' => 'Draft wording ready for approval.',
            'type_id' => $question->type_id,
            'requires_observation' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.question-versions.approve', $version))
            ->assertRedirect();

        $this->assertSame(QuestionVersion::STATUS_APPROVED, $version->fresh()->status);
    }

    public function test_platform_admin_can_edit_draft_option_question_version_configuration(): void
    {
        $admin = $this->platformAdmin();
        $question = Question::firstOrFail();
        $typeId = QuestionType::where('type_code', 'SINGLE_SELECT')->value('type_id');
        $version = QuestionVersion::create([
            'question_id' => $question->question_id,
            'version_number' => ((int) $question->versions()->max('version_number')) + 1,
            'status' => QuestionVersion::STATUS_DRAFT,
            'question_text' => 'Draft option wording.',
            'type_id' => $typeId,
            'options' => [],
        ]);

        $this->actingAs($admin)->put(route('admin.question-versions.update', $version), [
            'question_text' => 'Updated option wording.',
            'type_id' => $typeId,
            'options' => [
                ['option_label' => 'No', 'option_order' => 2, 'score_weight' => 0],
                ['option_label' => 'Yes', 'option_order' => 1, 'score_weight' => 100],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $version = $version->fresh();
        $this->assertSame('Updated option wording.', $version->question_text);
        $this->assertSame('Yes', $version->options[0]['option_label']);
        $this->assertEquals(100.0, $version->options[0]['score_weight']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'question.version.configured']);
    }

    public function test_option_question_version_validation_requires_working_options_and_scores(): void
    {
        $admin = $this->platformAdmin();
        $question = Question::firstOrFail();
        $typeId = QuestionType::where('type_code', 'SINGLE_SELECT')->value('type_id');
        $version = QuestionVersion::create([
            'question_id' => $question->question_id,
            'version_number' => ((int) $question->versions()->max('version_number')) + 1,
            'status' => QuestionVersion::STATUS_DRAFT,
            'question_text' => 'Draft option wording.',
            'type_id' => $typeId,
        ]);

        $this->actingAs($admin)->put(route('admin.question-versions.update', $version), [
            'question_text' => 'Invalid option wording.',
            'type_id' => $typeId,
            'options' => [
                ['option_label' => '', 'option_order' => 1, 'score_weight' => 101],
            ],
        ])->assertRedirect()->assertSessionHasErrors(['options.0.option_label', 'options.0.score_weight']);
    }

    public function test_platform_admin_can_edit_draft_numeric_question_version_bands(): void
    {
        $admin = $this->platformAdmin();
        $question = Question::firstOrFail();
        $typeId = QuestionType::where('type_code', 'NUMERIC')->value('type_id');
        $version = QuestionVersion::create([
            'question_id' => $question->question_id,
            'version_number' => ((int) $question->versions()->max('version_number')) + 1,
            'status' => QuestionVersion::STATUS_DRAFT,
            'question_text' => 'Numeric draft.',
            'type_id' => $typeId,
        ]);

        $this->actingAs($admin)->put(route('admin.question-versions.update', $version), [
            'question_text' => 'Average occupancy.',
            'type_id' => $typeId,
            'numeric_min' => 0,
            'numeric_max' => 100,
            'numeric_unit' => 'percent',
            'numeric_step' => 1,
            'numeric_bands' => [
                ['label' => 'Low', 'min_value' => 0, 'max_value' => 49, 'score_weight' => 25, 'display_order' => 1],
                ['label' => 'Healthy', 'min_value' => 50, 'max_value' => 85, 'score_weight' => 100, 'display_order' => 2],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $version = $version->fresh();
        $this->assertSame('percent', $version->numeric_config['unit']);
        $this->assertSame('Healthy', $version->numeric_bands[1]['label']);
        $this->assertEquals(100.0, $version->numeric_bands[1]['score_weight']);
    }

    public function test_published_question_version_cannot_be_edited_and_can_create_successor_draft(): void
    {
        $admin = $this->platformAdmin();
        $version = QuestionVersion::where('status', QuestionVersion::STATUS_PUBLISHED)->firstOrFail();
        $originalText = $version->question_text;

        $this->actingAs($admin)->put(route('admin.question-versions.update', $version), [
            'question_text' => 'Should not save.',
            'type_id' => $version->type_id,
        ])->assertRedirect()->assertSessionHasErrors(['status']);
        $this->assertSame($originalText, $version->fresh()->question_text);

        $this->actingAs($admin)->post(route('admin.question-versions.supersede', $version))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $version = $version->fresh();
        $successor = QuestionVersion::where('parent_version_id', $version->question_version_id)->firstOrFail();
        $this->assertSame(QuestionVersion::STATUS_SUPERSEDED, $version->status);
        $this->assertSame(QuestionVersion::STATUS_DRAFT, $successor->status);
        $this->assertSame($originalText, $successor->question_text);
    }

    public function test_referenced_question_version_archive_is_blocked(): void
    {
        $admin = $this->platformAdmin();
        $placement = \App\Models\FrameworkQuestionPlacement::firstOrFail();
        $version = QuestionVersion::findOrFail($placement->question_version_id);

        $this->actingAs($admin)
            ->patch(route('admin.question-versions.archive', $version))
            ->assertRedirect()
            ->assertSessionHasErrors(['archive']);

        $this->assertSame(QuestionVersion::STATUS_PUBLISHED, $version->fresh()->status);
    }

    public function test_platform_admin_can_create_framework_section_indicator_and_placement(): void
    {
        $admin = $this->platformAdmin();
        $module = \App\Models\AssessmentModule::firstOrFail();
        $publishedQuestionVersion = QuestionVersion::where('status', QuestionVersion::STATUS_PUBLISHED)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.framework-versions.store'), [
            'module_id' => $module->module_id,
            'framework_type' => 'DEPARTMENT',
            'display_name' => 'Beta editable framework',
            'source_authority' => 'Vytte Beta',
            'license_code' => 'INTERNAL-BETA',
        ])->assertRedirect();

        $framework = \App\Models\DepartmentFrameworkVersion::where('display_name', 'Beta editable framework')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.framework-versions.sections.store', $framework), [
            'section_code' => 'BETA_SECTION',
            'section_name' => 'Beta Section',
            'display_order' => 1,
        ])->assertRedirect();
        $section = \App\Models\FrameworkSection::where('framework_version_id', $framework->framework_version_id)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.framework-versions.indicators.store', $framework), [
            'framework_section_id' => $section->framework_section_id,
            'indicator_code' => 'BETA_IND',
            'indicator_name' => 'Beta Indicator',
            'display_order' => 1,
        ])->assertRedirect();
        $indicator = \App\Models\FrameworkIndicator::where('framework_version_id', $framework->framework_version_id)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.framework-versions.placements.store', $framework), [
            'framework_section_id' => $section->framework_section_id,
            'framework_indicator_id' => $indicator->framework_indicator_id,
            'question_version_id' => $publishedQuestionVersion->question_version_id,
            'display_order' => 1,
            'weight' => 1,
            'criticality' => 'STANDARD',
        ])->assertRedirect();

        $this->assertDatabaseHas('framework_question_placements', [
            'framework_version_id' => $framework->framework_version_id,
            'question_version_id' => $publishedQuestionVersion->question_version_id,
        ]);
    }

    public function test_platform_admin_can_create_catalogue_release_and_pin_framework(): void
    {
        $admin = $this->platformAdmin();
        $profile = \App\Models\FacilityProfile::where('status', \App\Models\FacilityProfile::STATUS_PUBLISHED)->firstOrFail();
        $framework = \App\Models\DepartmentFrameworkVersion::where('status', \App\Models\DepartmentFrameworkVersion::STATUS_PUBLISHED)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.catalogue-releases.store'), [
            'release_code' => 'BETA_RELEASE_TEST',
            'release_name' => 'Beta Release Test',
            'creation_path' => 'COMPREHENSIVE',
            'facility_profile_id' => $profile->facility_profile_id,
        ])->assertRedirect();

        $release = \App\Models\AssessmentCatalogueRelease::where('release_code', 'BETA_RELEASE_TEST')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.catalogue-releases.frameworks.attach', $release), [
            'framework_version_id' => $framework->framework_version_id,
            'applicability' => 'DEFAULT',
            'display_order' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('assessment_catalogue_department_versions', [
            'catalogue_release_id' => $release->catalogue_release_id,
            'framework_version_id' => $framework->framework_version_id,
        ]);
    }

    public function test_framework_supersession_clones_structure_and_archive_blocks_dependencies(): void
    {
        $admin = $this->platformAdmin();
        $framework = DepartmentFrameworkVersion::where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->whereHas('questionPlacements')
            ->firstOrFail();
        $sectionCount = $framework->sections()->count();
        $placementCount = $framework->questionPlacements()->count();

        $this->actingAs($admin)
            ->patch(route('admin.framework-versions.archive', $framework))
            ->assertRedirect()
            ->assertSessionHasErrors(['archive']);

        $this->actingAs($admin)
            ->post(route('admin.framework-versions.supersede', $framework))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $successor = DepartmentFrameworkVersion::where('parent_version_id', $framework->framework_version_id)->firstOrFail();
        $this->assertSame(DepartmentFrameworkVersion::STATUS_SUPERSEDED, $framework->fresh()->status);
        $this->assertSame(DepartmentFrameworkVersion::STATUS_DRAFT, $successor->status);
        $this->assertSame($sectionCount, $successor->sections()->count());
        $this->assertSame($placementCount, $successor->questionPlacements()->count());
    }

    public function test_catalogue_release_supersession_preserves_pinned_frameworks_and_archive_blocks_dependencies(): void
    {
        $admin = $this->platformAdmin();
        $release = AssessmentCatalogueRelease::where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)
            ->where('creation_path', 'COMPREHENSIVE')
            ->whereHas('departmentFrameworkVersions')
            ->firstOrFail();
        [$owner, $workspace] = $this->workspaceOwner();
        $project = Project::factory()->create([
            'workspace_id' => $workspace->workspace_id,
            'owner_user_id' => $owner->user_id,
        ]);
        $target = Target::create([
            'owner_workspace_id' => $workspace->workspace_id,
            'target_type_code' => 'HEALTH_FACILITY',
            'name' => 'Governance Clinic',
            'facility_profile_id' => $release->facility_profile_id,
            'uses_departments' => true,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);
        app(\App\Services\AssessmentCreationService::class)->createFromCatalogue($project, $release, creatorId: $owner->user_id);
        $pinnedCount = $release->departmentFrameworkVersions()->count();

        $this->actingAs($admin)
            ->patch(route('admin.catalogue-releases.archive', $release))
            ->assertRedirect()
            ->assertSessionHasErrors(['archive']);

        $this->actingAs($admin)
            ->post(route('admin.catalogue-releases.supersede', $release))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $successor = AssessmentCatalogueRelease::where('parent_release_id', $release->catalogue_release_id)->firstOrFail();
        $this->assertSame(AssessmentCatalogueRelease::STATUS_SUPERSEDED, $release->fresh()->status);
        $this->assertSame(AssessmentCatalogueRelease::STATUS_DRAFT, $successor->status);
        $this->assertSame($pinnedCount, $successor->departmentFrameworkVersions()->count());
    }

    public function test_platform_admin_can_assign_platform_admin_role(): void
    {
        $admin = $this->platformAdmin();
        $user = User::factory()->create(['platform_role' => null]);

        $this->actingAs($admin)
            ->patch(route('admin.platform-users.role', $user), ['platform_role' => 'PLATFORM_ADMIN'])
            ->assertRedirect();

        $this->assertSame('PLATFORM_ADMIN', $user->fresh()->platform_role);
        $this->assertDatabaseHas('audit_logs', ['event' => 'platform.user.role_updated']);
    }

    public function test_platform_admin_can_suspend_workspace(): void
    {
        $admin = $this->platformAdmin();
        $workspace = Workspace::factory()->create(['status' => 'ACTIVE']);

        $this->actingAs($admin)
            ->patch(route('admin.workspaces.status', $workspace), ['status' => 'SUSPENDED'])
            ->assertRedirect();

        $this->assertSame('SUSPENDED', $workspace->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'workspace.status_updated']);
    }

    public function test_platform_admin_can_revoke_report_share_link(): void
    {
        $admin = $this->platformAdmin();
        [$owner, $workspace] = $this->workspaceOwner();
        $project = Project::factory()->create([
            'workspace_id' => $workspace->workspace_id,
            'owner_user_id' => $owner->user_id,
        ]);
        $target = Target::create([
            'owner_workspace_id' => $workspace->workspace_id,
            'target_type_code' => 'HEALTH_FACILITY',
            'name' => 'Admin oversight target',
            'country' => 'Nigeria',
            'region' => 'Cross River',
            'uses_departments' => true,
        ]);
        $assessment = Assessment::factory()->create([
            'target_id' => $target->target_id,
            'project_id' => $project->project_id,
            'assessment_tier_id' => AssessmentTier::firstOrFail()->assessment_tier_id,
        ]);
        $shareLink = AssessmentShareLink::create([
            'assessment_id' => $assessment->assessment_id,
            'token' => Str::random(64),
            'created_by' => $owner->user_id,
            'created_at' => now(),
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.report-shares.revoke', $shareLink))
            ->assertRedirect();

        $this->assertFalse($shareLink->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['event' => 'platform.report_link.revoked']);
    }
}
