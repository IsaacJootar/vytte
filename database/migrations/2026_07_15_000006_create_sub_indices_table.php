<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_indices', function (Blueprint $table) {
            $table->increments('sub_index_id');
            $table->unsignedInteger('module_id');
            $table->unsignedSmallInteger('domain_id');
            $table->string('acronym', 10)->unique();
            $table->string('full_name', 200);
            $table->text('description')->nullable();
            $table->text('calculation_method')->nullable();
            $table->foreign('module_id')->references('module_id')->on('assessment_modules');
            $table->foreign('domain_id')->references('domain_id')->on('domains');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_indices');
    }
};
