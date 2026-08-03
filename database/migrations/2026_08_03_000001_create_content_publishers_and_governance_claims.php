<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_publishers', function (Blueprint $table) {
            $table->uuid('content_publisher_id')->primary();
            $table->uuid('workspace_id')->nullable();
            $table->string('publisher_code', 80)->unique();
            $table->string('name', 180);
            $table->string('publisher_type', 40);
            $table->string('visibility', 25)->default('PUBLIC');
            $table->string('verification_status', 25)->default('UNVERIFIED');
            $table->text('attribution')->nullable();
            $table->text('website_url')->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('workspace_id')->on('workspaces')->nullOnDelete();
            $table->foreign('verified_by')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['publisher_type', 'verification_status']);
            $table->index(['visibility', 'verification_status']);
        });

        Schema::create('content_governance_claims', function (Blueprint $table) {
            $table->uuid('content_governance_claim_id')->primary();
            $table->uuid('content_publisher_id');
            $table->string('content_type', 60);
            $table->uuid('content_id');
            $table->string('claim_type', 40);
            $table->string('status', 25)->default('NOT_REVIEWED');
            $table->text('evidence_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('content_publisher_id')->references('content_publisher_id')->on('content_publishers')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('user_id')->on('users')->nullOnDelete();
            $table->unique(['content_type', 'content_id', 'claim_type'], 'content_governance_claim_unique');
            $table->index(['content_publisher_id', 'status']);
        });

        foreach (['questions', 'department_framework_versions', 'assessment_catalogue_releases'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->uuid('content_publisher_id')->nullable();
                $table->string('distribution_level', 25)->default('PUBLIC');
                $table->foreign('content_publisher_id')->references('content_publisher_id')->on('content_publishers')->nullOnDelete();
                $table->index(['content_publisher_id', 'distribution_level']);
            });
        }

        $publisherId = (string) Str::uuid();
        DB::table('content_publishers')->insert([
            'content_publisher_id' => $publisherId,
            'publisher_code' => 'VYTTE',
            'name' => 'Vytte',
            'publisher_type' => 'VYTTE',
            'visibility' => 'PUBLIC',
            'verification_status' => 'VERIFIED',
            'attribution' => 'Published through the Vytte governed assessment platform.',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('questions')->whereNull('content_publisher_id')->update(['content_publisher_id' => $publisherId]);
        DB::table('department_framework_versions')->whereNull('content_publisher_id')->update(['content_publisher_id' => $publisherId]);
        DB::table('assessment_catalogue_releases')->whereNull('content_publisher_id')->update(['content_publisher_id' => $publisherId]);
    }

    public function down(): void
    {
        foreach (['assessment_catalogue_releases', 'department_framework_versions', 'questions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['content_publisher_id']);
                $table->dropIndex(['content_publisher_id', 'distribution_level']);
                $table->dropColumn(['content_publisher_id', 'distribution_level']);
            });
        }

        Schema::dropIfExists('content_governance_claims');
        Schema::dropIfExists('content_publishers');
    }
};
