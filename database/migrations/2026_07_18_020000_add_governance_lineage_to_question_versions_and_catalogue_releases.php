<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_versions', function (Blueprint $table) {
            if (! Schema::hasColumn('question_versions', 'parent_version_id')) {
                $table->uuid('parent_version_id')->nullable()->after('question_id');
                $table->foreign('parent_version_id')
                    ->references('question_version_id')
                    ->on('question_versions')
                    ->nullOnDelete();
                $table->index('parent_version_id');
            }
        });

        Schema::table('assessment_catalogue_releases', function (Blueprint $table) {
            if (! Schema::hasColumn('assessment_catalogue_releases', 'parent_release_id')) {
                $table->uuid('parent_release_id')->nullable()->after('release_code');
                $table->foreign('parent_release_id')
                    ->references('catalogue_release_id')
                    ->on('assessment_catalogue_releases')
                    ->nullOnDelete();
                $table->index('parent_release_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assessment_catalogue_releases', function (Blueprint $table) {
            if (Schema::hasColumn('assessment_catalogue_releases', 'parent_release_id')) {
                $table->dropForeign(['parent_release_id']);
                $table->dropIndex(['parent_release_id']);
                $table->dropColumn('parent_release_id');
            }
        });

        Schema::table('question_versions', function (Blueprint $table) {
            if (Schema::hasColumn('question_versions', 'parent_version_id')) {
                $table->dropForeign(['parent_version_id']);
                $table->dropIndex(['parent_version_id']);
                $table->dropColumn('parent_version_id');
            }
        });
    }
};
