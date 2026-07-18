<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('numeric_unit', 40)->nullable();
            $table->decimal('numeric_min', 15, 4)->nullable();
            $table->decimal('numeric_max', 15, 4)->nullable();
            $table->decimal('numeric_step', 15, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['numeric_unit', 'numeric_min', 'numeric_max', 'numeric_step']);
        });
    }
};
