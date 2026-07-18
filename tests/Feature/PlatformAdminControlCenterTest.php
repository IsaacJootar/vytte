<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentShareLink;
use App\Models\AssessmentTier;
use App\Models\Project;
use App\Models\Question;
use App\Models\QuestionGroup;
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
