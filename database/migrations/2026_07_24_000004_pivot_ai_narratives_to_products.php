<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The AI layer moves from "one narrative per lens" to a small set of purpose-built
     * products (executive briefing, diagnostic summary, root-cause analysis, donor / clinical
     * / operational summaries). The stored narrative is now keyed by product, not lens.
     */
    public function up(): void
    {
        Schema::table('assessment_ai_narratives', function (Blueprint $table) {
            $table->string('product', 40)->nullable()->after('assessment_id');
        });

        // Carry any existing rows over (test data only; production has none without a key).
        DB::table('assessment_ai_narratives')->whereNull('product')->update(['product' => DB::raw('lens')]);

        Schema::table('assessment_ai_narratives', function (Blueprint $table) {
            // Dropping the lens column drops its (assessment_id, lens) unique index with it.
            $table->dropColumn('lens');
            $table->unique(['assessment_id', 'product']);
        });
    }

    public function down(): void
    {
        Schema::table('assessment_ai_narratives', function (Blueprint $table) {
            $table->string('lens', 40)->nullable()->after('assessment_id');
        });
        DB::table('assessment_ai_narratives')->whereNull('lens')->update(['lens' => DB::raw('product')]);
        Schema::table('assessment_ai_narratives', function (Blueprint $table) {
            $table->dropUnique(['assessment_id', 'product']);
            $table->dropColumn('product');
            $table->unique(['assessment_id', 'lens']);
        });
    }
};
