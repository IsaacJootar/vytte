<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The methodology layer: the official health knowledge model.
 *
 * This sits ABOVE the existing platform. It adds no column to, and changes no behaviour
 * of, questions, frameworks, catalogue releases, snapshots, scoring or reporting. An
 * assessment that never references an objective behaves exactly as it does today.
 *
 * Everything is published as one coherent methodology version rather than per entity,
 * because objectives recommend lenses, templates and domains. Independent versioning
 * would allow publishing an objective pointing at a lens that does not exist yet, and
 * the recommendation would be silently empty.
 *
 * Naming, settled in P4:
 *   - Health Domain      — existing `health_domains`. The subject: HIV, Malaria, WASH.
 *   - Health Area        — new. A subdivision of a health domain.
 *   - Measurement Domain — existing `domains`. What scores roll up into. No longer
 *                          described as an "analytical lens".
 *   - Analysis Lens      — new. How results are interpreted. Holds no score.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── The governed container ────────────────────────────────────────────
        Schema::create('methodology_versions', function (Blueprint $table): void {
            $table->uuid('methodology_version_id')->primary();
            $table->unsignedInteger('version_number');
            $table->string('status', 20)->default('DRAFT');
            $table->text('methodology_notes')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->uuid('parent_version_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestamps();

            $table->unique('version_number');
            $table->index('status');
        });

        // ── Part 1: what the assessment is for ───────────────────────────────
        // Purposes only. The subject lives in health domains, so "Malaria" is not an
        // objective; "Baseline" applied to Malaria is.
        Schema::create('assessment_objectives', function (Blueprint $table): void {
            $table->uuid('assessment_objective_id')->primary();
            $table->uuid('methodology_version_id');
            $table->string('objective_code', 60);
            $table->string('objective_name', 150);
            $table->string('objective_group', 60);
            $table->text('description');
            $table->text('question_it_answers')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['methodology_version_id', 'objective_code']);
            $table->index('objective_group');
            $table->foreign('methodology_version_id')->references('methodology_version_id')
                ->on('methodology_versions')->cascadeOnDelete();
        });

        // ── Part 2: subdivisions of a health domain ──────────────────────────
        Schema::create('health_areas', function (Blueprint $table): void {
            $table->uuid('health_area_id')->primary();
            $table->uuid('methodology_version_id');
            $table->integer('health_domain_id');
            $table->string('area_code', 60);
            $table->string('area_name', 150);
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['methodology_version_id', 'area_code']);
            $table->index('health_domain_id');
            $table->foreign('methodology_version_id')->references('methodology_version_id')
                ->on('methodology_versions')->cascadeOnDelete();
            $table->foreign('health_domain_id')->references('health_domain_id')
                ->on('health_domains')->cascadeOnDelete();
        });

        // ── Part 5: how results are interpreted ──────────────────────────────
        // A lens holds no score. It selects and frames what is already scored, so one
        // assessment legitimately yields different insight under different lenses.
        Schema::create('analysis_lenses', function (Blueprint $table): void {
            $table->uuid('analysis_lens_id')->primary();
            $table->uuid('methodology_version_id');
            $table->string('lens_code', 60);
            $table->string('lens_name', 150);
            $table->text('question_it_answers');
            $table->text('description');
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['methodology_version_id', 'lens_code']);
            $table->foreign('methodology_version_id')->references('methodology_version_id')
                ->on('methodology_versions')->cascadeOnDelete();
        });

        // ── Part 6: the shapes a finding can take ────────────────────────────
        Schema::create('insight_categories', function (Blueprint $table): void {
            $table->uuid('insight_category_id')->primary();
            $table->uuid('methodology_version_id');
            $table->string('category_code', 60);
            $table->string('category_name', 150);
            // Whether this is good, bad or neutral news. Lets a report lead with what
            // matters instead of an arbitrary order.
            $table->string('polarity', 20);
            $table->text('description');
            $table->boolean('is_diagnostic')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['methodology_version_id', 'category_code']);
            $table->index('polarity');
            $table->foreign('methodology_version_id')->references('methodology_version_id')
                ->on('methodology_versions')->cascadeOnDelete();
        });

        // ── Part 3: the official starting points ─────────────────────────────
        Schema::create('assessment_templates', function (Blueprint $table): void {
            $table->uuid('assessment_template_id')->primary();
            $table->uuid('methodology_version_id');
            $table->string('template_code', 60);
            $table->string('template_name', 150);
            $table->text('description');
            // ENTERPRISE spans departments; FOCUSED covers one subject. Both use the
            // same builder, scoring and reporting. See Part 8.
            $table->string('scope_type', 20);
            $table->string('target_type_code', 40)->nullable();
            $table->unsignedInteger('typical_duration_minutes')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['methodology_version_id', 'template_code']);
            $table->index('scope_type');
            $table->foreign('methodology_version_id')->references('methodology_version_id')
                ->on('methodology_versions')->cascadeOnDelete();
        });

        // ── Part 4: what an objective suggests ───────────────────────────────
        // Recommendations only. Nothing here constrains what an author may build; the
        // builder and publication validation remain the only authorities.
        Schema::create('objective_recommendations', function (Blueprint $table): void {
            $table->uuid('objective_recommendation_id')->primary();
            $table->uuid('assessment_objective_id');
            // HEALTH_DOMAIN, HEALTH_AREA, TEMPLATE, ANALYSIS_LENS, MEASUREMENT_DOMAIN, EVIDENCE_TYPE
            $table->string('recommends_type', 30);
            $table->string('recommends_ref', 60);
            $table->unsignedInteger('display_order')->default(0);
            $table->text('rationale')->nullable();
            $table->timestamps();

            $table->unique(
                ['assessment_objective_id', 'recommends_type', 'recommends_ref'],
                'objective_recommendation_unique'
            );
            $table->index(['recommends_type', 'recommends_ref']);
            $table->foreign('assessment_objective_id')->references('assessment_objective_id')
                ->on('assessment_objectives')->cascadeOnDelete();
        });

        // ── Objective presets: a saved starting combination ──────────────────
        // Not a new concept in the knowledge model. A preset preselects an objective,
        // its health domains and a template, so a user can start from "Malaria Baseline
        // Assessment" without Malaria existing twice in the model.
        Schema::create('objective_presets', function (Blueprint $table): void {
            $table->uuid('objective_preset_id')->primary();
            $table->uuid('methodology_version_id');
            $table->uuid('assessment_objective_id');
            $table->string('preset_code', 60);
            $table->string('preset_name', 150);
            $table->text('description')->nullable();
            $table->json('health_domain_codes')->nullable();
            $table->string('template_code', 60)->nullable();
            $table->json('analysis_lens_codes')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['methodology_version_id', 'preset_code']);
            $table->foreign('methodology_version_id')->references('methodology_version_id')
                ->on('methodology_versions')->cascadeOnDelete();
            $table->foreign('assessment_objective_id')->references('assessment_objective_id')
                ->on('assessment_objectives')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('objective_presets');
        Schema::dropIfExists('objective_recommendations');
        Schema::dropIfExists('assessment_templates');
        Schema::dropIfExists('insight_categories');
        Schema::dropIfExists('analysis_lenses');
        Schema::dropIfExists('health_areas');
        Schema::dropIfExists('assessment_objectives');
        Schema::dropIfExists('methodology_versions');
    }
};
