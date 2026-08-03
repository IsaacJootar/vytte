<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_contributions', function (Blueprint $table) {
            $table->uuid('content_contribution_id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('submitted_by');
            $table->uuid('proposed_publisher_id')->nullable();
            $table->unsignedInteger('module_id')->nullable();
            $table->string('title', 180);
            $table->text('question_text');
            $table->string('response_format', 40);
            $table->json('answer_options')->nullable();
            $table->json('numeric_config')->nullable();
            $table->text('intended_use');
            $table->string('source_authority', 180)->nullable();
            $table->text('source_url')->nullable();
            $table->string('license_code', 80)->nullable();
            $table->text('methodology_notes')->nullable();
            $table->string('status', 25)->default('SUBMITTED');
            $table->uuid('reviewed_by')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('promoted_question_id')->nullable();
            $table->uuid('promoted_question_version_id')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('workspace_id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('submitted_by')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('proposed_publisher_id')->references('content_publisher_id')->on('content_publishers')->nullOnDelete();
            $table->foreign('module_id')->references('module_id')->on('assessment_modules')->nullOnDelete();
            $table->foreign('reviewed_by')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('promoted_question_id')->references('question_id')->on('questions')->nullOnDelete();
            $table->foreign('promoted_question_version_id')->references('question_version_id')->on('question_versions')->nullOnDelete();
            $table->index(['workspace_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('content_assistance_runs', function (Blueprint $table) {
            $table->uuid('content_assistance_run_id')->primary();
            $table->uuid('framework_version_id');
            $table->string('run_type', 30);
            $table->string('status', 25);
            $table->string('source_hash', 64);
            $table->json('findings');
            $table->string('model', 120)->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('framework_version_id')->references('framework_version_id')->on('department_framework_versions')->cascadeOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['framework_version_id', 'run_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_assistance_runs');
        Schema::dropIfExists('content_contributions');
    }
};
