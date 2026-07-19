<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `framework_question_placements` carried a unique constraint on
 * (framework_version_id, display_order). Display order is presentation metadata, not a
 * governance invariant: reordering two placements must pass through a state where two
 * rows briefly share an order, so the constraint made any straight swap fail.
 *
 * `framework_sections` and `framework_indicators` order their rows without such a
 * constraint, so removing it also makes the three sibling tables behave consistently.
 *
 * The constraint is replaced by a plain index, which keeps ordered reads fast. No
 * governance rule depends on display-order uniqueness: publication validates structure,
 * response types, scoring-profile membership and domain mappings, none of which read it.
 */
return new class extends Migration
{
    /**
     * PostgreSQL truncates identifiers at 63 characters, so the constraint Laravel
     * generated is stored under a truncated name and cannot be dropped by column list.
     */
    private const CONSTRAINT_NAME = 'framework_question_placements_framework_version_id_display_orde';

    private const INDEX_NAME = 'fw_placement_version_display_order_index';

    public function up(): void
    {
        if ($this->constraintExists()) {
            DB::statement('ALTER TABLE framework_question_placements DROP CONSTRAINT '.self::CONSTRAINT_NAME);
        }

        if (! $this->indexExists()) {
            Schema::table('framework_question_placements', function (Blueprint $table) {
                $table->index(['framework_version_id', 'display_order'], self::INDEX_NAME);
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists()) {
            Schema::table('framework_question_placements', function (Blueprint $table) {
                $table->dropIndex(self::INDEX_NAME);
            });
        }

        // Restoring the constraint only succeeds when no framework holds duplicate display
        // orders. Rolling back after a reorder may require manual renumbering first.
        if (! $this->constraintExists()) {
            DB::statement(
                'ALTER TABLE framework_question_placements ADD CONSTRAINT '.self::CONSTRAINT_NAME
                .' UNIQUE (framework_version_id, display_order)'
            );
        }
    }

    private function constraintExists(): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('table_name', 'framework_question_placements')
            ->where('constraint_name', self::CONSTRAINT_NAME)
            ->exists();
    }

    private function indexExists(): bool
    {
        return DB::table('pg_indexes')
            ->where('tablename', 'framework_question_placements')
            ->where('indexname', self::INDEX_NAME)
            ->exists();
    }
};
