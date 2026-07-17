<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentRespondentToken;
use App\Services\AuditService;
use App\Services\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class RespondentLinkController extends Controller
{
    public function store(Assessment $assessment): RedirectResponse
    {
        $this->authorizeWorkspaceAssessment($assessment);
        $workspace = app('current.workspace');

        if (! PlanService::workspaceCanAccess($workspace, 'shareable_public_links')) {
            return back()->with('error', 'Shareable respondent links are not available on your current plan. Upgrade to share assessments with external respondents.');
        }

        if ($assessment->status !== Assessment::STATUS_IN_PROGRESS) {
            return back()->with('error', 'Respondent links can only be created for in-progress assessments.');
        }

        $token = Str::random(32);

        $respondentToken = AssessmentRespondentToken::create([
            'token' => $token,
            'assessment_id' => $assessment->assessment_id,
            'created_by' => auth()->id(),
        ]);
        app(AuditService::class)->record('assessment.respondent_link.created', $assessment, newValues: [
            'token_prefix' => substr($respondentToken->token, 0, 8),
        ]);

        return back()->with('respondent_link', route('respondent.show', $token));
    }

    public function destroy(
        Assessment $assessment,
        AssessmentRespondentToken $respondentToken
    ): RedirectResponse {
        $this->authorizeWorkspaceAssessment($assessment);

        if ($respondentToken->assessment_id !== $assessment->assessment_id) {
            abort(404);
        }

        $respondentToken->update(['revoked_at' => now()]);
        app(AuditService::class)->record('assessment.respondent_link.revoked', $assessment, newValues: [
            'token_prefix' => substr($respondentToken->token, 0, 8),
        ]);

        return back()->with('success', 'The respondent link has been deactivated. Existing submitted responses were preserved.');
    }

    private function authorizeWorkspaceAssessment(Assessment $assessment): void
    {
        $this->authorize('view', $assessment);
    }
}
