<?php

namespace App\Http\Controllers;

use App\Services\PlanService;
use App\Services\Reporting\PortfolioService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PortfolioController extends Controller
{
    /**
     * The portfolio hub — every completed assessment in the workspace harmonised into one
     * program-level picture.
     */
    public function index(PortfolioService $portfolio): View|RedirectResponse
    {
        $workspace = app('current.workspace');

        if (! PlanService::workspaceCanAccess($workspace, 'progress_maturity_tracking')) {
            return redirect()->route('billing.index')
                ->with('limit_error', 'The portfolio hub is not available on your current plan. Upgrade to see your whole program in one place.');
        }

        return view('portfolio.index', $portfolio->build());
    }
}
