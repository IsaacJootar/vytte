<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Services\PlanService;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PlanService::PLAN_DEFINITIONS as $code => $definition) {
            SubscriptionPlan::updateOrCreate(
                ['plan_code' => $code],
                [
                    'plan_name' => $definition['name'],
                    'public_label' => $definition['label'],
                    'description' => $definition['description'],
                    'display_order' => $definition['display_order'],
                    'is_active' => true,
                    'is_beta_unlocked' => true,
                    'pricing_metadata' => $definition['pricing_metadata'],
                    'limits' => $definition['limits'],
                ]
            );
        }
    }
}
