<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The evidence & observation triad: respondent claim (existing), assessor-observed status,
 * and a concrete evidence checklist — WHO HHFA-style verification for selected questions.
 *
 * question_versions.requires_observation already exists but has never been wired to any
 * runner behaviour; observation_checklist is the author-defined set of specific things an
 * assessor could point to as proof (e.g. "modem/router observed"), alongside it.
 *
 * responses.evidence_note already carries the respondent's own free-text note; the two new
 * columns here carry the independent assessor layer on top of it, never replacing it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_versions', function (Blueprint $table) {
            $table->json('observation_checklist')->nullable();
        });

        Schema::table('responses', function (Blueprint $table) {
            $table->string('observation_status', 20)->nullable();
            $table->json('evidence_checked')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropColumn(['observation_status', 'evidence_checked']);
        });

        Schema::table('question_versions', function (Blueprint $table) {
            $table->dropColumn('observation_checklist');
        });
    }
};
