<?php

namespace App\Http\Controllers;

use App\Services\PlanService;
use App\Services\Reporting\PortfolioService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PortfolioController extends Controller
{
    /**
     * A safe overall view of completed results across the current workspace.
     */
    public function index(PortfolioService $portfolio): View|RedirectResponse
    {
        $workspace = app('current.workspace');

        if (! PlanService::workspaceCanAccess($workspace, 'progress_maturity_tracking')) {
            return redirect()->route('billing.index')
                ->with('limit_error', 'Overall results are not available on your current plan. Upgrade to see completed results across all your settings.');
        }

        return view('portfolio.index', $portfolio->build());
    }
}
