<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_index_scores', function (Blueprint $table) {
            $table->string('scoring_version', 50)->default('vytte-4.0-numeric-bands');
        });

        Schema::table('domain_scores', function (Blueprint $table) {
            $table->string('scoring_version', 50)->default('vytte-4.0-numeric-bands');
        });

        Schema::table('assessment_scores', function (Blueprint $table) {
            $table->string('scoring_version', 50)->default('vytte-4.0-numeric-bands');
        });
    }

    public function down(): void
    {
        Schema::table('sub_index_scores', function (Blueprint $table) {
            $table->dropColumn('scoring_version');
        });

        Schema::table('domain_scores', function (Blueprint $table) {
            $table->dropColumn('scoring_version');
        });

        Schema::table('assessment_scores', function (Blueprint $table) {
            $table->dropColumn('scoring_version');
        });
    }
};
