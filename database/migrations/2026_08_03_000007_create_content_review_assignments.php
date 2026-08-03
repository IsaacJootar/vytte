<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_review_assignments', function (Blueprint $table) {
            $table->uuid('content_review_assignment_id')->primary();
            $table->uuid('framework_version_id');
            $table->string('claim_type', 40);
            $table->uuid('assigned_by');
            $table->uuid('reviewer_id');
            $table->string('status', 30)->default('ASSIGNED');
            $table->string('recommendation', 20)->nullable();
            $table->text('evidence_summary')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->uuid('decided_by')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('framework_version_id')->references('framework_version_id')->on('department_framework_versions')->cascadeOnDelete();
            $table->foreign('assigned_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('reviewer_id')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('decided_by')->references('user_id')->on('users')->nullOnDelete();
            $table->unique(['framework_version_id', 'claim_type'], 'content_review_assignment_unique');
            $table->index(['reviewer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_review_assignments');
    }
};
