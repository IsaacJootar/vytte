<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_framework_versions', function (Blueprint $table) {
            $table->uuid('framework_version_id')->primary();
            $table->unsignedInteger('module_id');
            $table->unsignedInteger('version_number');
            $table->string('status', 20)->default('DRAFT');
            $table->string('display_name', 180);
            $table->text('description')->nullable();
            $table->string('source_authority', 180)->nullable();
            $table->text('source_url')->nullable();
            $table->string('license_code', 80)->nullable();
            $table->json('provenance')->nullable();
            $table->json('evidence_requirements')->nullable();
            $table->json('critical_failure_rules')->nullable();
            $table->string('scoring_version', 50)->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->json('published_payload')->nullable();
            $table->uuid('parent_version_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestamps();
            $table->unique(['module_id', 'version_number']);
            $table->foreign('module_id')->references('module_id')->on('assessment_modules');
            $table->foreign('published_by')->references('user_id')->on('users');
            $table->index(['module_id', 'status']);
        });

        Schema::create('facility_profiles', function (Blueprint $table) {
            $table->uuid('facility_profile_id')->primary();
            $table->string('profile_code', 60)->unique();
            $table->string('profile_name', 180);
            $table->string('setting_type_code', 30);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->smallInteger('display_order')->default(0);
            $table->timestamps();
            $table->foreign('setting_type_code')->references('setting_type_code')->on('setting_types');
            $table->index(['setting_type_code', 'status']);
        });

        Schema::create('facility_profile_departments', function (Blueprint $table) {
            $table->uuid('facility_profile_id');
            $table->unsignedInteger('module_id');
            $table->string('applicability', 20);
            $table->smallInteger('display_order')->default(0);
            $table->boolean('removal_allowed')->default(true);
            $table->primary(['facility_profile_id', 'module_id']);
            $table->foreign('facility_profile_id')->references('facility_profile_id')->on('facility_profiles')->cascadeOnDelete();
            $table->foreign('module_id')->references('module_id')->on('assessment_modules');
            $table->index(['module_id', 'applicability']);
        });

        Schema::create('assessment_catalogue_releases', function (Blueprint $table) {
            $table->uuid('catalogue_release_id')->primary();
            $table->string('release_code', 80)->unique();
            $table->string('release_name', 180);
            $table->text('description')->nullable();
            $table->string('creation_path', 20);
            $table->uuid('facility_profile_id')->nullable();
            $table->unsignedInteger('health_domain_id')->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->json('aggregation_policy');
            $table->json('composition_rules')->nullable();
            $table->json('collection_config')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestamps();
            $table->foreign('facility_profile_id')->references('facility_profile_id')->on('facility_profiles');
            $table->foreign('health_domain_id')->references('health_domain_id')->on('health_domains');
            $table->foreign('published_by')->references('user_id')->on('users');
            $table->index(['creation_path', 'status']);
            $table->index('facility_profile_id');
            $table->index('health_domain_id');
        });

        Schema::create('assessment_catalogue_department_versions', function (Blueprint $table) {
            $table->uuid('catalogue_release_id');
            $table->uuid('framework_version_id');
            $table->unsignedInteger('module_id');
            $table->string('applicability', 20);
            $table->smallInteger('display_order')->default(0);
            $table->string('area_label', 180)->nullable();
            $table->primary(['catalogue_release_id', 'framework_version_id']);
            $table->unique(['catalogue_release_id', 'module_id']);
            $table->foreign('catalogue_release_id')->references('catalogue_release_id')->on('assessment_catalogue_releases')->cascadeOnDelete();
            $table->foreign('framework_version_id')->references('framework_version_id')->on('department_framework_versions');
            $table->foreign('module_id')->references('module_id')->on('assessment_modules');
            $table->index(['module_id', 'applicability']);
        });

        Schema::create('local_custom_sections', function (Blueprint $table) {
            $table->uuid('local_section_id')->primary();
            $table->uuid('assessment_id');
            $table->uuid('workspace_id');
            $table->string('section_title', 180);
            $table->text('instructions')->nullable();
            $table->json('questions')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('workspace_id')->references('workspace_id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users');
            $table->index(['assessment_id', 'workspace_id']);
        });

        Schema::table('targets', function (Blueprint $table) {
            $table->uuid('facility_profile_id')->nullable()->after('uses_departments');
            $table->foreign('facility_profile_id')->references('facility_profile_id')->on('facility_profiles');
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->uuid('catalogue_release_id')->nullable()->after('creation_path');
            $table->foreign('catalogue_release_id')->references('catalogue_release_id')->on('assessment_catalogue_releases');
        });

        Schema::table('assessment_snapshots', function (Blueprint $table) {
            $table->uuid('catalogue_release_id')->nullable()->after('assessment_id');
            $table->uuid('facility_profile_id')->nullable()->after('catalogue_release_id');
            $table->json('composition_manifest')->nullable()->after('is_customized');
            $table->json('aggregation_policy')->nullable()->after('composition_manifest');
            $table->foreign('catalogue_release_id')->references('catalogue_release_id')->on('assessment_catalogue_releases');
            $table->foreign('facility_profile_id')->references('facility_profile_id')->on('facility_profiles');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_snapshots', function (Blueprint $table) {
            $table->dropForeign(['catalogue_release_id']);
            $table->dropForeign(['facility_profile_id']);
            $table->dropColumn(['catalogue_release_id', 'facility_profile_id', 'composition_manifest', 'aggregation_policy']);
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropForeign(['catalogue_release_id']);
            $table->dropColumn('catalogue_release_id');
        });

        Schema::table('targets', function (Blueprint $table) {
            $table->dropForeign(['facility_profile_id']);
            $table->dropColumn('facility_profile_id');
        });

        Schema::dropIfExists('local_custom_sections');
        Schema::dropIfExists('assessment_catalogue_department_versions');
        Schema::dropIfExists('assessment_catalogue_releases');
        Schema::dropIfExists('facility_profile_departments');
        Schema::dropIfExists('facility_profiles');
        Schema::dropIfExists('department_framework_versions');
    }
};
