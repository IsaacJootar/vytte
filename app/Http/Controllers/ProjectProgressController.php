<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Project;
use App\Services\PlanService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectProgressController extends Controller
{
    public function index(Project $project): View|RedirectResponse
    {
        $this->authorize('view', $project);
        $workspace = app('current.workspace');
        if (! PlanService::workspaceCanAccess($workspace, 'progress_maturity_tracking')) {
            return redirect()->route('projects.show', $project)
                ->with('limit_error', 'Progress tracking is not available on your current plan. Upgrade to view maturity trends over time.');
        }

        $assessments = Assessment::where('project_id', $project->project_id)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->with(['score.maturityLevel', 'moduleScope.module'])
            ->orderBy('completed_at')
            ->get();

        $assessmentIds = $assessments->pluck('assessment_id');

        $domainScoresByAssessment = $assessmentIds->isNotEmpty()
            ? DB::table('domain_scores as ds')
                ->join('domains as d', 'd.domain_id', '=', 'ds.domain_id')
                ->whereIn('ds.assessment_id', $assessmentIds)
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

        return view('projects.progress', compact('project', 'assessments', 'domainScoresByAssessment', 'allDomains'));
    }

    public function compare(Project $project, Request $request): View|RedirectResponse
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
            ->with(['score.maturityLevel', 'moduleScope.module'])
            ->firstOrFail();

        $assessmentB = Assessment::where('project_id', $project->project_id)
            ->where('assessment_id', $idB)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->with(['score.maturityLevel', 'moduleScope.module'])
            ->firstOrFail();

        if ($assessmentA->assessment_id === $assessmentB->assessment_id) {
            return redirect()->route('projects.progress', $project)
                ->with('error', 'Choose two different assessment runs to compare.');
        }

        if ($this->compositionFingerprint($assessmentA) !== $this->compositionFingerprint($assessmentB)) {
            return redirect()->route('projects.progress', $project)
                ->with('error', 'These assessments use different content or areas and cannot be compared reliably.');
        }

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

        return view('projects.compare', compact('project', 'assessmentA', 'assessmentB', 'allDomains', 'domainsA', 'domainsB'));
    }

    private function compositionFingerprint(Assessment $assessment): string
    {
        if ($assessment->composition_hash) {
            return $assessment->composition_hash;
        }

        $moduleIds = $assessment->moduleScope->where('in_scope', true)
            ->pluck('module_id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        return hash('sha256', json_encode($moduleIds, JSON_THROW_ON_ERROR));
    }
}
