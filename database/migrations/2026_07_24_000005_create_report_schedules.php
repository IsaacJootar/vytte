<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scheduled report delivery (P7): email the latest report for a project to a recipient on
     * a recurring cadence. A schedule is workspace-scoped and belongs to one project.
     */
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->uuid('report_schedule_id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('project_id');
            $table->string('recipient_email');
            $table->string('frequency', 20); // WEEKLY / MONTHLY / QUARTERLY
            $table->timestamp('next_run_at');
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('workspace_id')->references('workspace_id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('project_id')->references('project_id')->on('projects')->cascadeOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->cascadeOnDelete();

            $table->index(['is_active', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
