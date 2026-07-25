<?php

namespace App\Http\Controllers;

use App\Services\PlanService;
use App\Services\Reporting\BenchmarkService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BenchmarkController extends Controller
{
    /**
     * Compare — how the workspace's assessment targets stack up against each other. Everything
     * here is workspace-scoped; nothing crosses the tenant boundary (the cross-tenant national
     * average remains the deferred exception).
     */
    public function index(BenchmarkService $benchmarks): View|RedirectResponse
    {
        $workspace = app('current.workspace');
        if (! PlanService::workspaceCanAccess($workspace, 'progress_maturity_tracking')) {
            return redirect()->route('dashboard')
                ->with('limit_error', 'Comparison is not available on your current plan. Upgrade to compare your assessment targets.');
        }

        $facilities = $benchmarks->facilityComparison();
        $workspaceAverage = $benchmarks->workspaceAverage();
        $domainComparison = $benchmarks->domainComparison();

        return view('benchmark.index', compact('facilities', 'workspaceAverage', 'domainComparison'));
    }
}
