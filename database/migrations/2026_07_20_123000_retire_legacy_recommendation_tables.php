<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retires the dormant recommendation scaffolding.
 *
 * `recommendation_rules` encoded a single-threshold model: one sub-index or domain, one
 * score cut-off, one templated sentence. The P4 recommendation framework needs the
 * combination of objective, analysis lens, scores, responses, evidence, history,
 * benchmarks, risk level and pain points, which that shape cannot express. Extending it
 * would have carried the old assumption forward into the new framework.
 *
 * All three tables are empty and no application code reads them; they were flagged in
 * DATA_MODEL_AUDIT.md as present but not product authority. Retiring them now avoids
 * leaving two recommendation systems in the schema with no stated authority — the
 * pattern that let workspace suspension sit unenforced.
 *
 * `root_causes` goes with them: it exists only to be referenced by `recommendations`.
 * Root cause returns in P4 as an analysis lens over live results, not as a stored table
 * of pre-computed causes.
 *
 * Recorded in PRESERVATION_REGISTER.md. `down()` restores the original structure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('recommendation_rules');
        Schema::dropIfExists('root_causes');
    }

    public function down(): void
    {
        Schema::create('root_causes', function (Blueprint $table): void {
            $table->id('root_cause_id');
            $table->uuid('assessment_id')->nullable();
            $table->integer('sub_index_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('recommendation_rules', function (Blueprint $table): void {
            $table->id('rule_id');
            $table->integer('sub_index_id')->nullable();
            $table->integer('domain_id')->nullable();
            $table->decimal('score_below', 5, 2)->nullable();
            $table->string('recommendation_type')->nullable();
            $table->text('recommendation_template')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('recommendations', function (Blueprint $table): void {
            $table->id('recommendation_id');
            $table->uuid('assessment_id')->nullable();
            $table->unsignedBigInteger('root_cause_id')->nullable();
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->text('recommendation_text')->nullable();
            $table->string('recommendation_type')->nullable();
            $table->integer('priority_rank')->nullable();
            $table->string('status')->nullable();
        });
    }
};
