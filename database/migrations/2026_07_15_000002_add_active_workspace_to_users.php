<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('active_workspace_id')->nullable()->after('platform_role');
            $table->foreign('active_workspace_id')->references('workspace_id')->on('workspaces')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['active_workspace_id']);
            $table->dropColumn('active_workspace_id');
        });
    }
};
