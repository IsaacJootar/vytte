<?php

namespace App\Http\Controllers;

use App\Services\PlanService;
use App\Services\Reporting\BenchmarkService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BenchmarkController extends Controller
{
    /**
     * In-tenant benchmarking: how the workspace's facilities compare with each other, and
     * where the workspace is collectively strong and weak. Everything here is workspace-scoped
     * — no data crosses the tenant boundary.
     */
    public function index(BenchmarkService $benchmarks): View|RedirectResponse
    {
        $workspace = app('current.workspace');
        if (! PlanService::workspaceCanAccess($workspace, 'progress_maturity_tracking')) {
            return redirect()->route('dashboard')
                ->with('limit_error', 'Benchmarking is not available on your current plan. Upgrade to compare facilities.');
        }

        $facilities = $benchmarks->facilityComparison();
        $workspaceAverage = $benchmarks->workspaceAverage();
        $domainComparison = $benchmarks->domainComparison();

        return view('benchmark.index', compact('facilities', 'workspaceAverage', 'domainComparison'));
    }
}
