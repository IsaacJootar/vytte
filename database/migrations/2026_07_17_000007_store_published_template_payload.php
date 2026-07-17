<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_template_versions', function (Blueprint $table) {
            $table->json('published_payload')->nullable()->after('content_hash');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_template_versions', function (Blueprint $table) {
            $table->dropColumn('published_payload');
        });
    }
};
