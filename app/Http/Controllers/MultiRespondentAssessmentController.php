<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\PublicResponseSession;
use App\Models\WorkspaceMember;
use App\Notifications\AssessmentCompletedNotification;
use App\Services\AuditService;
use App\Services\MultiRespondentAggregationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class MultiRespondentAssessmentController extends Controller
{
    public function show(Assessment $assessment, MultiRespondentAggregationService $aggregation): View
    {
        $this->authorize('view', $assessment);
        $preview = $aggregation->preview($assessment);
        $assessment->load([
            'project',
            'target',
            'aggregationResult',
            'publicResponseSessions.scoreResult',
            'publicResponseSessions.accessToken',
        ]);

        return view('assessments.respondent-collection', compact('assessment', 'preview'));
    }

    public function classify(
        Request $request,
        Assessment $assessment,
        PublicResponseSession $responseSession,
    ): RedirectResponse {
        $this->authorize('finalizeMultiRespondent', $assessment);
        abort_unless($responseSession->assessment_id === $assessment->assessment_id, 404);

        $validated = $request->validate([
            'classification' => ['required', 'in:ELIGIBLE,EXCLUDED,TEST'],
            'reason' => ['nullable', 'required_unless:classification,ELIGIBLE', 'string', 'max:1000'],
        ]);
        $classification = $validated['classification'];
        DB::transaction(function () use ($assessment, $responseSession, $classification, $validated): void {
            $lockedAssessment = Assessment::whereKey($assessment->assessment_id)->lockForUpdate()->firstOrFail();
            $lockedSession = PublicResponseSession::whereKey($responseSession->session_id)->lockForUpdate()->firstOrFail();
            abort_if($lockedAssessment->isComplete() || $lockedSession->submitted_at === null, 422);

            $previousClassification = [
                'session_id' => $lockedSession->session_id,
                'eligibility_status' => $lockedSession->eligibility_status,
                'eligibility_reason' => $lockedSession->eligibility_reason,
                'is_test' => $lockedSession->is_test,
            ];
            $lockedSession->update([
                'eligibility_status' => $classification === 'ELIGIBLE' ? 'ELIGIBLE' : 'EXCLUDED',
                'eligibility_reason' => $classification === 'ELIGIBLE' ? null : trim($validated['reason']),
                'is_test' => $classification === 'TEST',
                'eligibility_reviewed_by' => auth()->id(),
                'eligibility_reviewed_at' => now(),
            ]);
            app(AuditService::class)->record(
                'assessment.respondent_session.classified',
                $lockedAssessment,
                oldValues: $previousClassification,
                newValues: [
                    'session_id' => $lockedSession->session_id,
                    'eligibility_status' => $lockedSession->eligibility_status,
                    'eligibility_reason' => $lockedSession->eligibility_reason,
                    'is_test' => $lockedSession->is_test,
                ],
            );
        });

        return back()->with('success', 'Respondent eligibility classification updated.');
    }

    public function finalize(
        Assessment $assessment,
        MultiRespondentAggregationService $aggregation,
    ): RedirectResponse {
        $this->authorize('finalizeMultiRespondent', $assessment);
        $aggregation->finalize($assessment, auth()->id());

        $admins = WorkspaceMember::where('workspace_id', app('current.workspace')->workspace_id)
            ->whereIn('role', ['OWNER', 'ADMIN'])
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();
        Notification::send($admins, new AssessmentCompletedNotification($assessment->fresh()));

        return redirect()->route('assessments.results', $assessment)
            ->with('success', 'The respondent collection was finalized and its immutable report was created.');
    }
}
