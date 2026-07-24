<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Project;
use App\Services\Reporting\BenchmarkService;
use Illuminate\Contracts\View\View;

class ReportsController extends Controller
{
    public function index(BenchmarkService $benchmarks): View
    {
        $workspaceProjectIds = Project::select('project_id');

        $assessments = Assessment::whereIn('project_id', $workspaceProjectIds)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->with([
                'project',
                'target',
                'score.maturityLevel',
                'reportSnapshot',
                'catalogueRelease',
                'moduleScope.module',
                'shareLinks' => fn ($query) => $query->latest('created_at'),
            ])
            ->latest('completed_at')
            ->paginate(20);

        // The headline of each report, read straight from the frozen snapshot (no recompute)
        // so the hub stays fast: the biggest issue and the top risk.
        $previews = $assessments->getCollection()->mapWithKeys(function ($assessment) {
            $intel = $assessment->reportSnapshot?->payload['intelligence'] ?? null;
            if (! $intel) {
                return [$assessment->assessment_id => null];
            }
            $findings = collect($intel['findings'] ?? []);

            return [$assessment->assessment_id => [
                'top_finding' => $findings->firstWhere('category', 'CRITICAL_FINDING') ?? $findings->firstWhere('category', 'WEAKNESS'),
                'top_risk' => collect($intel['risks'] ?? [])->first(),
            ]];
        });

        // Compare, folded into the hub: how the workspace's assessment targets stack up.
        $facilities = $benchmarks->facilityComparison();
        $workspaceAverage = $benchmarks->workspaceAverage();
        $domainComparison = $benchmarks->domainComparison();
        $canCompare = count($facilities) >= 2;

        return view('reports.index', compact(
            'assessments', 'previews', 'facilities', 'workspaceAverage', 'domainComparison', 'canCompare'
        ));
    }
}
