<?php

namespace Database\Seeders;

use App\Services\PlanService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $proFeatures = [
            'team_members',
            'shareable_public_links',
            'shareable_report_links',
            'progress_maturity_tracking',
            'localization',
            'patient_community_voice_module',
            'pdf_export_no_watermark',
            'csv_export',
        ];

        foreach (PlanService::PLANS as $plan) {
            foreach (array_keys(PlanService::FEATURES) as $featureKey) {
                $enabled = match ($plan) {
                    'FREE' => false,
                    'PRO' => in_array($featureKey, $proFeatures, true),
                    'AGENCY' => true,
                    default => false,
                };

                DB::table('plan_features')->updateOrInsert(
                    ['plan' => $plan, 'feature_key' => $featureKey],
                    ['enabled' => $enabled]
                );
            }
        }
    }
}
