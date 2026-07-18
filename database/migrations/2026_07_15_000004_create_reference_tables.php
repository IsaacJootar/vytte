<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('target_types', function (Blueprint $table) {
            $table->string('target_type_code', 20)->primary();
            $table->string('target_type_name', 100);
            $table->text('description')->nullable();
        });

        Schema::create('target_categories', function (Blueprint $table) {
            $table->smallIncrements('category_id');
            $table->string('target_type_code', 20);
            $table->string('category_code', 20);
            $table->string('category_name', 100);
            $table->text('description')->nullable();
            $table->unique(['target_type_code', 'category_code']);
            $table->foreign('target_type_code')->references('target_type_code')->on('target_types');
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->smallIncrements('domain_id');
            $table->string('domain_code', 4)->unique();
            $table->string('domain_name', 100);
            $table->boolean('is_operational')->default(false);
            $table->smallInteger('display_order');
        });

        Schema::create('maturity_levels', function (Blueprint $table) {
            $table->smallIncrements('level_id');
            $table->smallInteger('level_number')->unique();
            $table->string('level_name', 100);
            $table->text('description')->nullable();
            $table->decimal('min_score', 5, 2);
            $table->decimal('max_score', 5, 2);
        });

        Schema::create('assessment_tiers', function (Blueprint $table) {
            $table->smallIncrements('assessment_tier_id');
            $table->string('tier_code', 10)->unique();
            $table->string('tier_name', 100);
        });

        Schema::create('question_types', function (Blueprint $table) {
            $table->smallIncrements('type_id');
            $table->string('type_code', 30)->unique();
        });

        Schema::create('standards_registry', function (Blueprint $table) {
            $table->increments('standard_id');
            $table->string('standard_code', 30)->unique();
            $table->string('standard_name', 200);
            $table->string('issuing_body', 150);
            $table->text('description')->nullable();
            $table->string('reference_url', 500)->nullable();
        });

        Schema::create('topics', function (Blueprint $table) {
            $table->increments('topic_id');
            $table->string('topic_code', 20)->unique();
            $table->string('topic_name', 150);
            $table->text('description')->nullable();
            $table->string('score_acronym', 10)->unique();
            $table->string('score_full_name', 200);
            $table->string('source', 20)->default('CURATED');
            $table->string('review_status', 20)->default('PUBLISHED');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topics');
        Schema::dropIfExists('standards_registry');
        Schema::dropIfExists('question_types');
        Schema::dropIfExists('assessment_tiers');
        Schema::dropIfExists('maturity_levels');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('target_categories');
        Schema::dropIfExists('target_types');
    }
};
