<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an action be joined back to the exact governed finding it addresses, and the exact
 * frozen report it was created from.
 *
 * Before this, an action's provenance was frozen free-text only (source_finding_category/
 * subject/statement) — faithful to the report at creation time, but not queryable. Without a
 * stable key, "actions connected to new or persistent issues"
 * (docs/architecture/ASSESSMENT_PORTFOLIO_NEXT_UPDATE.md) cannot be computed. finding_key
 * matches the key already minted for the finding in DiagnosticsService — a finding, not a
 * single failed indicator, because that is the granularity an action is actually created at:
 * one recommendation cites one finding, which may bundle several failed indicators.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_actions', function (Blueprint $table) {
            $table->string('finding_key', 64)->nullable()->after('source_measurement_domain');
            $table->uuid('report_snapshot_id')->nullable()->after('assessment_id');
            $table->foreign('report_snapshot_id')->references('report_snapshot_id')->on('assessment_report_snapshots')->nullOnDelete();
            $table->index('finding_key');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_actions', function (Blueprint $table) {
            $table->dropForeign(['report_snapshot_id']);
            $table->dropIndex(['finding_key']);
            $table->dropColumn(['finding_key', 'report_snapshot_id']);
        });
    }
};
