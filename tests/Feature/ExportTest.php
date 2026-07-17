<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentModule;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentShareLink;
use App\Models\AssessmentTier;
use App\Models\Project;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\ReportSnapshotService;
use Database\Seeders\PlanFeatureSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlanFeatureSeeder::class);
    }

    private function createWorkspaceWithOwner(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['plan' => 'PRO']);
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);

        return [$user, $workspace];
    }

    private function createCompleteAssessment(Workspace $workspace, ?User $owner = null): Assessment
    {
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Test Community',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);

        $project = Project::factory()->create([
            'workspace_id' => $workspace->workspace_id,
            'owner_user_id' => $owner?->user_id ?? User::factory()->create()->user_id,
        ]);

        $tierId = AssessmentTier::value('assessment_tier_id');

        $assessment = Assessment::create([
            'target_id' => $target->target_id,
            'project_id' => $project->project_id,
            'assessment_tier_id' => $tierId,
            'status' => 'COMPLETE',
            'publish_status' => 'DRAFT',
            'scope_type' => 'FULL',
            'completed_at' => now(),
            'started_at' => now(),
        ]);
        app(ReportSnapshotService::class)->createFor($assessment);

        return $assessment;
    }

    public function test_pdf_download_requires_auth(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner();
        $assessment = $this->createCompleteAssessment($workspace);

        $this->get(route('assessments.export.pdf', $assessment))
            ->assertRedirect(route('login'));
    }

    public function test_reports_index_requires_auth(): void
    {
        $this->get(route('reports.index'))
            ->assertRedirect(route('login'));
    }

    public function test_reports_index_lists_only_completed_assessments_in_active_workspace(): void
    {
        [$userA, $workspaceA] = $this->createWorkspaceWithOwner();
        [$userB, $workspaceB] = $this->createWorkspaceWithOwner();
        $assessmentA = $this->createCompleteAssessment($workspaceA, $userA);
        $assessmentB = $this->createCompleteAssessment($workspaceB, $userB);
        $assessmentA->target->update(['name' => 'Visible Facility']);
        $assessmentB->target->update(['name' => 'Other Workspace Facility']);

        $this->actingAs($userA)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Reports')
            ->assertSee('Visible Facility')
            ->assertDontSee('Other Workspace Facility');
    }

    public function test_reports_index_manages_existing_governed_share_links(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner();
        $assessment = $this->createCompleteAssessment($workspace, $user);

        $this->actingAs($user)
            ->post(route('assessments.share', $assessment))
            ->assertRedirect();

        $shareLink = AssessmentShareLink::where('assessment_id', $assessment->assessment_id)->firstOrFail();

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Manage active share links (1)')
            ->assertSee(route('reports.shared.token', $shareLink->token));
    }

    public function test_pdf_download_returns_pdf_content_type(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner();
        $assessment = $this->createCompleteAssessment($workspace);

        $response = $this->actingAs($user)
            ->get(route('assessments.export.pdf', $assessment));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_pdf_download_has_attachment_header(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner();
        $assessment = $this->createCompleteAssessment($workspace);

        $response = $this->actingAs($user)
            ->get(route('assessments.export.pdf', $assessment));

        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('assessment-report-', $response->headers->get('Content-Disposition'));
    }

    public function test_pdf_blocked_for_assessment_in_other_workspace(): void
    {
        [$userA, $workspaceA] = $this->createWorkspaceWithOwner();
        [$userB, $workspaceB] = $this->createWorkspaceWithOwner();
        $assessmentB = $this->createCompleteAssessment($workspaceB);

        $this->actingAs($userA)
            ->get(route('assessments.export.pdf', $assessmentB))
            ->assertNotFound();
    }

    public function test_csv_download_requires_auth(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner();
        $project = Project::factory()->create(['workspace_id' => $workspace->workspace_id]);

        $this->get(route('projects.export.csv', $project))
            ->assertRedirect(route('login'));
    }

    public function test_csv_download_returns_csv_content_type(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner();
        $project = Project::factory()->create(['workspace_id' => $workspace->workspace_id]);

        $response = $this->actingAs($user)
            ->get(route('projects.export.csv', $project));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_csv_has_correct_column_headers(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner();
        $project = Project::factory()->create(['workspace_id' => $workspace->workspace_id]);

        $response = $this->actingAs($user)
            ->get(route('projects.export.csv', $project));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Assessment ID', $content);
        $this->assertStringContainsString('Target Name', $content);
        $this->assertStringContainsString('Module', $content);
        $this->assertStringContainsString('Assessor', $content);
        $this->assertStringContainsString('Completed At', $content);
        $this->assertStringContainsString('Overall Score', $content);
        $this->assertStringContainsString('Calibration Status', $content);
        $this->assertStringContainsString('Maturity Level', $content);
    }

    public function test_csv_lists_every_in_scope_module(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner();
        $assessment = $this->createCompleteAssessment($workspace, $user);
        foreach (['Leadership', 'Pharmacy'] as $position => $name) {
            $module = AssessmentModule::create([
                'target_type_code' => 'COMMUNITY',
                'module_code' => 'CSV'.($position + 1),
                'module_name' => $name,
                'is_active' => true,
            ]);
            AssessmentModuleScope::create([
                'assessment_id' => $assessment->assessment_id,
                'module_id' => $module->module_id,
                'in_scope' => true,
                'is_category_default' => true,
                'status' => 'COMPLETED',
            ]);
        }

        $content = $this->actingAs($user)
            ->get(route('projects.export.csv', $assessment->project_id))
            ->streamedContent();

        $this->assertStringContainsString('Leadership | Pharmacy', $content);
    }

    public function test_csv_blocked_for_project_in_other_workspace(): void
    {
        [$userA, $workspaceA] = $this->createWorkspaceWithOwner();
        [$userB, $workspaceB] = $this->createWorkspaceWithOwner();
        $projectB = Project::factory()->create(['workspace_id' => $workspaceB->workspace_id]);

        $this->actingAs($userA)
            ->get(route('projects.export.csv', $projectB))
            ->assertNotFound();
    }

    public function test_create_share_link_requires_auth(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner();
        $assessment = $this->createCompleteAssessment($workspace);

        $this->post(route('assessments.share', $assessment))
            ->assertRedirect(route('login'));
    }

    public function test_create_share_link_returns_signed_url_in_session(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner();
        $assessment = $this->createCompleteAssessment($workspace);

        $response = $this->actingAs($user)
            ->post(route('assessments.share', $assessment));

        $response->assertRedirect();
        $response->assertSessionHas('share_link');

        $link = $response->getSession()->get('share_link');
        $this->assertStringContainsString('/shared-reports/', $link);
        $this->assertDatabaseHas('assessment_share_links', [
            'assessment_id' => $assessment->assessment_id,
            'created_by' => $user->user_id,
            'is_active' => true,
        ]);
    }

    public function test_share_link_blocked_for_other_workspace_assessment(): void
    {
        [$userA, $workspaceA] = $this->createWorkspaceWithOwner();
        [$userB, $workspaceB] = $this->createWorkspaceWithOwner();
        $assessmentB = $this->createCompleteAssessment($workspaceB);

        $this->actingAs($userA)
            ->post(route('assessments.share', $assessmentB))
            ->assertNotFound();
    }

    public function test_shared_report_resolves_with_valid_signed_url(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner();
        $assessment = $this->createCompleteAssessment($workspace);

        $signedUrl = URL::temporarySignedRoute(
            'reports.shared',
            now()->addDays(30),
            ['assessment' => $assessment->assessment_id]
        );

        $this->get($signedUrl)->assertOk();
    }

    public function test_shared_report_rejected_with_invalid_signature(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner();
        $assessment = $this->createCompleteAssessment($workspace);

        $this->get(route('reports.shared', $assessment).'?signature=bad&expires='.now()->addDays(30)->timestamp)
            ->assertForbidden();
    }

    public function test_shared_report_rejected_with_expired_url(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner();
        $assessment = $this->createCompleteAssessment($workspace);

        $expiredUrl = URL::temporarySignedRoute(
            'reports.shared',
            now()->subMinutes(1),
            ['assessment' => $assessment->assessment_id]
        );

        $this->get($expiredUrl)->assertForbidden();
    }

    public function test_shared_report_404_for_incomplete_assessment(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner();
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Test Target',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project = Project::factory()->create([
            'workspace_id' => $workspace->workspace_id,
            'owner_user_id' => $user->user_id,
        ]);
        $tierId = AssessmentTier::value('assessment_tier_id');
        $assessment = Assessment::create([
            'target_id' => $target->target_id,
            'project_id' => $project->project_id,
            'assessment_tier_id' => $tierId,
            'status' => 'IN_PROGRESS',
            'publish_status' => 'DRAFT',
            'scope_type' => 'FULL',
            'started_at' => now(),
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'reports.shared',
            now()->addDays(30),
            ['assessment' => $assessment->assessment_id]
        );

        $this->get($signedUrl)->assertNotFound();
    }

    public function test_shared_report_accessible_without_auth(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner();
        $assessment = $this->createCompleteAssessment($workspace);

        $signedUrl = URL::temporarySignedRoute(
            'reports.shared',
            now()->addDays(30),
            ['assessment' => $assessment->assessment_id]
        );

        // No actingAs — unauthenticated request must succeed
        $this->get($signedUrl)->assertOk();
    }

    public function test_persistent_report_link_is_audited_and_revocable(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithOwner();
        $assessment = $this->createCompleteAssessment($workspace, $user);
        $this->actingAs($user)->post(route('assessments.share', $assessment));
        $shareLink = AssessmentShareLink::firstOrFail();

        $this->get(route('reports.shared.token', $shareLink->token))->assertOk();
        $this->assertSame(1, $shareLink->fresh()->use_count);
        $this->assertNotNull($shareLink->fresh()->last_used_at);

        $this->actingAs($user)
            ->delete(route('assessments.share.revoke', [$assessment, $shareLink]))
            ->assertRedirect();
        $this->get(route('reports.shared.token', $shareLink->token))->assertNotFound();
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'assessment.report_link.revoked',
            'auditable_id' => $assessment->assessment_id,
        ]);
    }
}
