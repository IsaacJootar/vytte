<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS responses_staff_assessment_question_unique');
        DB::statement(
            'CREATE UNIQUE INDEX responses_staff_assessment_question_unique
             ON responses (assessment_id, question_id)
             WHERE respondent_id IS NULL AND public_response_session_id IS NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX responses_public_session_question_unique
             ON responses (public_response_session_id, question_id)
             WHERE public_response_session_id IS NOT NULL'
        );

        Schema::table('assessment_snapshots', function (Blueprint $table) {
            $table->json('collection_config')->nullable();
        });

        Schema::table('public_response_sessions', function (Blueprint $table) {
            $table->string('eligibility_status', 20)->default('PENDING');
            $table->text('eligibility_reason')->nullable();
            $table->boolean('is_test')->default(false);
            $table->uuid('eligibility_reviewed_by')->nullable();
            $table->timestamp('eligibility_reviewed_at')->nullable();
            $table->json('response_snapshot')->nullable();
            $table->string('response_snapshot_hash', 64)->nullable();
            $table->foreign('eligibility_reviewed_by')->references('user_id')->on('users')->nullOnDelete();
        });

        Schema::create('respondent_score_results', function (Blueprint $table) {
            $table->uuid('score_result_id')->primary();
            $table->uuid('public_response_session_id')->unique();
            $table->uuid('assessment_id');
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->string('calibration_status', 30);
            $table->string('scoring_version', 50);
            $table->string('input_hash', 64);
            $table->string('result_hash', 64);
            $table->json('payload');
            $table->timestamp('calculated_at')->useCurrent();
            $table->foreign('public_response_session_id')->references('session_id')->on('public_response_sessions')->cascadeOnDelete();
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->index('assessment_id');
        });

        Schema::create('assessment_aggregation_results', function (Blueprint $table) {
            $table->uuid('aggregation_result_id')->primary();
            $table->uuid('assessment_id')->unique();
            $table->string('aggregation_method', 40);
            $table->unsignedInteger('minimum_completed_respondents');
            $table->unsignedInteger('eligible_respondent_count');
            $table->unsignedInteger('excluded_session_count');
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->string('calibration_status', 30);
            $table->string('scoring_version', 50);
            $table->string('input_hash', 64);
            $table->string('result_hash', 64);
            $table->json('payload');
            $table->uuid('finalized_by');
            $table->timestamp('finalized_at');
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('finalized_by')->references('user_id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_aggregation_results');
        Schema::dropIfExists('respondent_score_results');
        DB::statement('DROP INDEX IF EXISTS responses_public_session_question_unique');
        DB::statement('DROP INDEX IF EXISTS responses_staff_assessment_question_unique');
        DB::statement(
            'CREATE UNIQUE INDEX responses_staff_assessment_question_unique
             ON responses (assessment_id, question_id)
             WHERE respondent_id IS NULL AND public_response_session_id IS NULL'
        );

        Schema::table('public_response_sessions', function (Blueprint $table) {
            $table->dropForeign(['eligibility_reviewed_by']);
            $table->dropColumn([
                'eligibility_status',
                'eligibility_reason',
                'is_test',
                'eligibility_reviewed_by',
                'eligibility_reviewed_at',
                'response_snapshot',
                'response_snapshot_hash',
            ]);
        });

        Schema::table('assessment_snapshots', function (Blueprint $table) {
            $table->dropColumn('collection_config');
        });

    }
};
