<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentRespondentToken;
use App\Services\AuditService;
use App\Services\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RespondentLinkController extends Controller
{
    /**
     * Open a draft for collection and make its first respondent link in one deliberate action.
     * Locking the assessment makes retries idempotent instead of silently creating extra links.
     */
    public function openAndCreate(Assessment $assessment): RedirectResponse
    {
        $this->authorizeWorkspaceAssessment($assessment);
        $workspace = app('current.workspace');

        if (! PlanService::workspaceCanAccess($workspace, 'shareable_public_links')) {
            return back()->with('error', 'Shareable respondent links are not available on your current plan. Upgrade to share assessments with external respondents.');
        }

        if (! $assessment->snapshot()->exists()) {
            return back()->with('error', 'This assessment has no governed content and cannot be opened.');
        }

        $result = DB::transaction(function () use ($assessment): array {
            $lockedAssessment = Assessment::query()
                ->whereKey($assessment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAssessment->isComplete() || $lockedAssessment->isClosed()) {
                return ['token' => null, 'created' => false];
            }

            if ($lockedAssessment->isDraft()) {
                $lockedAssessment->markPublished(auth()->id());
                app(AuditService::class)->record('assessment.published', $lockedAssessment, ['publish_status' => Assessment::PUBLISH_DRAFT], [
                    'publish_status' => Assessment::PUBLISH_PUBLISHED,
                ]);
            }

            $respondentToken = AssessmentRespondentToken::query()
                ->where('assessment_id', $lockedAssessment->assessment_id)
                ->usable()
                ->oldest()
                ->first();

            if ($respondentToken !== null) {
                return ['token' => $respondentToken, 'created' => false];
            }

            $respondentToken = AssessmentRespondentToken::create([
                'token' => Str::random(32),
                'assessment_id' => $lockedAssessment->assessment_id,
                'created_by' => auth()->id(),
            ]);
            app(AuditService::class)->record('assessment.respondent_link.created', $lockedAssessment, newValues: [
                'token_prefix' => substr($respondentToken->token, 0, 8),
            ]);

            return ['token' => $respondentToken, 'created' => true];
        });

        if ($result['token'] === null) {
            return redirect()->route('assessments.respondent-collection', $assessment)
                ->with('error', 'This assessment is no longer open for new responses.');
        }

        $message = $result['created']
            ? 'Assessment opened and respondent link created. Copy it below to start collecting responses.'
            : 'This assessment is already open. Its respondent link is ready below.';

        return redirect()->route('assessments.respondent-collection', $assessment)
            ->with('success', $message)
            ->with('respondent_link', route('respondent.show', $result['token']->token));
    }

    public function store(Assessment $assessment): RedirectResponse
    {
        $this->authorizeWorkspaceAssessment($assessment);
        $workspace = app('current.workspace');

        if (! PlanService::workspaceCanAccess($workspace, 'shareable_public_links')) {
            return back()->with('error', 'Shareable respondent links are not available on your current plan. Upgrade to share assessments with external respondents.');
        }

        $result = DB::transaction(function () use ($assessment): array {
            $lockedAssessment = Assessment::query()
                ->whereKey($assessment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedAssessment->isCollecting()) {
                return ['token' => null, 'created' => false];
            }

            $respondentToken = AssessmentRespondentToken::query()
                ->where('assessment_id', $lockedAssessment->assessment_id)
                ->usable()
                ->oldest()
                ->first();

            if ($respondentToken !== null) {
                return ['token' => $respondentToken, 'created' => false];
            }

            $respondentToken = AssessmentRespondentToken::create([
                'token' => Str::random(32),
                'assessment_id' => $lockedAssessment->assessment_id,
                'created_by' => auth()->id(),
            ]);
            app(AuditService::class)->record('assessment.respondent_link.created', $lockedAssessment, newValues: [
                'token_prefix' => substr($respondentToken->token, 0, 8),
            ]);

            return ['token' => $respondentToken, 'created' => true];
        });

        if ($result['token'] === null) {
            return back()->with('error', 'Open this assessment for responses before creating a respondent link.');
        }

        $message = $result['created']
            ? 'A replacement respondent link was created. Copy it below to continue collecting responses.'
            : 'The existing respondent link is ready below. One link can collect responses from many people.';

        return back()
            ->with('success', $message)
            ->with('respondent_link', route('respondent.show', $result['token']->token));
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

        return back()->with('success', 'The respondent link has been deactivated. Completed responses remain available for review.');
    }

    private function authorizeWorkspaceAssessment(Assessment $assessment): void
    {
        $this->authorize('view', $assessment);
    }
}
