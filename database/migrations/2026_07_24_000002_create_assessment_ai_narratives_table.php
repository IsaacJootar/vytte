<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores an AI-written narrative for an assessment, per lens.
     *
     * The narrative is a retelling of the frozen intelligence, not a new source of truth. It
     * is kept so it is not regenerated (and re-billed) on every view, and stamped with the
     * model and a hash of the intelligence it was written from — so a narrative that has gone
     * stale relative to its report can be spotted and refreshed.
     */
    public function up(): void
    {
        Schema::create('assessment_ai_narratives', function (Blueprint $table) {
            $table->uuid('narrative_id')->primary();
            $table->uuid('assessment_id');
            $table->string('lens', 40)->default('EXECUTIVE');
            $table->string('model', 60);
            $table->string('source_hash', 64);
            $table->text('body');
            $table->uuid('generated_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('generated_by')->references('user_id')->on('users')->nullOnDelete();

            // One current narrative per assessment per lens; regenerating replaces it.
            $table->unique(['assessment_id', 'lens']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_ai_narratives');
    }
};
