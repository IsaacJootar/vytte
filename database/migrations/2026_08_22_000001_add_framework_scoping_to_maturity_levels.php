<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets one Focused framework declare its own maturity-band names and thresholds
     * (Odion Ikyo's "Vyttes maturity bands", explicitly not the platform default),
     * without touching the shared bands every other assessment already relies on.
     *
     * A row with framework_version_id NULL is a platform-default band, exactly as today.
     * A row with framework_version_id set is that one framework's own override. The unique
     * constraint moves from level_number alone to (level_number, framework_version_id), so
     * every framework can freely reuse levels 1-5 without colliding with the defaults or
     * with each other.
     */
    public function up(): void
    {
        Schema::table('maturity_levels', function (Blueprint $table) {
            $table->uuid('framework_version_id')->nullable()->after('level_id');
            $table->foreign('framework_version_id')->references('framework_version_id')->on('department_framework_versions')->cascadeOnDelete();
        });

        Schema::table('maturity_levels', function (Blueprint $table) {
            $table->dropUnique(['level_number']);
            $table->unique(['level_number', 'framework_version_id']);
        });
    }

    public function down(): void
    {
        Schema::table('maturity_levels', function (Blueprint $table) {
            $table->dropUnique(['level_number', 'framework_version_id']);
        });

        Schema::table('maturity_levels', function (Blueprint $table) {
            $table->dropForeign(['framework_version_id']);
            $table->dropColumn('framework_version_id');
            $table->unique('level_number');
        });
    }
};
