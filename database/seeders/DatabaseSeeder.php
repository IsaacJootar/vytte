<?php

namespace Database\Seeders;

use App\Models\MethodologyVersion;
use App\Services\MethodologyPublishingService;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

/**
 * The official production seed.
 *
 * This is the canonical Vytte knowledge base that ships with the platform. It contains no
 * demonstration content: no demo accounts, no demo workspaces, no demo assessments, no
 * demo frameworks. A fresh database seeded from here is production-ready.
 *
 * Order matters. Reference taxonomy and measurement domains come first; the methodology
 * depends on them; the question library depends on the methodology; frameworks compose
 * from the library; catalogue releases pin the frameworks. Seeding out of order produces
 * content that cannot resolve.
 *
 * Everything is published and live, because this is the real beta, not a preview. The
 * methodology version is published at the end so reports can cite a frozen methodology.
 *
 * The demonstration seeders (PlatformGovernedDemoSeeder, DemoAccountSeeder, DemoDataSeeder)
 * remain in the codebase as test fixtures and are seeded only by TestBaselineSeeder for the
 * automated suite. They are deliberately absent here.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Platform and reference data
            PlatformSettingsSeeder::class,
            ReferenceDataSeeder::class,
            OfficialTaxonomySeeder::class,
            SubscriptionPlanSeeder::class,
            PlanFeatureSeeder::class,

            // Official knowledge base
            OfficialReferenceSeeder::class,      // facility profiles and departments
            MethodologyCatalogueSeeder::class,   // objectives, lenses, insight categories, templates
            OfficialQuestionLibrarySeeder::class,
            OfficialFrameworkSeeder::class,
            OfficialCatalogueSeeder::class,
        ]);

        $this->publishMethodology();
    }

    /**
     * Freeze the methodology so a report can always be traced to the exact objectives,
     * lenses and categories in force when it was produced. A methodology that fails its
     * own consistency check is left as a draft rather than published broken.
     */
    private function publishMethodology(): void
    {
        $version = MethodologyVersion::where('status', MethodologyVersion::STATUS_DRAFT)
            ->orderByDesc('version_number')
            ->first();

        if (! $version) {
            return;
        }

        try {
            app(MethodologyPublishingService::class)->publish($version);
            $this->command?->info('Methodology version '.$version->version_number.' published.');
        } catch (ValidationException $e) {
            $this->command?->warn('Methodology left as draft — consistency check failed: '.collect($e->errors())->flatten()->first());
        }
    }
}
