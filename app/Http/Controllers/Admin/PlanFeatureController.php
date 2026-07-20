<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\PlanService;
use App\Services\PlanUsageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanFeatureController extends Controller
{
    public function index(PlanUsageService $usage): View
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
            'plans' => SubscriptionPlan::orderBy('display_order')->get(),
            'usage' => $usage->byPlan(),
            'nearLimit' => $usage->workspacesNearLimit(),
            'totals' => $usage->totals(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plans' => ['nullable', 'array'],
            'plans.*.public_label' => ['required', 'string', 'max:120'],
            'plans.*.description' => ['nullable', 'string'],
            'plans.*.display_order' => ['required', 'integer', 'min:1', 'max:999'],
            'plans.*.is_active' => ['nullable', 'boolean'],
            'plans.*.is_beta_unlocked' => ['nullable', 'boolean'],
            'plans.*.limits_json' => ['nullable', 'json'],
            'features' => ['nullable', 'array'],
        ]);

        foreach ($validated['plans'] ?? [] as $planCode => $planData) {
            $plan = SubscriptionPlan::find($planCode);
            if (! $plan) {
                continue;
            }

            $plan->update([
                'public_label' => $planData['public_label'],
                'description' => $planData['description'] ?? null,
                'display_order' => $planData['display_order'],
                'is_active' => $request->boolean("plans.{$planCode}.is_active"),
                'is_beta_unlocked' => $request->boolean("plans.{$planCode}.is_beta_unlocked"),
                'limits' => json_decode($planData['limits_json'] ?: '{}', true, flags: JSON_THROW_ON_ERROR),
            ]);
        }

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
