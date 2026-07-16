<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('respondent_consents', function (Blueprint $table) {
            $table->string('respondent_session_id', 36)->nullable()->after('module_id');
        });

        Schema::table('respondent_consents', function (Blueprint $table) {
            $table->uuid('consented_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('respondent_consents', function (Blueprint $table) {
            $table->dropColumn('respondent_session_id');
        });

        Schema::table('respondent_consents', function (Blueprint $table) {
            $table->uuid('consented_by')->nullable(false)->change();
        });
    }
};
