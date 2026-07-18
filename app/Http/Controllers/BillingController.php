<?php

namespace App\Http\Controllers;

use App\Services\PlanService;
use Illuminate\Contracts\View\View;

class BillingController extends Controller
{
    public function index(): View
    {
        $workspace = app('current.workspace');

        return view('billing.index', [
            'workspace' => $workspace,
            'currentPlan' => PlanService::normalizePlan($workspace->plan ?? 'STARTER'),
            'plans' => PlanService::activePlans(),
            'features' => PlanService::FEATURES,
        ]);
    }
}
