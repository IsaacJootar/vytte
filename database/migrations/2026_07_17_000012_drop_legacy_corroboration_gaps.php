<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('corroboration_gaps');
    }

    public function down(): void
    {
        Schema::create('corroboration_gaps', function (Blueprint $table) {
            $table->uuid('assessment_id');
            $table->unsignedInteger('sub_index_id');
            $table->string('voice_respondent_type', 20);
            $table->decimal('staff_score', 5, 2)->nullable();
            $table->decimal('voice_score', 5, 2)->nullable();
            $table->decimal('gap_magnitude', 5, 2)->nullable();
            $table->timestamp('flagged_at')->useCurrent();
            $table->primary(['assessment_id', 'sub_index_id', 'voice_respondent_type']);
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('sub_index_id')->references('sub_index_id')->on('sub_indices');
        });
    }
};
