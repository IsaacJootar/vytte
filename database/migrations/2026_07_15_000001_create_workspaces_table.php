<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->uuid('workspace_id')->primary();
            $table->string('name', 255);
            $table->string('workspace_type', 30)->default('INDIVIDUAL');
            $table->string('slug', 100)->unique()->nullable();
            $table->string('plan', 50)->default('FREE');
            $table->string('status', 50)->default('ACTIVE');
            $table->json('settings')->default('{}');
            $table->timestamps();
        });

        Schema::create('workspace_members', function (Blueprint $table) {
            $table->uuid('workspace_id');
            $table->uuid('user_id');
            $table->string('role', 20)->default('MEMBER');
            $table->timestamp('joined_at')->useCurrent();
            $table->primary(['workspace_id', 'user_id']);
            $table->foreign('workspace_id')->references('workspace_id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
        });

        Schema::create('workspace_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('email', 255);
            $table->string('role', 20)->default('MEMBER');
            $table->string('token', 64)->unique();
            $table->uuid('invited_by');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->foreign('workspace_id')->references('workspace_id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('invited_by')->references('user_id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_invitations');
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};
