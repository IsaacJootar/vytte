<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_types', function (Blueprint $table) {
            $table->string('setting_type_code', 30)->primary();
            $table->string('setting_type_name', 100);
            $table->text('description')->nullable();
            $table->boolean('uses_departments')->default(false);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('display_order')->default(0);
        });

        Schema::create('health_domains', function (Blueprint $table) {
            $table->increments('health_domain_id');
            $table->string('domain_code', 40)->unique();
            $table->string('domain_name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('display_order')->default(0);
        });

        Schema::create('target_type_setting_map', function (Blueprint $table) {
            $table->string('target_type_code', 20)->primary();
            $table->string('setting_type_code', 30);
            $table->foreign('target_type_code')->references('target_type_code')->on('target_types');
            $table->foreign('setting_type_code')->references('setting_type_code')->on('setting_types');
        });

        Schema::create('assessment_module_health_domain', function (Blueprint $table) {
            $table->unsignedInteger('module_id');
            $table->unsignedInteger('health_domain_id');
            $table->boolean('is_primary')->default(false);
            $table->primary(['module_id', 'health_domain_id']);
            $table->foreign('module_id')->references('module_id')->on('assessment_modules')->cascadeOnDelete();
            $table->foreign('health_domain_id')->references('health_domain_id')->on('health_domains')->cascadeOnDelete();
        });

        Schema::table('targets', function (Blueprint $table) {
            $table->string('custom_setting_label', 120)->nullable();
            $table->boolean('uses_departments')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('targets', function (Blueprint $table) {
            $table->dropColumn(['custom_setting_label', 'uses_departments']);
        });

        Schema::dropIfExists('assessment_module_health_domain');
        Schema::dropIfExists('target_type_setting_map');
        Schema::dropIfExists('health_domains');
        Schema::dropIfExists('setting_types');
    }
};
