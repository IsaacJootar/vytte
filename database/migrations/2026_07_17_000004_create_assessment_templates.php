<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_templates', function (Blueprint $table) {
            $table->uuid('template_id')->primary();
            $table->string('template_code', 60)->unique();
            $table->string('template_name', 180);
            $table->text('description')->nullable();
            $table->string('creation_path', 20);
            $table->string('setting_type_code', 30)->nullable();
            $table->unsignedInteger('health_domain_id')->nullable();
            $table->string('source_authority', 180)->nullable();
            $table->text('source_url')->nullable();
            $table->string('license_code', 80)->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('setting_type_code')->references('setting_type_code')->on('setting_types');
            $table->foreign('health_domain_id')->references('health_domain_id')->on('health_domains');
            $table->foreign('created_by')->references('user_id')->on('users');
            $table->index(['creation_path', 'status']);
            $table->index('health_domain_id');
        });

        Schema::create('assessment_template_versions', function (Blueprint $table) {
            $table->uuid('template_version_id')->primary();
            $table->uuid('template_id');
            $table->unsignedInteger('version_number');
            $table->string('status', 20)->default('DRAFT');
            $table->string('scoring_version', 50)->default('vytte-2.0-normalized');
            $table->string('content_hash', 64)->nullable();
            $table->uuid('parent_version_id')->nullable();
            $table->boolean('is_customized')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestamps();
            $table->unique(['template_id', 'version_number']);
            $table->foreign('template_id')->references('template_id')->on('assessment_templates')->cascadeOnDelete();
            $table->foreign('parent_version_id')->references('template_version_id')->on('assessment_template_versions');
            $table->foreign('published_by')->references('user_id')->on('users');
        });

        Schema::create('assessment_template_version_modules', function (Blueprint $table) {
            $table->uuid('template_version_id');
            $table->unsignedInteger('module_id');
            $table->smallInteger('display_order')->default(0);
            $table->boolean('is_default')->default(true);
            $table->string('area_label', 180)->nullable();
            $table->primary(['template_version_id', 'module_id']);
            $table->foreign('template_version_id')->references('template_version_id')->on('assessment_template_versions')->cascadeOnDelete();
            $table->foreign('module_id')->references('module_id')->on('assessment_modules');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_template_version_modules');
        Schema::dropIfExists('assessment_template_versions');
        Schema::dropIfExists('assessment_templates');
    }
};
