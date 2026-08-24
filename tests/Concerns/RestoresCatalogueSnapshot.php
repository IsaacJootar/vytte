<?php

namespace Tests\Concerns;

use Database\Seeders\DatabaseSeeder;
use Tests\Support\CatalogueSnapshotter;

/**
 * Gives official-catalogue tests the real, fully governed catalogue content — questions,
 * frameworks, catalogue releases — without paying what DatabaseSeeder costs to rebuild it
 * from scratch on every single test method.
 *
 * The first run after any seeder file changes rebuilds the snapshot the slow way (correct,
 * self-healing); every run after that restores it in seconds via bulk insert instead of
 * re-running the seeder's business logic.
 */
trait RestoresCatalogueSnapshot
{
    protected function seedOfficialCatalogue(): void
    {
        if (CatalogueSnapshotter::isFresh()) {
            CatalogueSnapshotter::restore();

            return;
        }

        CatalogueSnapshotter::capture(fn () => $this->seed(DatabaseSeeder::class));
    }
}
