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
            'currentPlan' => $workspace->plan ?? 'FREE',
            'plans' => PlanService::PLANS,
            'limits' => PlanService::LIMITS,
            'prices' => [
                'PRO' => PlanService::priceNgn('PRO'),
                'AGENCY' => PlanService::priceNgn('AGENCY'),
            ],
            'paystackPublicKey' => config('services.paystack.public_key'),
        ]);
    }
}
