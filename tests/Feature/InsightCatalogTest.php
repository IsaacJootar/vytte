<?php

namespace Tests\Feature;

use App\Models\InsightCategory;
use App\Services\Reporting\InsightCatalog;
use Database\Seeders\MethodologyCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The insights engine reads a pure constant catalog for speed and testability. This test is
 * the guarantee that the constant never drifts from the seeded governed categories.
 */
class InsightCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_matches_the_seeded_insight_categories_exactly(): void
    {
        // The methodology seeder owns the governed insight categories; ensure they are present
        // regardless of what the shared test baseline seeds.
        if (InsightCategory::query()->count() === 0) {
            $this->seed(MethodologyCatalogueSeeder::class);
        }

        $seeded = InsightCategory::query()->get()
            ->mapWithKeys(fn ($c) => [$c->category_code => ['name' => $c->category_name, 'polarity' => $c->polarity]]);

        // Same set of codes — no category seeded that the engine cannot name, and none named
        // that is not governed.
        $this->assertEqualsCanonicalizing(
            $seeded->keys()->all(),
            array_keys(InsightCatalog::CATEGORIES),
            'InsightCatalog codes must match the seeded insight_categories exactly.'
        );

        // Same polarity for every code.
        foreach ($seeded as $code => $row) {
            $this->assertSame($row['polarity'], InsightCatalog::polarity($code), "Polarity drift for {$code}");
        }
    }
}
