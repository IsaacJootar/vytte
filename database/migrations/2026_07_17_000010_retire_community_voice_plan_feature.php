<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('plan_features')
            ->where('feature_key', 'patient_community_voice_module')
            ->delete();
    }

    public function down(): void
    {
        foreach (['FREE', 'PRO', 'AGENCY'] as $plan) {
            DB::table('plan_features')->updateOrInsert(
                [
                    'plan' => $plan,
                    'feature_key' => 'patient_community_voice_module',
                ],
                ['enabled' => $plan !== 'FREE'],
            );
        }
    }
};
