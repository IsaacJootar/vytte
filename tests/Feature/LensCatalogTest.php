<?php

namespace Tests\Feature;

use App\Models\AnalysisLens;
use App\Services\Reporting\LensCatalog;
use Database\Seeders\MethodologyCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The lens engine reads a pure constant catalog. This is the guarantee that its seven codes
 * are real governed analysis lenses, so a lens the report offers always maps to a seeded one.
 */
class LensCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_catalog_lens_is_a_seeded_analysis_lens(): void
    {
        if (AnalysisLens::query()->count() === 0) {
            $this->seed(MethodologyCatalogueSeeder::class);
        }

        $seededCodes = AnalysisLens::query()->pluck('lens_code')->all();

        foreach (array_keys(LensCatalog::LENSES) as $code) {
            $this->assertContains($code, $seededCodes, "Lens {$code} is not a seeded analysis lens.");
        }
    }
}
