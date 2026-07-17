<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('target_category_default_modules');

        Schema::table('targets', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id']);
            $table->dropColumn('category_id');
        });

        Schema::dropIfExists('target_categories');
    }

    public function down(): void
    {
        Schema::create('target_categories', function (Blueprint $table) {
            $table->smallIncrements('category_id');
            $table->string('target_type_code', 20);
            $table->string('category_code', 20);
            $table->string('category_name', 100);
            $table->text('description')->nullable();
            $table->unique(['target_type_code', 'category_code']);
            $table->foreign('target_type_code')->references('target_type_code')->on('target_types');
        });

        Schema::table('targets', function (Blueprint $table) {
            $table->unsignedSmallInteger('category_id')->nullable();
            $table->foreign('category_id')->references('category_id')->on('target_categories');
            $table->index('category_id');
        });

        Schema::create('target_category_default_modules', function (Blueprint $table) {
            $table->unsignedSmallInteger('category_id');
            $table->unsignedInteger('module_id');
            $table->boolean('is_default')->default(true);
            $table->primary(['category_id', 'module_id']);
            $table->foreign('category_id')->references('category_id')->on('target_categories');
            $table->foreign('module_id')->references('module_id')->on('assessment_modules');
        });
    }
};
