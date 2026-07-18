<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_versions', function (Blueprint $table) {
            $table->uuid('question_version_id')->primary();
            $table->uuid('question_id');
            $table->unsignedInteger('version_number');
            $table->string('status', 25)->default('DRAFT');
            $table->text('question_text');
            $table->unsignedSmallInteger('type_id');
            $table->json('options')->nullable();
            $table->json('numeric_config')->nullable();
            $table->json('numeric_bands')->nullable();
            $table->boolean('requires_observation')->default(false);
            $table->string('respondent_role_hint', 150)->nullable();
            $table->text('methodology_notes')->nullable();
            $table->text('source_summary')->nullable();
            $table->text('review_notes')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->date('effective_date')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestamps();
            $table->unique(['question_id', 'version_number']);
            $table->foreign('question_id')->references('question_id')->on('questions')->cascadeOnDelete();
            $table->foreign('type_id')->references('type_id')->on('question_types');
            $table->foreign('reviewed_by')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('published_by')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['question_id', 'status']);
        });

        Schema::table('department_framework_versions', function (Blueprint $table) {
            $table->string('framework_type', 25)->default('DEPARTMENT')->after('module_id');
            $table->text('purpose')->nullable()->after('description');
            $table->text('methodology_notes')->nullable()->after('license_code');
            $table->text('source_summary')->nullable()->after('methodology_notes');
            $table->text('review_notes')->nullable()->after('source_summary');
            $table->uuid('reviewed_by')->nullable()->after('review_notes');
            $table->uuid('approved_by')->nullable()->after('reviewed_by');
            $table->date('effective_date')->nullable()->after('approved_by');
            $table->foreign('reviewed_by')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['framework_type', 'status']);
        });

        Schema::create('framework_sections', function (Blueprint $table) {
            $table->uuid('framework_section_id')->primary();
            $table->uuid('framework_version_id');
            $table->string('section_code', 80);
            $table->string('section_name', 180);
            $table->text('purpose')->nullable();
            $table->unsignedSmallInteger('display_order')->default(1);
            $table->timestamps();
            $table->unique(['framework_version_id', 'section_code']);
            $table->foreign('framework_version_id')->references('framework_version_id')->on('department_framework_versions')->cascadeOnDelete();
        });

        Schema::create('framework_indicators', function (Blueprint $table) {
            $table->uuid('framework_indicator_id')->primary();
            $table->uuid('framework_version_id');
            $table->uuid('framework_section_id');
            $table->string('indicator_code', 80);
            $table->string('indicator_name', 180);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('display_order')->default(1);
            $table->timestamps();
            $table->unique(['framework_version_id', 'indicator_code']);
            $table->foreign('framework_version_id')->references('framework_version_id')->on('department_framework_versions')->cascadeOnDelete();
            $table->foreign('framework_section_id')->references('framework_section_id')->on('framework_sections')->cascadeOnDelete();
        });

        Schema::create('framework_question_placements', function (Blueprint $table) {
            $table->uuid('framework_question_placement_id')->primary();
            $table->uuid('framework_version_id');
            $table->uuid('framework_section_id');
            $table->uuid('framework_indicator_id');
            $table->uuid('question_id');
            $table->uuid('question_version_id');
            $table->unsignedInteger('sub_index_id')->nullable();
            $table->unsignedSmallInteger('display_order')->default(1);
            $table->boolean('is_required')->default(true);
            $table->json('applicability')->nullable();
            $table->text('evidence_expectation')->nullable();
            $table->decimal('weight', 6, 3)->default(1.000);
            $table->boolean('scoring_contribution')->default(true);
            $table->string('criticality', 30)->default('STANDARD');
            $table->text('help_text')->nullable();
            $table->text('local_display_text')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['framework_version_id', 'display_order']);
            $table->foreign('framework_version_id')->references('framework_version_id')->on('department_framework_versions')->cascadeOnDelete();
            $table->foreign('framework_section_id')->references('framework_section_id')->on('framework_sections')->cascadeOnDelete();
            $table->foreign('framework_indicator_id')->references('framework_indicator_id')->on('framework_indicators')->cascadeOnDelete();
            $table->foreign('question_id')->references('question_id')->on('questions');
            $table->foreign('question_version_id')->references('question_version_id')->on('question_versions');
            $table->foreign('sub_index_id')->references('sub_index_id')->on('sub_indices')->nullOnDelete();
            $table->index(['framework_version_id', 'question_version_id']);
            $table->index(['question_id', 'question_version_id']);
        });

        Schema::create('workspace_custom_assessment_designs', function (Blueprint $table) {
            $table->uuid('custom_assessment_design_id')->primary();
            $table->uuid('workspace_id');
            $table->string('title', 180);
            $table->text('purpose');
            $table->string('scope', 180)->nullable();
            $table->string('setting', 180)->nullable();
            $table->string('target_population', 180)->nullable();
            $table->string('respondent_type', 180)->nullable();
            $table->string('status', 25)->default('DRAFT');
            $table->json('sections')->nullable();
            $table->json('indicators')->nullable();
            $table->json('questions')->nullable();
            $table->json('evidence_requests')->nullable();
            $table->json('descriptive_outputs')->nullable();
            $table->json('private_scoring_config')->nullable();
            $table->json('ai_drafting_context')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('workspace_id')->references('workspace_id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_custom_assessment_designs');
        Schema::dropIfExists('framework_question_placements');
        Schema::dropIfExists('framework_indicators');
        Schema::dropIfExists('framework_sections');

        Schema::table('department_framework_versions', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'framework_type',
                'purpose',
                'methodology_notes',
                'source_summary',
                'review_notes',
                'reviewed_by',
                'approved_by',
                'effective_date',
            ]);
        });

        Schema::dropIfExists('question_versions');
    }
};
