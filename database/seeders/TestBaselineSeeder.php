<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The baseline content every feature test can assume: the reference taxonomy and the
 * governed demonstration catalogue.
 *
 * RefreshDatabase runs `migrate:fresh --seeder` once per PHPUnit process, so this executes
 * a single time and every test then begins from the same seeded state inside its own
 * rolled-back transaction. Seeding it per test instead cost roughly 866 queries and 7.4
 * seconds each, which dominated the suite.
 *
 * DemoAccountSeeder and DemoDataSeeder are deliberately excluded. They create demo users,
 * workspaces and completed assessments, and several tests assert on an empty or
 * self-constructed state, so those stay opt-in through an explicit `$this->seed(...)` call.
 */
class TestBaselineSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            PlatformGovernedDemoSeeder::class,
        ]);
    }
}
