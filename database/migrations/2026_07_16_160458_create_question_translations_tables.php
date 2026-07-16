<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_translations', function (Blueprint $table) {
            $table->increments('translation_id');
            $table->uuid('question_id');
            $table->string('locale', 10);
            $table->text('question_text');
            $table->unique(['question_id', 'locale']);
            $table->foreign('question_id')->references('question_id')->on('questions')->cascadeOnDelete();
        });

        Schema::create('question_option_translations', function (Blueprint $table) {
            $table->increments('translation_id');
            $table->unsignedInteger('option_id');
            $table->string('locale', 10);
            $table->string('option_label', 255);
            $table->unique(['option_id', 'locale']);
            $table->foreign('option_id')->references('option_id')->on('question_options')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_option_translations');
        Schema::dropIfExists('question_translations');
    }
};
