<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('local_custom_sections', function (Blueprint $table) {
            // When a tailored section is answered by many respondents (shared-link collection),
            // each respondent's answers are kept here keyed by their response session id. The
            // finalised custom_score is the average of every respondent's private 0-100 score.
            $table->json('respondent_answers')->nullable()->after('answers');
        });
    }

    public function down(): void
    {
        Schema::table('local_custom_sections', function (Blueprint $table) {
            $table->dropColumn('respondent_answers');
        });
    }
};
