<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('project_id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('owner_user_id');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unsignedInteger('topic_id')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();
            $table->foreign('workspace_id')->references('workspace_id')->on('workspaces');
            $table->foreign('owner_user_id')->references('user_id')->on('users');
            $table->foreign('topic_id')->references('topic_id')->on('topics');
            $table->index('workspace_id');
            $table->index('owner_user_id');
        });

        Schema::create('project_targets', function (Blueprint $table) {
            $table->uuid('project_id');
            $table->uuid('target_id');
            $table->timestamp('added_at')->useCurrent();
            $table->primary(['project_id', 'target_id']);
            $table->foreign('project_id')->references('project_id')->on('projects')->cascadeOnDelete();
            $table->foreign('target_id')->references('target_id')->on('targets');
            $table->index('project_id');
            $table->index('target_id');
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->uuid('assessment_id')->primary();
            $table->uuid('target_id');
            $table->uuid('project_id')->nullable();
            $table->unsignedSmallInteger('assessment_tier_id');
            $table->string('scope_type', 20)->default('FULL_TARGET');
            $table->string('status', 20)->default('IN_PROGRESS');
            $table->string('publish_status', 20)->default('DRAFT');
            $table->timestamp('published_at')->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->string('assessor_name', 255)->nullable();
            $table->timestamps();
            $table->foreign('target_id')->references('target_id')->on('targets');
            $table->foreign('project_id')->references('project_id')->on('projects');
            $table->foreign('assessment_tier_id')->references('assessment_tier_id')->on('assessment_tiers');
            $table->foreign('published_by')->references('user_id')->on('users');
            $table->index('target_id');
            $table->index('project_id');
            $table->index('publish_status');
        });

        Schema::create('assessment_share_links', function (Blueprint $table) {
            $table->uuid('link_id')->primary();
            $table->uuid('assessment_id');
            $table->unsignedInteger('module_id')->nullable();
            $table->unsignedInteger('topic_id')->nullable();
            $table->string('token', 64)->unique();
            $table->uuid('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('module_id')->references('module_id')->on('assessment_modules');
            $table->foreign('topic_id')->references('topic_id')->on('topics');
            $table->foreign('created_by')->references('user_id')->on('users');
            $table->index('token');
            $table->index('assessment_id');
        });

        Schema::create('assessment_module_scope', function (Blueprint $table) {
            $table->uuid('assessment_id');
            $table->unsignedInteger('module_id');
            $table->boolean('in_scope');
            $table->boolean('is_category_default');
            $table->text('exclusion_reason')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->timestamp('completed_at')->nullable();
            $table->primary(['assessment_id', 'module_id']);
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('module_id')->references('module_id')->on('assessment_modules');
        });

        Schema::create('assessment_topic_scope', function (Blueprint $table) {
            $table->uuid('assessment_id');
            $table->unsignedInteger('topic_id');
            $table->string('status', 20)->default('PENDING');
            $table->timestamp('completed_at')->nullable();
            $table->primary(['assessment_id', 'topic_id']);
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('topic_id')->references('topic_id')->on('topics');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_topic_scope');
        Schema::dropIfExists('assessment_module_scope');
        Schema::dropIfExists('assessment_share_links');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('project_targets');
        Schema::dropIfExists('projects');
    }
};
