<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_modules', function (Blueprint $table) {
            $table->increments('module_id');
            $table->string('target_type_code', 20);
            $table->string('module_code', 10);
            $table->string('module_name', 150);
            $table->string('primary_respondent', 255)->nullable();
            $table->smallInteger('estimated_duration_minutes')->nullable();
            $table->string('data_collection_methods', 255)->nullable();
            $table->unique(['target_type_code', 'module_code']);
            $table->foreign('target_type_code')->references('target_type_code')->on('target_types');
        });

        Schema::create('target_category_default_modules', function (Blueprint $table) {
            $table->unsignedSmallInteger('category_id');
            $table->unsignedInteger('module_id');
            $table->boolean('is_default')->default(true);
            $table->primary(['category_id', 'module_id']);
            $table->foreign('category_id')->references('category_id')->on('target_categories');
            $table->foreign('module_id')->references('module_id')->on('assessment_modules');
        });

        Schema::create('module_domains', function (Blueprint $table) {
            $table->increments('module_domain_id');
            $table->unsignedInteger('module_id');
            $table->smallInteger('domain_number');
            $table->string('domain_label', 150);
            $table->unique(['module_id', 'domain_number']);
            $table->foreign('module_id')->references('module_id')->on('assessment_modules');
        });

        Schema::create('respondent_roles', function (Blueprint $table) {
            $table->increments('role_id');
            $table->string('target_type_code', 20);
            $table->string('role_code', 30);
            $table->string('role_name', 100);
            $table->boolean('is_internal')->default(true);
            $table->unique(['target_type_code', 'role_code']);
            $table->foreign('target_type_code')->references('target_type_code')->on('target_types');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respondent_roles');
        Schema::dropIfExists('module_domains');
        Schema::dropIfExists('target_category_default_modules');
        Schema::dropIfExists('assessment_modules');
    }
};
