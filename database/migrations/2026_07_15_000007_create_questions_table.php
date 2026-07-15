<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('question_id')->primary();
            $table->unsignedInteger('module_id')->nullable();
            $table->unsignedInteger('module_domain_id')->nullable();
            $table->smallInteger('question_number')->nullable();
            $table->string('question_code', 20)->unique();
            $table->text('question_text');
            $table->unsignedSmallInteger('type_id');
            $table->boolean('requires_observation')->default(false);
            $table->string('respondent_role_hint', 150)->nullable();
            $table->smallInteger('display_order');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_scored')->default(true);
            $table->string('source', 20)->default('CORE');
            $table->string('question_status', 20)->default('APPROVED');
            $table->unsignedInteger('standard_reference_id')->nullable();
            $table->string('standard_alignment_status', 25)->default('NEEDS_REVIEW');
            $table->unsignedInteger('corroborates_sub_index_id')->nullable();
            $table->unique(['module_domain_id', 'question_number']);
            $table->foreign('module_id')->references('module_id')->on('assessment_modules');
            $table->foreign('module_domain_id')->references('module_domain_id')->on('module_domains');
            $table->foreign('type_id')->references('type_id')->on('question_types');
            $table->foreign('standard_reference_id')->references('standard_id')->on('standards_registry');
            $table->foreign('corroborates_sub_index_id')->references('sub_index_id')->on('sub_indices');
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->increments('option_id');
            $table->uuid('question_id');
            $table->string('option_label', 255);
            $table->smallInteger('option_order');
            $table->decimal('score_weight', 5, 2)->nullable()->check('score_weight >= 0 AND score_weight <= 100');
            $table->boolean('is_flagged_pain_point')->default(false);
            $table->foreign('question_id')->references('question_id')->on('questions')->cascadeOnDelete();
        });

        Schema::create('question_numeric_bands', function (Blueprint $table) {
            $table->increments('band_id');
            $table->uuid('question_id');
            $table->decimal('min_value', 10, 4)->nullable();
            $table->decimal('max_value', 10, 4)->nullable();
            $table->decimal('score_weight', 5, 2)->check('score_weight >= 0 AND score_weight <= 100');
            $table->smallInteger('band_order');
            $table->foreign('question_id')->references('question_id')->on('questions')->cascadeOnDelete();
        });

        Schema::create('sub_index_questions', function (Blueprint $table) {
            $table->unsignedInteger('sub_index_id');
            $table->uuid('question_id');
            $table->decimal('weight', 4, 3)->default(1.000);
            $table->primary(['sub_index_id', 'question_id']);
            $table->foreign('sub_index_id')->references('sub_index_id')->on('sub_indices');
            $table->foreign('question_id')->references('question_id')->on('questions');
        });

        Schema::create('question_topics', function (Blueprint $table) {
            $table->uuid('question_id');
            $table->unsignedInteger('topic_id');
            $table->decimal('relevance_weight', 4, 3)->default(1.000);
            $table->primary(['question_id', 'topic_id']);
            $table->foreign('question_id')->references('question_id')->on('questions')->cascadeOnDelete();
            $table->foreign('topic_id')->references('topic_id')->on('topics')->cascadeOnDelete();
        });

        Schema::create('question_drafts', function (Blueprint $table) {
            $table->uuid('draft_id')->primary();
            $table->string('origin_type', 20);
            $table->unsignedInteger('topic_id')->nullable();
            $table->string('requested_topic_label', 150)->nullable();
            $table->uuid('question_id')->nullable();
            $table->text('draft_text');
            $table->string('draft_type', 30)->nullable();
            $table->unsignedInteger('standard_reference_id')->nullable();
            $table->text('standard_check_notes')->nullable();
            $table->uuid('requested_by')->nullable();
            $table->string('status', 20)->default('PENDING_REVIEW');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->foreign('topic_id')->references('topic_id')->on('topics');
            $table->foreign('question_id')->references('question_id')->on('questions');
            $table->foreign('standard_reference_id')->references('standard_id')->on('standards_registry');
            $table->foreign('requested_by')->references('user_id')->on('users');
            $table->foreign('reviewed_by')->references('user_id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_drafts');
        Schema::dropIfExists('question_topics');
        Schema::dropIfExists('sub_index_questions');
        Schema::dropIfExists('question_numeric_bands');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
    }
};
