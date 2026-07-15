<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_index_scores', function (Blueprint $table) {
            $table->uuid('assessment_id');
            $table->unsignedInteger('sub_index_id');
            $table->string('respondent_type', 20)->default('STAFF');
            $table->decimal('score', 5, 2)->nullable()->check('score >= 0 AND score <= 100');
            $table->string('calibration_status', 30)->default('NOT_CALIBRATED');
            $table->unsignedSmallInteger('confidence_tier')->nullable();
            $table->timestamp('calculated_at')->useCurrent();
            $table->primary(['assessment_id', 'sub_index_id', 'respondent_type']);
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('sub_index_id')->references('sub_index_id')->on('sub_indices');
            $table->index('assessment_id');
        });

        Schema::create('corroboration_gaps', function (Blueprint $table) {
            $table->uuid('assessment_id');
            $table->unsignedInteger('sub_index_id');
            $table->string('voice_respondent_type', 20);
            $table->decimal('staff_score', 5, 2)->nullable();
            $table->decimal('voice_score', 5, 2)->nullable();
            $table->decimal('gap_magnitude', 5, 2)->nullable();
            $table->timestamp('flagged_at')->useCurrent();
            $table->primary(['assessment_id', 'sub_index_id', 'voice_respondent_type']);
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('sub_index_id')->references('sub_index_id')->on('sub_indices');
        });

        Schema::create('domain_scores', function (Blueprint $table) {
            $table->uuid('assessment_id');
            $table->unsignedSmallInteger('domain_id');
            $table->decimal('score', 5, 2)->nullable()->check('score >= 0 AND score <= 100');
            $table->string('calibration_status', 30)->default('NOT_CALIBRATED');
            $table->timestamp('calculated_at')->useCurrent();
            $table->primary(['assessment_id', 'domain_id']);
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('domain_id')->references('domain_id')->on('domains');
            $table->index('assessment_id');
        });

        Schema::create('topic_scores', function (Blueprint $table) {
            $table->uuid('assessment_id');
            $table->unsignedInteger('topic_id');
            $table->decimal('score', 5, 2)->check('score >= 0 AND score <= 100');
            $table->smallInteger('questions_answered')->nullable();
            $table->smallInteger('questions_expected')->nullable();
            $table->timestamp('calculated_at')->useCurrent();
            $table->primary(['assessment_id', 'topic_id']);
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('topic_id')->references('topic_id')->on('topics');
            $table->index('assessment_id');
        });

        Schema::create('assessment_scores', function (Blueprint $table) {
            $table->uuid('assessment_id')->primary();
            $table->decimal('overall_score', 5, 2)->nullable()->check('overall_score >= 0 AND overall_score <= 100');
            $table->unsignedSmallInteger('maturity_level_id')->nullable();
            $table->string('calibration_status', 30)->default('NOT_CALIBRATED');
            $table->decimal('clinical_quality_score', 5, 2)->nullable();
            $table->smallInteger('expected_module_count')->nullable();
            $table->smallInteger('active_module_count')->nullable();
            $table->timestamp('calculated_at')->useCurrent();
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('maturity_level_id')->references('level_id')->on('maturity_levels');
        });

        Schema::create('project_domain_scores', function (Blueprint $table) {
            $table->uuid('project_id');
            $table->unsignedSmallInteger('domain_id');
            $table->decimal('avg_score', 5, 2)->check('avg_score >= 0 AND avg_score <= 100');
            $table->smallInteger('target_count');
            $table->timestamp('calculated_at')->useCurrent();
            $table->primary(['project_id', 'domain_id']);
            $table->foreign('project_id')->references('project_id')->on('projects')->cascadeOnDelete();
            $table->foreign('domain_id')->references('domain_id')->on('domains');
        });

        Schema::create('project_scores', function (Blueprint $table) {
            $table->uuid('project_id')->primary();
            $table->decimal('avg_overall_score', 5, 2)->check('avg_overall_score >= 0 AND avg_overall_score <= 100');
            $table->smallInteger('target_count');
            $table->timestamp('calculated_at')->useCurrent();
            $table->foreign('project_id')->references('project_id')->on('projects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_scores');
        Schema::dropIfExists('project_domain_scores');
        Schema::dropIfExists('assessment_scores');
        Schema::dropIfExists('topic_scores');
        Schema::dropIfExists('domain_scores');
        Schema::dropIfExists('corroboration_gaps');
        Schema::dropIfExists('sub_index_scores');
    }
};
