<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suspending a person has to be recorded somewhere, and the reason has to travel with
 * it — an account locked out with no stated reason is unexplainable to the person
 * affected and to whoever reviews the decision later.
 *
 * Suspension is deliberately reversible and additive: no row is removed, so the account,
 * its memberships, and everything it authored stay intact and auditable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('suspended_at')->nullable()->after('platform_role');
            $table->string('suspension_reason', 255)->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });
    }
};
