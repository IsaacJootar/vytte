<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Captures and restores the rows the official DatabaseSeeder chain adds (or updates) on top
 * of the per-process TestBaselineSeeder state, so tests that need the real official catalogue
 * (HealthFacilityDigitalReadinessTest and friends) don't have to re-run several minutes of
 * seeder business logic on every single test method.
 *
 * Several official seeders call updateOrCreate() against rows the baseline seeder already
 * created (e.g. facility_profiles keyed by profile_code), so the diff is keyed by primary key,
 * not full-row content: a row whose primary key already existed is an update to replay, not a
 * new row to insert. Tables without a single-column primary key fall back to whole-row content
 * identity.
 *
 * The snapshot is paired with a hash of every seeder file's contents; if the seeders have
 * changed since the snapshot was taken, it's stale and gets rebuilt automatically rather than
 * silently restoring outdated data.
 *
 * UUID primary keys minted by the baseline seeder (content_publishers, facility_profiles,
 * domain_taxonomies, ...) are not stable across PHPUnit processes — every process re-runs
 * TestBaselineSeeder and mints fresh ones, the same way the "VYTTE" content_publishers row
 * gets a fresh id from every `migrate:fresh`. A snapshot captured in one process therefore
 * embeds foreign keys pointing at ids a later process never recreated. capture() records, for
 * every baseline table with a single-column primary key and a natural (unique, non-pk) key —
 * facility_profiles.profile_code, domain_taxonomies.taxonomy_code, and so on — a map from that
 * natural key to the id captured for it. restore() re-derives each table's *current* id for
 * the same natural key and substitutes it wherever the old id appears, resolving multi-table
 * chains (e.g. domain_definitions' own natural key embeds domain_taxonomy_versions' id) in
 * dependency order via retry passes, the same way pending inserts are retried below.
 */
class CatalogueSnapshotter
{
    private const DATA_PATH = __DIR__.'/../fixtures/catalogue-snapshot/data.php';

    private const HASH_PATH = __DIR__.'/../fixtures/catalogue-snapshot/hash.txt';

    private const IGNORED_TABLES = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    /** Separates natural-key column values when composite keys are joined into one lookup string. */
    private const KEY_SEPARATOR = "\x1f";

    public static function isFresh(): bool
    {
        return is_file(self::DATA_PATH)
            && is_file(self::HASH_PATH)
            && trim(file_get_contents(self::HASH_PATH)) === self::currentSeederHash();
    }

    /**
     * Run $seed(), diff every table's rows before/after by primary key, and persist the
     * inserted and updated rows as the new snapshot. Runs inside the caller's already-open
     * test transaction; the rows it inserts are rolled back as usual when the test ends, but
     * the snapshot file it writes to disk is not, so the next test (or the next run) restores
     * instead of reseeding.
     */
    public static function capture(callable $seed): void
    {
        $tables = self::tables();

        $pkColumns = [];
        foreach ($tables as $table) {
            $cols = self::primaryKeyColumns($table);
            $pkColumns[$table] = count($cols) === 1 ? $cols[0] : null;
        }

        $before = self::rowSets($tables);

        $naturalKeys = self::captureNaturalKeys($tables, $before, $pkColumns);

        $seed();

        $after = self::rowSets($tables);

        $inserts = [];
        $updates = [];

        foreach ($tables as $table) {
            $pk = $pkColumns[$table];

            if ($pk === null) {
                $beforeHashes = [];

                foreach ($before[$table] ?? [] as $row) {
                    $beforeHashes[self::hashRow($row)] = true;
                }

                $newRows = array_values(array_filter(
                    $after[$table] ?? [],
                    fn (array $row) => ! isset($beforeHashes[self::hashRow($row)])
                ));

                if ($newRows !== []) {
                    $inserts[$table] = $newRows;
                }

                continue;
            }

            $beforeByPk = [];

            foreach ($before[$table] ?? [] as $row) {
                $beforeByPk[(string) $row[$pk]] = $row;
            }

            $newRows = [];
            $changedRows = [];

            foreach ($after[$table] ?? [] as $row) {
                $key = (string) $row[$pk];

                if (! array_key_exists($key, $beforeByPk)) {
                    $newRows[] = $row;
                } elseif ($beforeByPk[$key] !== $row) {
                    $changedRows[] = $row;
                }
            }

            if ($newRows !== []) {
                $inserts[$table] = $newRows;
            }

            if ($changedRows !== []) {
                $updates[$table] = ['pk' => $pk, 'rows' => $changedRows];
            }
        }

        self::write([
            'inserts' => $inserts,
            'updates' => $updates,
            'baseline_natural_keys' => $naturalKeys,
        ]);
    }

    /**
     * Bulk-insert the snapshotted new rows, then replay the updates, skipping the seeder
     * business logic that produced them. Insertion order isn't recorded, so tables whose
     * foreign keys aren't satisfied yet are retried after the others succeed.
     */
    public static function restore(): void
    {
        $snapshot = require self::DATA_PATH;

        $baselineRemap = self::resolveBaselineRemap($snapshot['baseline_natural_keys'] ?? []);

        if ($baselineRemap !== []) {
            $snapshot['inserts'] = self::remapValue($snapshot['inserts'], $baselineRemap);
            $snapshot['updates'] = self::remapValue($snapshot['updates'], $baselineRemap);
        }

        $deferredScoringModelVersionIds = self::stripCyclicScoringModelReference($snapshot['inserts']);

        $pending = $snapshot['inserts'];
        $stalledPasses = 0;
        $lastErrors = [];

        while ($pending !== []) {
            $progressed = false;

            foreach ($pending as $table => $rows) {
                DB::statement('SAVEPOINT catalogue_snapshot_restore');

                try {
                    foreach (array_chunk($rows, 500) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }

                    DB::statement('RELEASE SAVEPOINT catalogue_snapshot_restore');
                    unset($pending[$table]);
                    $progressed = true;
                } catch (Throwable $e) {
                    DB::statement('ROLLBACK TO SAVEPOINT catalogue_snapshot_restore');
                    $lastErrors[$table] = $e->getMessage();
                }
            }

            if (! $progressed && ++$stalledPasses > 1) {
                $detail = collect($lastErrors)
                    ->only(array_keys($pending))
                    ->map(fn ($message, $table) => "{$table}: {$message}")
                    ->implode("\n");

                throw new RuntimeException(
                    "Catalogue snapshot restore stalled. Last error per remaining table:\n{$detail}"
                );
            }
        }

        foreach ($snapshot['updates'] as $table => ['pk' => $pk, 'rows' => $rows]) {
            foreach ($rows as $row) {
                DB::table($table)->where($pk, $row[$pk])->update($row);
            }
        }

        foreach ($deferredScoringModelVersionIds as $frameworkVersionId => $scoringModelVersionId) {
            DB::table('department_framework_versions')
                ->where('framework_version_id', $frameworkVersionId)
                ->update(['scoring_model_version_id' => $scoringModelVersionId]);
        }
    }

    /**
     * department_framework_versions.scoring_model_version_id and
     * scoring_model_versions.framework_version_id reference each other: a framework version
     * names its scoring model, and the scoring model names the framework version it belongs
     * to. No insert ordering resolves a genuine cycle, so the framework side is nulled out
     * (the column is nullable for exactly this reason — see the migration that added it) and
     * every row is inserted without it; restore() patches the real value back in once both
     * tables are fully populated.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $inserts
     * @return array<string, string> framework_version_id => scoring_model_version_id
     */
    private static function stripCyclicScoringModelReference(array &$inserts): array
    {
        $deferred = [];

        if (! isset($inserts['department_framework_versions'])) {
            return $deferred;
        }

        foreach ($inserts['department_framework_versions'] as &$row) {
            if (! empty($row['scoring_model_version_id'])) {
                $deferred[$row['framework_version_id']] = $row['scoring_model_version_id'];
                $row['scoring_model_version_id'] = null;
            }
        }
        unset($row);

        return $deferred;
    }

    /**
     * For every table with a single-column primary key and a natural (unique, non-pk) key,
     * map that natural key to the primary key value captured for it, so restore() can later
     * translate captured ids into whatever the current process actually minted for the same
     * logical row.
     *
     * @param  array<int, string>  $tables
     * @param  array<string, array<int, array<string, mixed>>>  $before
     * @param  array<string, ?string>  $pkColumns
     * @return array<string, array{pk: string, columns: array<int, string>, rows: array<string, string>}>
     */
    private static function captureNaturalKeys(array $tables, array $before, array $pkColumns): array
    {
        $result = [];

        foreach ($tables as $table) {
            $pk = $pkColumns[$table];

            if ($pk === null || ($before[$table] ?? []) === []) {
                continue;
            }

            $naturalColumns = self::naturalKeyColumns($table);

            if ($naturalColumns === []) {
                continue;
            }

            $rows = [];

            foreach ($before[$table] as $row) {
                $key = self::naturalKeyValue($row, $naturalColumns);

                if ($key !== null) {
                    $rows[$key] = (string) $row[$pk];
                }
            }

            if ($rows !== []) {
                $result[$table] = ['pk' => $pk, 'columns' => $naturalColumns, 'rows' => $rows];
            }
        }

        return $result;
    }

    /**
     * Translate every table's captured natural-key rows into the primary key value the
     * *current* process actually has for that same natural key, and flatten the result into
     * one old-id => new-id map. A table's natural key can itself embed another table's
     * primary key (e.g. domain_definitions is unique on (domain_taxonomy_version_id,
     * domain_code)), so tables are resolved in dependency order via retry passes, same as the
     * pending-insert loop in restore().
     *
     * @param  array<string, array{pk: string, columns: array<int, string>, rows: array<string, string>}>  $naturalKeys
     * @return array<string, string> old id => new id
     */
    private static function resolveBaselineRemap(array $naturalKeys): array
    {
        $resolved = [];
        $pending = $naturalKeys;
        $stalledPasses = 0;

        while ($pending !== []) {
            $progressed = false;

            foreach ($pending as $table => $spec) {
                $currentByKey = [];

                foreach (self::rowSets([$table])[$table] ?? [] as $row) {
                    $key = self::naturalKeyValue($row, $spec['columns']);

                    if ($key !== null) {
                        $currentByKey[$key] = (string) $row[$spec['pk']];
                    }
                }

                $tableResolved = true;
                $tableMap = [];

                foreach ($spec['rows'] as $capturedKey => $capturedPk) {
                    $liveKey = self::translateNaturalKey($capturedKey, $resolved);

                    if (! isset($currentByKey[$liveKey])) {
                        $tableResolved = false;

                        continue;
                    }

                    if ($currentByKey[$liveKey] !== $capturedPk) {
                        $tableMap[$capturedPk] = $currentByKey[$liveKey];
                    }
                }

                if ($tableResolved) {
                    $resolved += $tableMap;
                    unset($pending[$table]);
                    $progressed = true;
                }
            }

            if (! $progressed && ++$stalledPasses > 1) {
                // Some baseline rows captured before no longer exist under the same natural
                // key (e.g. a seeder removed or renamed one). Proceed with what did resolve;
                // anything that actually still matters will surface as its own clear foreign
                // key error during insert, rather than failing silently here.
                break;
            }
        }

        return $resolved;
    }

    /**
     * Rewrite the pieces of a captured composite natural-key string that are themselves ids
     * already resolved to their current value.
     */
    private static function translateNaturalKey(string $key, array $resolved): string
    {
        if ($resolved === []) {
            return $key;
        }

        $parts = explode(self::KEY_SEPARATOR, $key);

        foreach ($parts as &$part) {
            $part = $resolved[$part] ?? $part;
        }
        unset($part);

        return implode(self::KEY_SEPARATOR, $parts);
    }

    /**
     * The columns of the smallest unique index on $table that isn't the primary key itself,
     * or [] if the table has none.
     *
     * @return array<int, string>
     */
    private static function naturalKeyColumns(string $table): array
    {
        $index = DB::selectOne(<<<'SQL'
            SELECT i.indexrelid
            FROM pg_index i
            WHERE i.indrelid = ?::regclass AND i.indisunique AND NOT i.indisprimary AND i.indpred IS NULL
            ORDER BY array_length(i.indkey, 1) ASC
            LIMIT 1
        SQL, [$table]);

        if ($index === null) {
            return [];
        }

        return collect(DB::select(<<<'SQL'
            SELECT a.attname
            FROM pg_index i
            JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
            WHERE i.indexrelid = ?
            ORDER BY array_position(i.indkey, a.attnum)
        SQL, [$index->indexrelid]))->pluck('attname')->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $columns
     */
    private static function naturalKeyValue(array $row, array $columns): ?string
    {
        $parts = [];

        foreach ($columns as $column) {
            if (! array_key_exists($column, $row) || $row[$column] === null) {
                return null;
            }

            $parts[] = (string) $row[$column];
        }

        return implode(self::KEY_SEPARATOR, $parts);
    }

    /**
     * Replace every string value that exactly matches an old id in $map with its current id.
     * Ids embedded as substrings of a larger value (e.g. inside a JSON payload column) are
     * intentionally left as captured — those columns aren't foreign keys the database
     * enforces, only display payloads.
     */
    private static function remapValue(array $data, array $map): array
    {
        if ($map === []) {
            return $data;
        }

        return array_map(
            fn ($value) => is_array($value) ? self::remapValue($value, $map) : ($map[$value] ?? $value),
            $data
        );
    }

    private static function rowSets(array $tables): array
    {
        $sets = [];

        foreach ($tables as $table) {
            $sets[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }

        return $sets;
    }

    private static function tables(): array
    {
        return DB::table('information_schema.tables')
            ->where('table_schema', 'public')
            ->where('table_type', 'BASE TABLE')
            ->pluck('table_name')
            ->reject(fn ($table) => in_array($table, self::IGNORED_TABLES, true))
            ->values()
            ->all();
    }

    private static function primaryKeyColumns(string $table): array
    {
        return collect(DB::select(<<<'SQL'
            SELECT a.attname
            FROM pg_index i
            JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
            WHERE i.indrelid = ?::regclass AND i.indisprimary
            ORDER BY a.attnum
        SQL, [$table]))->pluck('attname')->all();
    }

    private static function hashRow(array $row): string
    {
        return hash('sha256', serialize($row));
    }

    private static function write(array $snapshot): void
    {
        $dir = dirname(self::DATA_PATH);

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(self::DATA_PATH, "<?php\n\nreturn ".var_export($snapshot, true).";\n");
        file_put_contents(self::HASH_PATH, self::currentSeederHash());
    }

    private static function currentSeederHash(): string
    {
        $files = glob(__DIR__.'/../../database/seeders/*.php');
        sort($files);

        $hash = hash_init('sha256');

        foreach ($files as $file) {
            hash_update($hash, (string) file_get_contents($file));
        }

        return hash_final($hash);
    }
}
