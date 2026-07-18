<?php

namespace Database\Seeders;

use App\Services\PlanService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanFeatureSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('plan_features')
            ->whereNotIn('plan', PlanService::PLANS)
            ->delete();

        foreach (PlanService::PLANS as $plan) {
            foreach (array_keys(PlanService::FEATURES) as $featureKey) {
                DB::table('plan_features')->updateOrInsert(
                    ['plan' => $plan, 'feature_key' => $featureKey],
                    ['enabled' => true]
                );
            }
        }
    }
}
