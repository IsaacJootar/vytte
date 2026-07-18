<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlatformSettingsSeeder::class,
            ReferenceDataSeeder::class,
            PlatformGovernedDemoSeeder::class,
            PlanFeatureSeeder::class,
            DemoAccountSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
