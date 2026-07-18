<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->string('plan_code', 40)->primary();
            $table->string('plan_name', 120);
            $table->string('public_label', 120);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('display_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_beta_unlocked')->default(true);
            $table->json('pricing_metadata')->nullable();
            $table->json('limits')->nullable();
            $table->timestamps();
        });

        DB::table('workspaces')
            ->where('plan', 'FREE')
            ->update(['plan' => 'STARTER']);

        DB::table('workspaces')
            ->where('plan', 'PRO')
            ->update(['plan' => 'PROFESSIONAL']);

        DB::table('workspaces')
            ->where('plan', 'AGENCY')
            ->update(['plan' => 'ORGANIZATION']);

        DB::table('plan_features')
            ->where('plan', 'FREE')
            ->update(['plan' => 'STARTER']);

        DB::table('plan_features')
            ->where('plan', 'PRO')
            ->update(['plan' => 'PROFESSIONAL']);

        DB::table('plan_features')
            ->where('plan', 'AGENCY')
            ->update(['plan' => 'ORGANIZATION']);
    }

    public function down(): void
    {
        DB::table('workspaces')
            ->where('plan', 'STARTER')
            ->update(['plan' => 'FREE']);

        DB::table('workspaces')
            ->where('plan', 'PROFESSIONAL')
            ->update(['plan' => 'PRO']);

        DB::table('workspaces')
            ->where('plan', 'ORGANIZATION')
            ->update(['plan' => 'AGENCY']);

        DB::table('plan_features')
            ->where('plan', 'STARTER')
            ->update(['plan' => 'FREE']);

        DB::table('plan_features')
            ->where('plan', 'PROFESSIONAL')
            ->update(['plan' => 'PRO']);

        DB::table('plan_features')
            ->where('plan', 'ORGANIZATION')
            ->update(['plan' => 'AGENCY']);

        Schema::dropIfExists('subscription_plans');
    }
};
