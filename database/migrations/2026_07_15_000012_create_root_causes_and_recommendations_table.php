<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('root_causes', function (Blueprint $table) {
            $table->uuid('root_cause_id')->primary();
            $table->uuid('assessment_id');
            $table->unsignedInteger('sub_index_id')->nullable();
            $table->text('description');
            $table->smallInteger('frequency_score')->nullable();
            $table->smallInteger('impact_score')->nullable();
            $table->smallInteger('time_cost_score')->nullable();
            $table->text('workaround_description')->nullable();
            $table->text('consequence_if_unresolved')->nullable();
            $table->decimal('current_spending_estimate', 15, 2)->nullable();
            $table->text('ideal_outcome_description')->nullable();
            $table->smallInteger('priority_rank')->nullable();
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('sub_index_id')->references('sub_index_id')->on('sub_indices');
        });

        Schema::create('recommendation_rules', function (Blueprint $table) {
            $table->increments('rule_id');
            $table->unsignedInteger('sub_index_id')->nullable();
            $table->unsignedSmallInteger('domain_id')->nullable();
            $table->decimal('score_below', 5, 2);
            $table->string('recommendation_type', 20);
            $table->text('recommendation_template');
            $table->boolean('is_active')->default(true);
            $table->foreign('sub_index_id')->references('sub_index_id')->on('sub_indices');
            $table->foreign('domain_id')->references('domain_id')->on('domains');
        });

        Schema::create('recommendations', function (Blueprint $table) {
            $table->uuid('recommendation_id')->primary();
            $table->uuid('assessment_id');
            $table->uuid('root_cause_id')->nullable();
            $table->unsignedInteger('rule_id')->nullable();
            $table->text('recommendation_text');
            $table->string('recommendation_type', 20);
            $table->smallInteger('priority_rank')->nullable();
            $table->string('status', 20)->default('PROPOSED');
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('root_cause_id')->references('root_cause_id')->on('root_causes');
            $table->foreign('rule_id')->references('rule_id')->on('recommendation_rules');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('recommendation_rules');
        Schema::dropIfExists('root_causes');
    }
};
