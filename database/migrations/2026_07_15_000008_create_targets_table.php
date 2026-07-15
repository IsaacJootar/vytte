<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('targets', function (Blueprint $table) {
            $table->uuid('target_id')->primary();
            $table->uuid('owner_workspace_id');
            $table->string('target_type_code', 20);
            $table->string('name', 255);
            $table->unsignedSmallInteger('category_id');
            $table->string('state', 100)->nullable();
            $table->string('lga', 100)->nullable();
            $table->string('ownership', 50)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->timestamps();
            $table->foreign('owner_workspace_id')->references('workspace_id')->on('workspaces');
            $table->foreign('target_type_code')->references('target_type_code')->on('target_types');
            $table->foreign('category_id')->references('category_id')->on('target_categories');
            $table->index('owner_workspace_id');
            $table->index('target_type_code');
            $table->index('category_id');
        });

        Schema::create('respondents', function (Blueprint $table) {
            $table->uuid('respondent_id')->primary();
            $table->uuid('target_id');
            $table->unsignedInteger('role_id');
            $table->string('full_name', 255)->nullable();
            $table->string('designation', 150)->nullable();
            $table->unsignedInteger('module_id')->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('target_id')->references('target_id')->on('targets');
            $table->foreign('role_id')->references('role_id')->on('respondent_roles');
            $table->foreign('module_id')->references('module_id')->on('assessment_modules');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respondents');
        Schema::dropIfExists('targets');
    }
};
