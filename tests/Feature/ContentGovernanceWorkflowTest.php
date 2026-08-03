<?php

namespace Tests\Feature;

use App\Models\AssessmentModule;
use App\Models\ContentAssistanceRun;
use App\Models\ContentContribution;
use App\Models\ContentGovernanceClaim;
use App\Models\ContentPublisher;
use App\Models\ContentReviewAssignment;
use App\Models\DepartmentFrameworkVersion;
use App\Models\QuestionVersion;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Scopes\WorkspaceScope;
use App\Services\Ai\AiChatClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentGovernanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceUser(?Workspace $workspace = null): array
    {
        $workspace ??= Workspace::factory()->create();
        $user = User::factory()->create(['active_workspace_id' => $workspace->workspace_id]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
        app()->instance('current.workspace', $workspace);

        return [$user, $workspace];
    }

    private function admin(): User
    {
        return User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);
    }

    private function draftFramework(): DepartmentFrameworkVersion
    {
        return DepartmentFrameworkVersion::create([
            'module_id' => AssessmentModule::where('is_active', true)->orderBy('module_id')->value('module_id'),
            'version_number' => 1,
            'status' => DepartmentFrameworkVersion::STATUS_DRAFT,
            'display_name' => 'Governance workflow test',
            'purpose' => 'Test a governed assessment before publishing.',
            'source_authority' => 'Clinical review group',
        ]);
    }

    public function test_workspace_expert_can_submit_a_question_without_exposing_it_to_another_workspace(): void
    {
        [$author, $workspace] = $this->workspaceUser();

        $this->actingAs($author)->post(route('contributions.store'), [
            'title' => 'Cold-chain monitoring question',
            'question_text' => 'Was the vaccine refrigerator temperature recorded today?',
            'response_format' => 'yes_no',
            'intended_use' => 'Daily immunization service readiness review.',
            'source_authority' => 'Facility immunization lead',
        ])->assertRedirect(route('contributions.index'))->assertSessionHasNoErrors();

        $contribution = ContentContribution::firstOrFail();
        $this->assertSame($workspace->workspace_id, $contribution->workspace_id);
        $this->assertSame('SUBMITTED', $contribution->status);

        [$outsider, $otherWorkspace] = $this->workspaceUser();
        app()->instance('current.workspace', $otherWorkspace);
        $this->actingAs($outsider)->get(route('contributions.index'))
            ->assertOk()
            ->assertDontSee('Cold-chain monitoring question');
    }

    public function test_accepted_contribution_is_promoted_only_to_a_private_draft_question(): void
    {
        [$author, $workspace] = $this->workspaceUser();
        $contribution = ContentContribution::create([
            'workspace_id' => $workspace->workspace_id,
            'submitted_by' => $author->user_id,
            'title' => 'Medicine stock question',
            'question_text' => 'Are first-line medicines currently in stock?',
            'response_format' => 'yes_no_na',
            'answer_options' => ['Yes', 'No', 'Not applicable'],
            'intended_use' => 'Routine service readiness review.',
            'status' => 'SUBMITTED',
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.contributions.review', $contribution->content_contribution_id), [
            'status' => 'ACCEPTED',
            'review_notes' => 'Clear purpose and safe response options.',
        ])->assertSessionHasNoErrors();

        $publisher = ContentPublisher::where('publisher_code', 'VYTTE')->firstOrFail();
        $module = AssessmentModule::where('is_active', true)->orderBy('module_id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.contributions.promote', $contribution->content_contribution_id), [
            'module_id' => $module->module_id,
            'content_publisher_id' => $publisher->content_publisher_id,
        ])->assertSessionHasNoErrors();

        $contribution = ContentContribution::withoutGlobalScope(WorkspaceScope::class)->findOrFail($contribution->content_contribution_id);
        $version = QuestionVersion::findOrFail($contribution->promoted_question_version_id);
        $this->assertSame('PROMOTED', $contribution->status);
        $this->assertSame(QuestionVersion::STATUS_DRAFT, $version->status);
        $this->assertSame(ContentPublisher::VISIBILITY_PRIVATE, $version->question->distribution_level);
        $this->assertFalse($version->question->is_scored);
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->workspace_id,
            'event' => 'content.contribution.promoted',
        ]);
    }

    public function test_contribution_cannot_be_promoted_before_acceptance(): void
    {
        [$author, $workspace] = $this->workspaceUser();
        $contribution = ContentContribution::create([
            'workspace_id' => $workspace->workspace_id,
            'submitted_by' => $author->user_id,
            'title' => 'Unreviewed question',
            'question_text' => 'Is this ready?',
            'response_format' => 'yes_no',
            'answer_options' => ['Yes', 'No'],
            'intended_use' => 'Governance test.',
            'status' => 'SUBMITTED',
        ]);

        $this->actingAs($this->admin())->post(route('admin.contributions.promote', $contribution->content_contribution_id), [
            'module_id' => AssessmentModule::where('is_active', true)->orderBy('module_id')->value('module_id'),
            'content_publisher_id' => ContentPublisher::where('publisher_code', 'VYTTE')->value('content_publisher_id'),
        ])->assertSessionHasErrors('status');

        $this->assertSame('SUBMITTED', $contribution->fresh()->status);
        $this->assertNull($contribution->fresh()->promoted_question_version_id);
    }

    public function test_quality_review_requires_assignment_evidence_and_an_independent_decision(): void
    {
        $admin = $this->admin();
        $reviewer = $this->admin();
        $framework = $this->draftFramework();

        $this->actingAs($admin)->get(route('admin.assessments.quality', $framework))
            ->assertOk()
            ->assertSee('AI can flag wording and burden concerns. It cannot edit, approve, score, or publish.')
            ->assertSee('Source Verified')
            ->assertSee('Benchmark Approved');

        $this->actingAs($admin)->put(route('admin.assessments.quality.claim', [$framework, 'SOURCE_VERIFIED']), [
            'status' => 'PASSED',
            'evidence_summary' => 'A self-approved claim must be refused.',
        ])->assertSessionHasErrors('review');

        $this->actingAs($admin)->post(route('admin.assessments.quality.assign', [$framework, 'SOURCE_VERIFIED']), [
            'reviewer_id' => $reviewer->user_id,
        ])->assertSessionHasNoErrors();
        $assignment = ContentReviewAssignment::where('framework_version_id', $framework->framework_version_id)->firstOrFail();
        $this->assertSame('ASSIGNED', $assignment->status);

        $this->actingAs($reviewer)->put(route('admin.assessments.quality.submit', [$framework, $assignment]), [
            'recommendation' => 'PASSED',
            'evidence_summary' => 'The reviewer checked the cited source and retained a record.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('SUBMITTED', $assignment->fresh()->status);

        $this->actingAs($admin)->put(route('admin.assessments.quality.decide', [$framework, $assignment]), [
            'decision' => 'APPROVE',
            'decision_notes' => 'Evidence is sufficient and traceable.',
        ])->assertSessionHasNoErrors();

        $claim = ContentGovernanceClaim::where('content_id', $framework->framework_version_id)
            ->where('claim_type', 'SOURCE_VERIFIED')->firstOrFail();
        $this->assertSame('PASSED', $claim->status);
        $this->assertSame($reviewer->user_id, $claim->reviewed_by);
        $this->assertSame('APPROVED', $assignment->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'assessment.governance_review.assigned']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'assessment.governance_review.submitted']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'assessment.governance_review.decided']);
    }

    public function test_assigned_reviewer_cannot_approve_their_own_review(): void
    {
        $admin = $this->admin();
        $reviewer = $this->admin();
        $framework = $this->draftFramework();

        $this->actingAs($admin)->post(route('admin.assessments.quality.assign', [$framework, 'SCORING_REVIEWED']), [
            'reviewer_id' => $reviewer->user_id,
        ]);
        $assignment = ContentReviewAssignment::firstOrFail();
        $this->actingAs($reviewer)->put(route('admin.assessments.quality.submit', [$framework, $assignment]), [
            'recommendation' => 'PASSED',
            'evidence_summary' => 'Scoring rules were independently reproduced.',
        ]);

        $this->actingAs($reviewer)->put(route('admin.assessments.quality.decide', [$framework, $assignment]), [
            'decision' => 'APPROVE',
        ])->assertForbidden();
        $this->assertSame('SUBMITTED', $assignment->fresh()->status);
    }

    public function test_deterministic_and_ai_checks_are_advisory_records_and_do_not_publish(): void
    {
        $admin = $this->admin();
        $framework = $this->draftFramework();
        app()->instance(AiChatClient::class, new class implements AiChatClient
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function message(string $system, string $user, int $maxTokens = 1024): string
            {
                return 'WARNING: Define the reporting timeframe before human approval.';
            }

            public function model(): string
            {
                return 'governance-test-model';
            }
        });

        $this->actingAs($admin)->post(route('admin.assessments.quality.lint', $framework))
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.assessments.quality.ai', $framework))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('content_assistance_runs', [
            'framework_version_id' => $framework->framework_version_id,
            'run_type' => 'DETERMINISTIC_LINT',
            'status' => 'COMPLETE',
        ]);
        $this->assertDatabaseHas('content_assistance_runs', [
            'framework_version_id' => $framework->framework_version_id,
            'run_type' => 'AI_LINT',
            'status' => 'ADVISORY_ONLY',
            'model' => 'governance-test-model',
        ]);
        $this->assertSame(2, ContentAssistanceRun::where('framework_version_id', $framework->framework_version_id)->count());
        $this->assertSame(DepartmentFrameworkVersion::STATUS_DRAFT, $framework->fresh()->status);
    }
}
