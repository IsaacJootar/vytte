<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentAssistanceRun;
use App\Models\ContentGovernanceClaim;
use App\Models\DepartmentFrameworkVersion;
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

        return view('admin.assessment-builder.quality', [
            'assessment' => $assessment->load(['contentPublisher', 'sections.questionPlacements.questionVersion.questionType']),
            'claimTypes' => ContentGovernanceClaim::CLAIM_TYPES,
            'claims' => $claims,
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
        abort_unless(in_array($claimType, ContentGovernanceClaim::CLAIM_TYPES, true), 404);
        $validated = $request->validate([
            'status' => ['required', Rule::in(ContentGovernanceClaim::STATUSES)],
            'evidence_summary' => ['nullable', 'string', 'max:5000'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);
        if (in_array($validated['status'], ['PASSED', 'FAILED'], true) && blank($validated['evidence_summary'] ?? null)) {
            return back()->withErrors(['evidence_summary' => 'Explain the evidence for a passed or failed review.']);
        }

        $claim = ContentGovernanceClaim::updateOrCreate(
            ['content_type' => 'FRAMEWORK_VERSION', 'content_id' => $assessment->framework_version_id, 'claim_type' => $claimType],
            [
                'content_publisher_id' => $assessment->content_publisher_id,
                ...$validated,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ],
        );
        $audit->record('assessment.governance_claim.reviewed', $assessment, newValues: [
            'claim_type' => $claimType,
            'status' => $claim->status,
            'content_governance_claim_id' => $claim->content_governance_claim_id,
        ]);

        return back()->with('success', str($claimType)->replace('_', ' ')->lower()->ucfirst().' review saved.');
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
