<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assessment_respondent_tokens', 'created_by')) {
            Schema::table('assessment_respondent_tokens', function (Blueprint $table) {
                $table->uuid('created_by')->nullable()->after('assessment_id');
                $table->timestamp('revoked_at')->nullable()->after('expires_at');
                $table->unsignedInteger('use_count')->default(0)->after('revoked_at');
                $table->timestamp('last_used_at')->nullable()->after('use_count');
                $table->foreign('created_by')->references('user_id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('public_response_sessions')) {
            Schema::create('public_response_sessions', function (Blueprint $table) {
                $table->uuid('session_id')->primary();
                $table->char('token', 32);
                $table->uuid('assessment_id');
                $table->string('locale', 10)->default('en');
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('last_activity_at')->useCurrent();
                $table->timestamp('submitted_at')->nullable();

                // Tokens are revoked, not deleted. Restrict deletion so collected responses cannot
                // disappear merely because an access link is being retired.
                $table->foreign('token')->references('token')->on('assessment_respondent_tokens');
                $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
                $table->index(['assessment_id', 'submitted_at']);
            });
        }

        // Recreate the staff-only partial index after the public-response identity column exists.
        DB::statement('DROP INDEX IF EXISTS responses_staff_assessment_question_unique');
        if (! Schema::hasColumn('responses', 'public_response_session_id')) {
            Schema::table('responses', function (Blueprint $table) {
                $table->uuid('public_response_session_id')->nullable()->after('respondent_id');
                $table->foreign('public_response_session_id')
                    ->references('session_id')
                    ->on('public_response_sessions')
                    ->cascadeOnDelete();
                $table->index('public_response_session_id');
            });
        }
        DB::statement(
            'CREATE UNIQUE INDEX responses_staff_assessment_question_unique
             ON responses (assessment_id, question_id)
             WHERE respondent_id IS NULL AND public_response_session_id IS NULL'
        );

        if (! Schema::hasColumn('respondent_consents', 'public_response_session_id')) {
            Schema::table('respondent_consents', function (Blueprint $table) {
                $table->uuid('public_response_session_id')->nullable()->after('respondent_session_id');
                $table->foreign('public_response_session_id')
                    ->references('session_id')
                    ->on('public_response_sessions')
                    ->cascadeOnDelete();
                $table->index('public_response_session_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('respondent_consents', function (Blueprint $table) {
            $table->dropForeign(['public_response_session_id']);
            $table->dropIndex(['public_response_session_id']);
            $table->dropColumn('public_response_session_id');
        });

        DB::statement('DROP INDEX IF EXISTS responses_staff_assessment_question_unique');
        Schema::table('responses', function (Blueprint $table) {
            $table->dropForeign(['public_response_session_id']);
            $table->dropIndex(['public_response_session_id']);
            $table->dropColumn('public_response_session_id');
        });
        DB::statement(
            'CREATE UNIQUE INDEX responses_staff_assessment_question_unique
             ON responses (assessment_id, question_id)
             WHERE respondent_id IS NULL'
        );

        Schema::dropIfExists('public_response_sessions');

        Schema::table('assessment_respondent_tokens', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['created_by', 'revoked_at', 'use_count', 'last_used_at']);
        });
    }
};
