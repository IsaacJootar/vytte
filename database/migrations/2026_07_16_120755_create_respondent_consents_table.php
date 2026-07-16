<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respondent_consents', function (Blueprint $table) {
            $table->uuid('consent_id')->primary();
            $table->uuid('assessment_id');
            $table->unsignedInteger('module_id');
            $table->text('consent_text');
            $table->uuid('consented_by');
            $table->timestamp('consented_at')->useCurrent();
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('module_id')->references('module_id')->on('assessment_modules');
            $table->foreign('consented_by')->references('user_id')->on('users');
            $table->index('assessment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respondent_consents');
    }
};
