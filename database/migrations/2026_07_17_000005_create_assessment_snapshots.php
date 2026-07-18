<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('creation_path', 20)->nullable()->index();
            $table->string('composition_hash', 64)->nullable();
        });

        Schema::create('assessment_snapshots', function (Blueprint $table) {
            $table->uuid('snapshot_id')->primary();
            $table->uuid('assessment_id')->unique();
            $table->string('creation_path', 20);
            $table->string('setting_type_code', 30)->nullable();
            $table->unsignedInteger('health_domain_id')->nullable();
            $table->string('content_hash', 64);
            $table->boolean('is_customized')->default(false);
            $table->json('payload');
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('assessment_id')->references('assessment_id')->on('assessments')->cascadeOnDelete();
            $table->foreign('setting_type_code')->references('setting_type_code')->on('setting_types');
            $table->foreign('health_domain_id')->references('health_domain_id')->on('health_domains');
            $table->foreign('created_by')->references('user_id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_snapshots');

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['creation_path', 'composition_hash']);
        });
    }
};
