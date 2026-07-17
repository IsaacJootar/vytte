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
            $table->unsignedSmallInteger('category_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('targets')->whereNull('category_id')->exists()) {
            throw new RuntimeException('Cannot require target categories while category-free settings exist.');
        }

        Schema::table('targets', function (Blueprint $table) {
            $table->unsignedSmallInteger('category_id')->nullable(false)->change();
        });
    }
};
