<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The action domain — the one living, mutable part of the reporting phase.
     *
     * An action is born from a frozen recommendation but lives on its own: owned,
     * scheduled, worked, and verified over time. It is deliberately kept out of the
     * immutable report snapshot (see REPORTING_INTELLIGENCE_BLUEPRINT.md §3) so that a
     * report stays a photograph while the action plan changes daily. These two tables are
     * the only new schema in the whole reporting phase.
     */
    public function up(): void
    {
        Schema::create('assessment_actions', function (Blueprint $table) {
            $table->uuid('action_id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('assessment_id');
            $table->uuid('project_id');

            // The citation, frozen at creation. An action must always trace back to the
            // finding it came from — the same rule that governs recommendations. Copied in
            // rather than referenced so the action's provenance survives even as reports age.
            $table->string('source_finding_category', 40);
            $table->string('source_finding_subject');
            $table->text('source_finding_statement');
            $table->string('source_measurement_domain', 20)->nullable();
            $table->text('recommendation_statement');

            // The living fields.
            $table->string('title');
            $table->uuid('owner_user_id')->nullable();
            $table->string('priority', 20)->default('MEDIUM');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('OPEN');
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('workspace_id')->references('workspace_id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('project_id')->references('project_id')->on('projects')->cascadeOnDelete();
            $table->foreign('owner_user_id')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('verified_by')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->cascadeOnDelete();

            // Progress Tracking reads all actions for a project's target over time.
            $table->index(['project_id', 'status']);
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('action_updates', function (Blueprint $table) {
            $table->uuid('action_update_id')->primary();
            $table->uuid('action_id');
            $table->uuid('workspace_id');
            $table->uuid('author_user_id');
            $table->text('note')->nullable();
            $table->string('status_from', 20)->nullable();
            $table->string('status_to', 20)->nullable();
            $table->text('evidence_note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('action_id')->references('action_id')->on('assessment_actions')->cascadeOnDelete();
            $table->foreign('workspace_id')->references('workspace_id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('author_user_id')->references('user_id')->on('users')->cascadeOnDelete();

            $table->index('action_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_updates');
        Schema::dropIfExists('assessment_actions');
    }
};
