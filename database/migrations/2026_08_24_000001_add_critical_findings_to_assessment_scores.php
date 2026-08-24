<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * calibration_status already records THAT a critical failure occurred; nothing recorded WHY,
 * so every report showed the same generic sentence regardless of what actually tripped it.
 * critical_findings holds the human-readable reason(s) — the frozen per-question message and/or
 * any compound cross-question rule labels that fired — so the report can name the actual answer
 * or combination of answers responsible instead of a single unexplained flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_scores', function (Blueprint $table) {
            $table->json('critical_findings')->nullable()->after('calibration_status');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_scores', function (Blueprint $table) {
            $table->dropColumn('critical_findings');
        });
    }
};
