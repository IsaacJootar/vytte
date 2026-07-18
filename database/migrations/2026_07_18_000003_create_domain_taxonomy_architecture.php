<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_taxonomies', function (Blueprint $table) {
            $table->uuid('domain_taxonomy_id')->primary();
            $table->string('taxonomy_code', 40)->unique();
            $table->string('taxonomy_name', 160);
            $table->text('description')->nullable();
            $table->string('status', 25)->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('domain_taxonomy_versions', function (Blueprint $table) {
            $table->uuid('domain_taxonomy_version_id')->primary();
            $table->uuid('domain_taxonomy_id');
            $table->unsignedInteger('version_number');
            $table->string('status', 25)->default('DRAFT');
            $table->text('methodology_notes')->nullable();
            $table->json('rejected_candidates')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->uuid('parent_version_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestamps();
            $table->unique(['domain_taxonomy_id', 'version_number']);
            $table->foreign('domain_taxonomy_id')->references('domain_taxonomy_id')->on('domain_taxonomies')->cascadeOnDelete();
            $table->foreign('published_by')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['domain_taxonomy_id', 'status']);
        });

        Schema::create('domain_definitions', function (Blueprint $table) {
            $table->uuid('domain_definition_id')->primary();
            $table->uuid('domain_taxonomy_version_id');
            $table->unsignedSmallInteger('domain_id');
            $table->string('domain_code', 4);
            $table->string('domain_name', 100);
            $table->text('definition');
            $table->text('rationale');
            $table->unsignedSmallInteger('display_order');
            $table->timestamps();
            $table->unique(['domain_taxonomy_version_id', 'domain_code']);
            $table->foreign('domain_taxonomy_version_id')->references('domain_taxonomy_version_id')->on('domain_taxonomy_versions')->cascadeOnDelete();
            $table->foreign('domain_id')->references('domain_id')->on('domains');
        });

        Schema::create('framework_indicator_domain_mappings', function (Blueprint $table) {
            $table->uuid('indicator_domain_mapping_id')->primary();
            $table->uuid('framework_indicator_id');
            $table->uuid('domain_definition_id');
            $table->boolean('is_primary')->default(true);
            $table->decimal('contribution_weight', 6, 3)->default(1.000);
            $table->text('rationale')->nullable();
            $table->timestamps();
            $table->unique(['framework_indicator_id', 'domain_definition_id'], 'fw_ind_domain_unique');
            $table->foreign('framework_indicator_id', 'fw_ind_domain_indicator_fk')->references('framework_indicator_id')->on('framework_indicators')->cascadeOnDelete();
            $table->foreign('domain_definition_id', 'fw_ind_domain_definition_fk')->references('domain_definition_id')->on('domain_definitions');
            $table->index(['domain_definition_id', 'is_primary']);
        });

        Schema::create('framework_question_placement_domain_overrides', function (Blueprint $table) {
            $table->uuid('placement_domain_override_id')->primary();
            $table->uuid('framework_question_placement_id');
            $table->uuid('domain_definition_id');
            $table->boolean('is_primary')->default(true);
            $table->decimal('contribution_weight', 6, 3)->default(1.000);
            $table->text('rationale')->nullable();
            $table->timestamps();
            $table->unique(['framework_question_placement_id', 'domain_definition_id'], 'fw_place_domain_unique');
            $table->foreign('framework_question_placement_id', 'fw_place_domain_placement_fk')->references('framework_question_placement_id')->on('framework_question_placements')->cascadeOnDelete();
            $table->foreign('domain_definition_id', 'fw_place_domain_definition_fk')->references('domain_definition_id')->on('domain_definitions');
            $table->index(['domain_definition_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('framework_question_placement_domain_overrides');
        Schema::dropIfExists('framework_indicator_domain_mappings');
        Schema::dropIfExists('domain_definitions');
        Schema::dropIfExists('domain_taxonomy_versions');
        Schema::dropIfExists('domain_taxonomies');
    }
};
