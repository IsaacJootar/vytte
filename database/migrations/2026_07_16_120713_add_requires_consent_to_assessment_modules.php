<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_modules', function (Blueprint $table) {
            $table->boolean('requires_consent')->default(false)->after('is_active');
        });

        DB::table('assessment_modules')
            ->where('module_code', 'HIVAW')
            ->update(['requires_consent' => true]);
    }

    public function down(): void
    {
        Schema::table('assessment_modules', function (Blueprint $table) {
            $table->dropColumn('requires_consent');
        });
    }
};
