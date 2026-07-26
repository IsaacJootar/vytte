<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('local_custom_sections', function (Blueprint $table) {
            // The workspace's own answers to its own questions, and the private 0-100 score
            // they produce — kept entirely out of the official Vytte score.
            $table->json('answers')->nullable()->after('questions');
            $table->decimal('custom_score', 5, 2)->nullable()->after('answers');
            $table->timestamp('scored_at')->nullable()->after('custom_score');
        });
    }

    public function down(): void
    {
        Schema::table('local_custom_sections', function (Blueprint $table) {
            $table->dropColumn(['answers', 'custom_score', 'scored_at']);
        });
    }
};
