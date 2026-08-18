<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A performance target is a goal against one comparable series, not against a project forever.
 *
 * Without this, a target set while one methodology was active kept silently applying after the
 * project moved to an incompatible series — the exact "targets too broadly attached" gap in
 * docs/architecture/ASSESSMENT_PORTFOLIO_NEXT_UPDATE.md. Existing targets are left with a null
 * signature; TrendService treats a null-signature target as bound to whatever series is active
 * the next time it is read or re-saved, so nothing already set is silently deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_targets', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'domain_code']);
            $table->string('comparison_signature', 64)->nullable()->after('domain_code');
            $table->index('comparison_signature');
            $table->unique(['project_id', 'domain_code', 'comparison_signature']);
        });
    }

    public function down(): void
    {
        Schema::table('performance_targets', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'domain_code', 'comparison_signature']);
            $table->dropIndex(['comparison_signature']);
            $table->dropColumn('comparison_signature');
            $table->unique(['project_id', 'domain_code']);
        });
    }
};
