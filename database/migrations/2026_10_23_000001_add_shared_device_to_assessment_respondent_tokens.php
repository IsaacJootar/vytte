<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Shared device": the owner of an assessment can mark its respondent link as one that several
 * people will use on the same phone or tablet (for example a focal person taking girls through the
 * assessment one at a time). The setting lives on the link, not on the frozen template, so it can be
 * switched on or off for any assessment, including ones already collecting. Off by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_respondent_tokens', function (Blueprint $table) {
            $table->boolean('shared_device')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('assessment_respondent_tokens', function (Blueprint $table) {
            $table->dropColumn('shared_device');
        });
    }
};
