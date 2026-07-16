<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('targets', function (Blueprint $table) {
            $table->string('country', 100)->nullable()->after('category_id');
        });

        DB::table('targets')->update(['country' => 'Nigeria']);

        Schema::table('targets', function (Blueprint $table) {
            $table->renameColumn('state', 'region');
            $table->renameColumn('lga', 'sub_region');
        });
    }

    public function down(): void
    {
        Schema::table('targets', function (Blueprint $table) {
            $table->renameColumn('region', 'state');
            $table->renameColumn('sub_region', 'lga');
        });

        Schema::table('targets', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
