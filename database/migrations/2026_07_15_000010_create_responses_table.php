<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responses', function (Blueprint $table) {
            $table->uuid('response_id')->primary();
            $table->uuid('assessment_id');
            $table->uuid('question_id');
            $table->uuid('respondent_id')->nullable();
            $table->text('value_text')->nullable();
            $table->decimal('value_numeric', 15, 4)->nullable();
            $table->unsignedInteger('value_option_id')->nullable();
            $table->timestamp('answered_at')->useCurrent();
            $table->unique(['assessment_id', 'question_id', 'respondent_id']);
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('question_id')->references('question_id')->on('questions');
            $table->foreign('respondent_id')->references('respondent_id')->on('respondents');
            $table->foreign('value_option_id')->references('option_id')->on('question_options');
            $table->index('assessment_id');
            $table->index('question_id');
        });

        Schema::create('response_options', function (Blueprint $table) {
            $table->uuid('response_id');
            $table->unsignedInteger('option_id');
            $table->primary(['response_id', 'option_id']);
            $table->foreign('response_id')->references('response_id')->on('responses')->cascadeOnDelete();
            $table->foreign('option_id')->references('option_id')->on('question_options');
        });

        Schema::create('observation_records', function (Blueprint $table) {
            $table->uuid('observation_id')->primary();
            $table->uuid('assessment_id');
            $table->unsignedInteger('module_id');
            $table->uuid('question_id')->nullable();
            $table->text('observed_value_text')->nullable();
            $table->decimal('observed_value_numeric', 15, 4)->nullable();
            $table->string('observation_type', 30)->nullable();
            $table->timestamp('observed_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('module_id')->references('module_id')->on('assessment_modules');
            $table->foreign('question_id')->references('question_id')->on('questions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observation_records');
        Schema::dropIfExists('response_options');
        Schema::dropIfExists('responses');
    }
};
