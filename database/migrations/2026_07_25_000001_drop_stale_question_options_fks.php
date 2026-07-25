<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the stale foreign keys from responses to `question_options`.
     *
     * These date from the pre-governance schema. Governed and official content now stores its
     * options inline in the immutable assessment snapshot with snapshot-local ids (1, 2, 3 …),
     * and `question_options` is not populated for it. The keys therefore made it impossible to
     * record any answer to a governed assessment — a chosen option's id was never present in
     * `question_options`. Answer integrity is instead enforced where it belongs: the runner
     * validates every selection against the frozen snapshot before saving.
     */
    public function up(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropForeign(['value_option_id']);
        });

        Schema::table('response_options', function (Blueprint $table) {
            $table->dropForeign(['option_id']);
        });
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->foreign('value_option_id')->references('option_id')->on('question_options');
        });

        Schema::table('response_options', function (Blueprint $table) {
            $table->foreign('option_id')->references('option_id')->on('question_options');
        });
    }
};
