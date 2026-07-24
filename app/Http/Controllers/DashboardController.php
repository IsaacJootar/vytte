<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentAction;
use App\Models\Project;
use App\Models\PublicResponseSession;
use App\Services\Reporting\ReportComposer;
use App\Services\ReportSnapshotService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): View
    {
        // All project IDs scoped to the current workspace (WorkspaceScope applies automatically)
        $workspaceProjectIds = Project::select('project_id');

        $activeProjectCount = Project::where('status', 'ACTIVE')->count();

        $totalAssessments = Assessment::whereIn('project_id', $workspaceProjectIds)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->count();

        $avgScore = null;
        if ($totalAssessments > 0) {
            $avgScore = DB::table('assessment_scores as s')
                ->join('assessments as a', 'a.assessment_id', '=', 's.assessment_id')
                ->whereIn('a.project_id', $workspaceProjectIds)
                ->whereNotNull('s.overall_score')
                ->avg('s.overall_score');

            $avgScore = $avgScore !== null ? round((float) $avgScore, 1) : null;
        }

        // Score distribution counts
        $allScores = DB::table('assessment_scores as s')
            ->join('assessments as a', 'a.assessment_id', '=', 's.assessment_id')
            ->whereIn('a.project_id', $workspaceProjectIds)
            ->whereNotNull('s.overall_score')
            ->pluck('s.overall_score')
            ->map(fn ($s) => (float) $s);

        $distribution = [
            'strong' => $allScores->filter(fn ($s) => $s >= 70)->count(),
            'moderate' => $allScores->filter(fn ($s) => $s >= 45 && $s < 70)->count(),
            'weak' => $allScores->filter(fn ($s) => $s < 45)->count(),
        ];

        $recentProjects = Project::with(['targets.targetType'])
            ->where('status', 'ACTIVE')
            ->latest()
            ->limit(5)
            ->get();

        $recentAssessments = Assessment::whereIn('project_id', $workspaceProjectIds)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->with(['project', 'moduleScope.module', 'score', 'reportSnapshot', 'catalogueRelease'])
            ->latest('completed_at')
            ->limit(5)
            ->get();

        // Operational view of the daily work: what is being set up, what is out collecting,
        // and how many responses have arrived. These read the same tables as the outcome
        // figures above, just at a different stage of the lifecycle.
        $operations = [
            'awaiting_publish' => Assessment::whereIn('project_id', $workspaceProjectIds)
                ->where('publish_status', Assessment::PUBLISH_DRAFT)
                ->where('status', Assessment::STATUS_IN_PROGRESS)
                ->count(),
            'collecting' => Assessment::whereIn('project_id', $workspaceProjectIds)
                ->where('publish_status', Assessment::PUBLISH_PUBLISHED)
                ->whereNull('closed_at')
                ->where('status', Assessment::STATUS_IN_PROGRESS)
                ->count(),
            'responses' => PublicResponseSession::whereIn(
                'assessment_id',
                Assessment::whereIn('project_id', $workspaceProjectIds)->select('assessment_id')
            )->whereNotNull('submitted_at')->count(),
        ];

        $assessmentsAwaitingPublish = Assessment::whereIn('project_id', $workspaceProjectIds)
            ->where('publish_status', Assessment::PUBLISH_DRAFT)
            ->where('status', Assessment::STATUS_IN_PROGRESS)
            ->with(['project', 'target'])
            ->latest('updated_at')
            ->limit(5)
            ->get();

        $assessmentsCollecting = Assessment::whereIn('project_id', $workspaceProjectIds)
            ->where('publish_status', Assessment::PUBLISH_PUBLISHED)
            ->whereNull('closed_at')
            ->where('status', Assessment::STATUS_IN_PROGRESS)
            ->with(['project', 'target'])
            ->withCount(['publicResponseSessions as submitted_count' => fn ($q) => $q->whereNotNull('submitted_at')])
            ->latest('published_at')
            ->limit(5)
            ->get();

        // First-run activation: the three steps a new workspace works through. The dashboard
        // leads with this checklist until the first report exists, so a new user always has a
        // clear next step rather than a wall of zeros.
        $anyProject = Project::exists();
        $anyAssessment = Assessment::whereIn('project_id', $workspaceProjectIds)->exists();
        $activation = [
            'project' => $anyProject,
            'assessment' => $anyAssessment,
            'report' => $totalAssessments > 0,
        ];

        // Action attention across the workspace (AssessmentAction is workspace-scoped).
        $openActions = AssessmentAction::whereIn('status', [AssessmentAction::STATUS_OPEN, AssessmentAction::STATUS_IN_PROGRESS])->count();
        $overdueActions = AssessmentAction::whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->whereNotIn('status', [AssessmentAction::STATUS_DONE, AssessmentAction::STATUS_VERIFIED])
            ->count();

        // A preview of the latest report's intelligence, so the front door shows value.
        $latestReport = $this->latestReportPreview($recentAssessments->first());

        return view('dashboard', compact(
            'activeProjectCount',
            'totalAssessments',
            'avgScore',
            'distribution',
            'recentProjects',
            'recentAssessments',
            'operations',
            'assessmentsAwaitingPublish',
            'assessmentsCollecting',
            'activation',
            'openActions',
            'overdueActions',
            'latestReport',
        ));
    }

    /**
     * Top finding, biggest risk, and first recommendation from the most recent completed
     * assessment — the intelligence, previewed on the home page.
     *
     * @return array<string, mixed>|null
     */
    private function latestReportPreview(?Assessment $latest): ?array
    {
        if ($latest === null) {
            return null;
        }

        $payload = app(ReportSnapshotService::class)->payloadFor($latest);
        $intelligence = $payload['intelligence'] ?? app(ReportComposer::class)->intelligence($payload);
        $findings = collect($intelligence['findings'] ?? []);

        return [
            'assessment' => $latest,
            'title' => $payload['title'] ?? 'Assessment report',
            'score' => $payload['score']['overall_score'] ?? null,
            'calibration' => $payload['score']['calibration_status'] ?? null,
            'top_finding' => $findings->firstWhere('category', 'CRITICAL_FINDING')
                ?? $findings->firstWhere('category', 'WEAKNESS'),
            'top_risk' => collect($intelligence['risks'] ?? [])->first(),
            'top_action' => collect($intelligence['recommendations'] ?? [])->first(),
        ];
    }
}
