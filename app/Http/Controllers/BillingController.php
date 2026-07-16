<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
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
            'paystackEnabled' => PlatformSetting::get('payment.paystack_enabled', true),
            'flutterwaveEnabled' => PlatformSetting::get('payment.flutterwave_enabled', false),
            'flutterwavePublicKey' => config('services.flutterwave.public_key'),
        ]);
    }
}
