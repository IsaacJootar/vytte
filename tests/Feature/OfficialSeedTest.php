<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The official production seed composition.
 *
 * Guards the one thing about the production seed that a careless change could quietly
 * break: that `DatabaseSeeder` ships official content and no demonstration content.
 *
 * This asserts the composition of the seeder chain by reading its source, rather than by
 * seeding a database. The automated suite seeds a shared demonstration baseline once per
 * process for speed, which is deliberately incompatible with a test that needs a different
 * seed; a runtime assertion would fight that optimisation. The freshly seeded official
 * database is verified separately, by `php artisan migrate:fresh --seed`, and the numbers
 * are recorded in OFFICIAL_SEED_REPORT.md.
 */
class OfficialSeedTest extends TestCase
{
    private function databaseSeederSource(): string
    {
        return file_get_contents(base_path('database/seeders/DatabaseSeeder.php'));
    }

    public function test_the_production_seed_includes_every_official_seeder(): void
    {
        $source = $this->databaseSeederSource();

        foreach ([
            'OfficialTaxonomySeeder',
            'OfficialReferenceSeeder',
            'MethodologyCatalogueSeeder',
            'OfficialQuestionLibrarySeeder',
            'OfficialFrameworkSeeder',
            'OfficialCatalogueSeeder',
        ] as $seeder) {
            $this->assertStringContainsString($seeder, $source,
                "The production seed no longer calls {$seeder}.");
        }
    }

    public function test_the_production_seed_contains_no_demonstration_seeder(): void
    {
        $source = $this->databaseSeederSource();

        foreach ([
            'PlatformGovernedDemoSeeder',
            'DemoAccountSeeder',
            'DemoDataSeeder',
        ] as $demoSeeder) {
            // The class name may appear in a comment explaining its deliberate absence;
            // what must not appear is it being called.
            $this->assertStringNotContainsString($demoSeeder.'::class', $source,
                "The production seed calls the demonstration seeder {$demoSeeder}.");
        }
    }

    public function test_the_production_seed_publishes_the_methodology(): void
    {
        // Reports cite a frozen methodology; the seed must leave it published, not draft.
        $this->assertStringContainsString('publishMethodology', $this->databaseSeederSource());
    }

    public function test_demonstration_seeders_remain_available_for_tests(): void
    {
        // They are removed from production, not deleted — the automated suite still needs
        // them as fixtures.
        $this->assertFileExists(base_path('database/seeders/PlatformGovernedDemoSeeder.php'));
        $this->assertStringContainsString('PlatformGovernedDemoSeeder',
            file_get_contents(base_path('database/seeders/TestBaselineSeeder.php')));
    }
}
