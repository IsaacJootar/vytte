<?php

use App\Services\ScoringModelService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_models', function (Blueprint $table) {
            $table->uuid('scoring_model_id')->primary();
            $table->uuid('content_publisher_id')->nullable();
            $table->string('model_code', 100)->unique();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('content_publisher_id')->references('content_publisher_id')->on('content_publishers')->nullOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->nullOnDelete();
        });

        Schema::create('scoring_model_versions', function (Blueprint $table) {
            $table->uuid('scoring_model_version_id')->primary();
            $table->uuid('scoring_model_id');
            $table->uuid('framework_version_id')->nullable();
            $table->unsignedInteger('version_number');
            $table->string('status', 25)->default('DRAFT');
            $table->string('score_purpose', 30)->default('PRIMARY');
            $table->string('construct', 60)->default('READINESS');
            $table->string('direction', 30)->default('HIGHER_IS_BETTER');
            $table->string('algorithm_version', 80);
            $table->json('missing_policy');
            $table->json('aggregation_config');
            $table->string('content_hash', 64)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestamps();
            $table->foreign('scoring_model_id')->references('scoring_model_id')->on('scoring_models')->cascadeOnDelete();
            $table->foreign('framework_version_id')->references('framework_version_id')->on('department_framework_versions')->cascadeOnDelete();
            $table->foreign('published_by')->references('user_id')->on('users')->nullOnDelete();
            $table->unique(['scoring_model_id', 'version_number']);
            $table->unique('framework_version_id');
            $table->index(['status', 'score_purpose']);
        });

        Schema::create('scoring_item_rules', function (Blueprint $table) {
            $table->uuid('scoring_item_rule_id')->primary();
            $table->uuid('scoring_model_version_id');
            $table->uuid('framework_question_placement_id');
            $table->uuid('question_version_id');
            $table->unsignedInteger('sub_index_id')->nullable();
            $table->string('method', 35)->default('UNSCORED');
            $table->string('score_role', 30)->default('PRIMARY');
            $table->decimal('weight', 8, 3)->default(1.000);
            $table->string('criticality', 30)->default('STANDARD');
            $table->json('rule_config');
            $table->timestamps();
            $table->foreign('scoring_model_version_id')->references('scoring_model_version_id')->on('scoring_model_versions')->cascadeOnDelete();
            $table->foreign('framework_question_placement_id')->references('framework_question_placement_id')->on('framework_question_placements')->cascadeOnDelete();
            $table->foreign('question_version_id')->references('question_version_id')->on('question_versions');
            $table->foreign('sub_index_id')->references('sub_index_id')->on('sub_indices')->nullOnDelete();
            $table->unique(['scoring_model_version_id', 'framework_question_placement_id'], 'scoring_item_rule_placement_unique');
            $table->index(['scoring_model_version_id', 'score_role']);
        });

        Schema::table('department_framework_versions', function (Blueprint $table) {
            $table->uuid('scoring_model_version_id')->nullable();
            $table->foreign('scoring_model_version_id')->references('scoring_model_version_id')->on('scoring_model_versions')->nullOnDelete();
        });

        Schema::table('assessment_snapshots', function (Blueprint $table) {
            $table->uuid('scoring_model_version_id')->nullable();
            $table->string('comparison_signature', 64)->nullable();
            $table->foreign('scoring_model_version_id')->references('scoring_model_version_id')->on('scoring_model_versions')->nullOnDelete();
            $table->index('comparison_signature');
        });

        Schema::table('assessment_report_snapshots', function (Blueprint $table) {
            $table->uuid('scoring_model_version_id')->nullable();
            $table->string('comparison_signature', 64)->nullable();
            $table->foreign('scoring_model_version_id')->references('scoring_model_version_id')->on('scoring_model_versions')->nullOnDelete();
            $table->index('comparison_signature');
        });

        app(ScoringModelService::class)->backfillLegacyModels();
    }

    public function down(): void
    {
        Schema::table('assessment_report_snapshots', function (Blueprint $table) {
            $table->dropForeign(['scoring_model_version_id']);
            $table->dropIndex(['comparison_signature']);
            $table->dropColumn(['scoring_model_version_id', 'comparison_signature']);
        });
        Schema::table('assessment_snapshots', function (Blueprint $table) {
            $table->dropForeign(['scoring_model_version_id']);
            $table->dropIndex(['comparison_signature']);
            $table->dropColumn(['scoring_model_version_id', 'comparison_signature']);
        });
        Schema::table('department_framework_versions', function (Blueprint $table) {
            $table->dropForeign(['scoring_model_version_id']);
            $table->dropColumn('scoring_model_version_id');
        });
        Schema::dropIfExists('scoring_item_rules');
        Schema::dropIfExists('scoring_model_versions');
        Schema::dropIfExists('scoring_models');
    }
};
