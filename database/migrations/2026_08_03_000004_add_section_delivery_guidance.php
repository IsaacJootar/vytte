<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('framework_sections', function (Blueprint $table) {
            $table->text('instructions')->nullable();
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->string('respondent_role', 120)->nullable();
            $table->json('visibility_rules')->nullable();
            $table->boolean('is_repeatable')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('framework_sections', function (Blueprint $table) {
            $table->dropColumn([
                'instructions',
                'estimated_minutes',
                'respondent_role',
                'visibility_rules',
                'is_repeatable',
            ]);
        });
    }
};
