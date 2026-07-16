<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_respondent_tokens', function (Blueprint $table) {
            $table->char('token', 32)->primary();
            $table->uuid('assessment_id');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('assessment_id')
                ->references('assessment_id')
                ->on('assessments')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_respondent_tokens');
    }
};
