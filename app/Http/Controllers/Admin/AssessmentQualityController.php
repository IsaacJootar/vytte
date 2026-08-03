<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentAssistanceRun;
use App\Models\ContentGovernanceClaim;
use App\Models\ContentReviewAssignment;
use App\Models\DepartmentFrameworkVersion;
use App\Models\User;
use App\Services\Ai\AssessmentAuthoringAssistant;
use App\Services\AssessmentAuthoringLintService;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssessmentQualityController extends Controller
{
    public function show(DepartmentFrameworkVersion $assessment, AssessmentAuthoringLintService $lint, AssessmentAuthoringAssistant $ai): View
    {
        $claims = ContentGovernanceClaim::where('content_type', 'FRAMEWORK_VERSION')
            ->where('content_id', $assessment->framework_version_id)
            ->get()->keyBy('claim_type');
        $assignments = ContentReviewAssignment::with(['assigner', 'reviewer', 'decisionMaker'])
            ->where('framework_version_id', $assessment->framework_version_id)
            ->get()->keyBy('claim_type');

        return view('admin.assessment-builder.quality', [
            'assessment' => $assessment->load(['contentPublisher', 'sections.questionPlacements.questionVersion.questionType']),
            'claimTypes' => ContentGovernanceClaim::CLAIM_TYPES,
            'claims' => $claims,
            'assignments' => $assignments,
            'reviewers' => User::where('platform_role', 'PLATFORM_ADMIN')->where('user_id', '!=', auth()->id())->orderBy('name')->get(['user_id', 'name', 'email']),
            'baselineFindings' => $lint->lint($assessment),
            'assistanceRuns' => ContentAssistanceRun::where('framework_version_id', $assessment->framework_version_id)->latest()->limit(10)->get(),
            'aiAvailable' => $ai->isAvailable(),
            'steps' => AssessmentBuilderController::STEPS,
            'currentStep' => 'review',
            'isEditable' => $assessment->status === DepartmentFrameworkVersion::STATUS_DRAFT,
        ]);
    }

    public function updateClaim(Request $request, DepartmentFrameworkVersion $assessment, string $claimType, AuditService $audit): RedirectResponse
    {
        return back()->withErrors(['review' => 'Trust decisions use the independent assignment workflow. Assign a reviewer below.']);
    }

    public function assignReview(Request $request, DepartmentFrameworkVersion $assessment, string $claimType, AuditService $audit): RedirectResponse
    {
        abort_unless(in_array($claimType, ContentGovernanceClaim::CLAIM_TYPES, true), 404);
        abort_unless($assessment->status === DepartmentFrameworkVersion::STATUS_DRAFT, 422);
        $validated = $request->validate([
            'reviewer_id' => ['required', 'uuid', Rule::exists('users', 'user_id')->where('platform_role', 'PLATFORM_ADMIN'), Rule::notIn([auth()->id()])],
        ], ['reviewer_id.not_in' => 'Choose another administrator so the review is independent.']);

        $assignment = ContentReviewAssignment::updateOrCreate(
            ['framework_version_id' => $assessment->framework_version_id, 'claim_type' => $claimType],
            [
                'assigned_by' => auth()->id(),
                'reviewer_id' => $validated['reviewer_id'],
                'status' => 'ASSIGNED',
                'recommendation' => null,
                'evidence_summary' => null,
                'reviewer_notes' => null,
                'submitted_at' => null,
                'decided_by' => null,
                'decision_notes' => null,
                'decided_at' => null,
            ],
        );
        ContentGovernanceClaim::updateOrCreate(
            ['content_type' => 'FRAMEWORK_VERSION', 'content_id' => $assessment->framework_version_id, 'claim_type' => $claimType],
            ['content_publisher_id' => $assessment->content_publisher_id, 'status' => 'PENDING'],
        );
        $audit->record('assessment.governance_review.assigned', $assessment, newValues: ['assignment_id' => $assignment->content_review_assignment_id, 'claim_type' => $claimType, 'reviewer_id' => $validated['reviewer_id']]);

        return back()->with('success', str($claimType)->replace('_', ' ')->title().' review assigned.');
    }

    public function submitReview(Request $request, DepartmentFrameworkVersion $assessment, ContentReviewAssignment $assignment, AuditService $audit): RedirectResponse
    {
        abort_unless($assignment->framework_version_id === $assessment->framework_version_id, 404);
        abort_unless($assignment->reviewer_id === auth()->id(), 403);
        abort_unless(in_array($assignment->status, ['ASSIGNED', 'CHANGES_REQUESTED'], true), 422);
        $validated = $request->validate([
            'recommendation' => ['required', Rule::in(['PASSED', 'FAILED'])],
            'evidence_summary' => ['required', 'string', 'max:5000'],
            'reviewer_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $assignment->update([...$validated, 'status' => 'SUBMITTED', 'submitted_at' => now()]);
        $audit->record('assessment.governance_review.submitted', $assessment, newValues: ['assignment_id' => $assignment->content_review_assignment_id, 'recommendation' => $validated['recommendation']]);

        return back()->with('success', 'Review evidence submitted for an independent decision.');
    }

    public function decideReview(Request $request, DepartmentFrameworkVersion $assessment, ContentReviewAssignment $assignment, AuditService $audit): RedirectResponse
    {
        abort_unless($assignment->framework_version_id === $assessment->framework_version_id, 404);
        abort_unless($assignment->reviewer_id !== auth()->id(), 403);
        abort_unless($assignment->status === 'SUBMITTED', 422);
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['APPROVE', 'REQUEST_CHANGES'])],
            'decision_notes' => ['nullable', 'string', 'max:5000'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);
        if ($validated['decision'] === 'REQUEST_CHANGES' && blank($validated['decision_notes'] ?? null)) {
            return back()->withErrors(['decision_notes' => 'Explain what the reviewer must change.']);
        }

        $approved = $validated['decision'] === 'APPROVE';
        $assignment->update([
            'status' => $approved ? 'APPROVED' : 'CHANGES_REQUESTED',
            'decided_by' => auth()->id(),
            'decision_notes' => $validated['decision_notes'] ?? null,
            'decided_at' => now(),
        ]);
        $claim = ContentGovernanceClaim::updateOrCreate(
            ['content_type' => 'FRAMEWORK_VERSION', 'content_id' => $assessment->framework_version_id, 'claim_type' => $assignment->claim_type],
            [
                'content_publisher_id' => $assessment->content_publisher_id,
                'status' => $approved ? $assignment->recommendation : 'PENDING',
                'evidence_summary' => $approved ? $assignment->evidence_summary : null,
                'metadata' => ['assignment_id' => $assignment->content_review_assignment_id, 'decision_notes' => $validated['decision_notes'] ?? null],
                'reviewed_by' => $approved ? $assignment->reviewer_id : null,
                'reviewed_at' => $approved ? $assignment->submitted_at : null,
                'expires_at' => $approved ? ($validated['expires_at'] ?? null) : null,
            ],
        );
        $audit->record('assessment.governance_review.decided', $assessment, newValues: ['assignment_id' => $assignment->content_review_assignment_id, 'claim_id' => $claim->content_governance_claim_id, 'decision' => $validated['decision']]);

        return back()->with('success', $approved ? 'Independent review approved and trust signal updated.' : 'Changes requested from the assigned reviewer.');
    }

    public function runLint(DepartmentFrameworkVersion $assessment, AssessmentAuthoringLintService $lint): RedirectResponse
    {
        ContentAssistanceRun::create([
            'framework_version_id' => $assessment->framework_version_id,
            'run_type' => 'DETERMINISTIC_LINT',
            'status' => 'COMPLETE',
            'source_hash' => $lint->sourceHash($assessment),
            'findings' => $lint->lint($assessment),
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Quality checks rerun against the current draft.');
    }

    public function runAi(DepartmentFrameworkVersion $assessment, AssessmentAuthoringLintService $lint, AssessmentAuthoringAssistant $ai): RedirectResponse
    {
        try {
            $result = $ai->lint($assessment);
        } catch (\Throwable) {
            return back()->withErrors(['ai' => 'AI review is unavailable right now. Deterministic checks and human review still work.']);
        }
        ContentAssistanceRun::create([
            'framework_version_id' => $assessment->framework_version_id,
            'run_type' => 'AI_LINT',
            'status' => 'ADVISORY_ONLY',
            'source_hash' => $lint->sourceHash($assessment),
            'findings' => $result['findings'],
            'model' => $result['model'],
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'AI advisory review added. A human reviewer must still make every decision.');
    }
}
