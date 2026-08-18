<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\PerformanceTarget;
use App\Models\Project;
use App\Models\ReportSchedule;
use App\Services\PlanService;
use App\Services\Reporting\ComparisonEligibilityService;
use App\Services\Reporting\ComparisonSeriesService;
use App\Services\Reporting\TrendService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectProgressController extends Controller
{
    public function index(Project $project, TrendService $trends, ComparisonSeriesService $seriesService): View|RedirectResponse
    {
        $this->authorize('view', $project);
        $workspace = app('current.workspace');
        if (! PlanService::workspaceCanAccess($workspace, 'progress_maturity_tracking')) {
            return redirect()->route('projects.show', $project)
                ->with('limit_error', 'Progress tracking is not available on your current plan. Upgrade to view maturity trends over time.');
        }

        // The full ledger: every completed run, regardless of series. The chart and domain
        // matrix below use only the compatible subset — mixing series there would let visual
        // proximity imply a trend Vytte is not permitted to claim.
        $assessments = Assessment::where('project_id', $project->project_id)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->with(['score.maturityLevel', 'moduleScope.module'])
            ->orderBy('completed_at')
            ->get();

        $series = $seriesService->seriesFor($project);
        $seriesIds = $series->pluck('assessment_id');

        $domainScoresByAssessment = $seriesIds->isNotEmpty()
            ? DB::table('domain_scores as ds')
                ->join('domains as d', 'd.domain_id', '=', 'ds.domain_id')
                ->whereIn('ds.assessment_id', $seriesIds)
                ->where('d.is_operational', true)
                ->select('ds.assessment_id', 'ds.score', 'd.domain_id', 'd.domain_code', 'd.domain_name', 'd.display_order')
                ->orderBy('d.display_order')
                ->get()
                ->groupBy('assessment_id')
            : collect();

        $allDomains = DB::table('domains')
            ->where('is_operational', true)
            ->orderBy('display_order')
            ->get();

        $trend = $trends->summary($project);
        $followThrough = $trends->actionFollowThrough($project);
        $issues = $trends->issues($project);
        $trendInsights = $trends->trendInsights($project);
        $targetProgress = $trends->targetProgress($project);
        $targets = PerformanceTarget::where('project_id', $project->project_id)->get();
        $schedules = ReportSchedule::where('project_id', $project->project_id)->get();

        return view('projects.progress', compact('project', 'assessments', 'series', 'domainScoresByAssessment', 'allDomains', 'trend', 'followThrough', 'issues', 'trendInsights', 'targetProgress', 'targets', 'schedules'));
    }

    public function compare(Project $project, Request $request, ComparisonEligibilityService $eligibility): View|RedirectResponse
    {
        $this->authorize('view', $project);
        $workspace = app('current.workspace');
        if (! PlanService::workspaceCanAccess($workspace, 'progress_maturity_tracking')) {
            return redirect()->route('projects.show', $project)
                ->with('limit_error', 'Assessment comparison is not available on your current plan. Upgrade to compare results.');
        }

        $idA = $request->query('a');
        $idB = $request->query('b');

        $assessmentA = Assessment::where('project_id', $project->project_id)
            ->where('assessment_id', $idA)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->with(['score.maturityLevel', 'moduleScope.module', 'snapshot', 'reportSnapshot'])
            ->firstOrFail();

        $assessmentB = Assessment::where('project_id', $project->project_id)
            ->where('assessment_id', $idB)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->with(['score.maturityLevel', 'moduleScope.module', 'snapshot', 'reportSnapshot'])
            ->firstOrFail();

        if ($assessmentA->assessment_id === $assessmentB->assessment_id) {
            return redirect()->route('projects.progress', $project)
                ->with('error', 'Choose two different assessment runs to compare.');
        }

        $comparison = $eligibility->between($assessmentA, $assessmentB);

        $allDomains = DB::table('domains')
            ->where('is_operational', true)
            ->orderBy('display_order')
            ->get();

        $domainsA = DB::table('domain_scores')
            ->where('assessment_id', $idA)
            ->pluck('score', 'domain_id');

        $domainsB = DB::table('domain_scores')
            ->where('assessment_id', $idB)
            ->pluck('score', 'domain_id');

        return view('projects.compare', compact('project', 'assessmentA', 'assessmentB', 'allDomains', 'domainsA', 'domainsB', 'comparison'));
    }

    /**
     * Set (or update) a performance target for this project — overall or for one domain.
     *
     * Bound to the project's current compatible series at the moment it is set: if the project
     * later moves to an incompatible series, this target stops applying rather than silently
     * being measured against a different methodology. A project with no completed assessment
     * yet gets a null signature, meaning "applies to whichever series is recorded first."
     */
    public function setTarget(Request $request, Project $project, ComparisonSeriesService $series): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'domain_code' => ['nullable', 'string', 'max:20'],
            'target_score' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $currentSeries = $series->seriesFor($project);
        $signature = $currentSeries->isNotEmpty() ? $series->signatureFor($currentSeries->last()) : null;

        PerformanceTarget::updateOrCreate(
            [
                'project_id' => $project->project_id,
                'domain_code' => $validated['domain_code'] ?: null,
                'comparison_signature' => $signature,
            ],
            ['target_score' => $validated['target_score'], 'created_by' => $request->user()->user_id],
        );

        return back()->with('success', 'Target saved.');
    }

    public function deleteTarget(Project $project, PerformanceTarget $target): RedirectResponse
    {
        $this->authorize('update', $project);
        if ($target->project_id !== $project->project_id) {
            abort(404);
        }

        $target->delete();

        return back()->with('success', 'Target removed.');
    }
}
