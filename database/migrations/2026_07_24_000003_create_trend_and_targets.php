<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Longitudinal support (P4): assessment typing and performance targets.
     *
     * Typing turns a series of assessments into a story — baseline, midline, endline — so a
     * report can say "since baseline" rather than just "since last time". Targets give the
     * trend a destination: a goal score to measure current performance against.
     */
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            // BASELINE / MIDLINE / ENDLINE / FOLLOWUP; null = an ordinary run.
            $table->string('assessment_type', 20)->nullable()->after('scope_type');
        });

        Schema::create('performance_targets', function (Blueprint $table) {
            $table->uuid('target_goal_id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('project_id');
            // The domain this target applies to; null = the overall score.
            $table->string('domain_code', 20)->nullable();
            $table->decimal('target_score', 5, 2);
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('workspace_id')->references('workspace_id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('project_id')->references('project_id')->on('projects')->cascadeOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->cascadeOnDelete();

            // One target per project per scope (overall or a given domain).
            $table->unique(['project_id', 'domain_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_targets');
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('assessment_type');
        });
    }
};
