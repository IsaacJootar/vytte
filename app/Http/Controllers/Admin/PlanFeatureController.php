<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PlanService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanFeatureController extends Controller
{
    public function index(): View
    {
        $rows = DB::table('plan_features')->get()->keyBy(fn ($r) => $r->plan.'|'.$r->feature_key);

        $matrix = [];
        foreach (array_keys(PlanService::FEATURES) as $featureKey) {
            $matrix[$featureKey] = [];
            foreach (PlanService::PLANS as $plan) {
                $row = $rows->get($plan.'|'.$featureKey);
                $matrix[$featureKey][$plan] = (bool) ($row?->enabled ?? false);
            }
        }

        return view('admin.plan-features.index', [
            'matrix' => $matrix,
            'features' => PlanService::FEATURES,
            'plans' => PlanService::PLANS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        foreach (array_keys(PlanService::FEATURES) as $featureKey) {
            foreach (PlanService::PLANS as $plan) {
                $enabled = $request->boolean("features.{$plan}.{$featureKey}");

                DB::table('plan_features')->updateOrInsert(
                    ['plan' => $plan, 'feature_key' => $featureKey],
                    ['enabled' => $enabled]
                );
            }
        }

        return back()->with('success', 'Plan features saved.');
    }
}
