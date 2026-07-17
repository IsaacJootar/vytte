<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_report_snapshots', function (Blueprint $table) {
            $table->uuid('report_snapshot_id')->primary();
            $table->uuid('assessment_id')->unique();
            $table->string('schema_version', 40);
            $table->string('content_hash', 64);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_report_snapshots');
    }
};
