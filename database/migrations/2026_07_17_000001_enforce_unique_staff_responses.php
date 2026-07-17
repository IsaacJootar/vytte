<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEX_NAME = 'responses_staff_assessment_question_unique';

    public function up(): void
    {
        $duplicateExists = DB::table('responses')
            ->select('assessment_id', 'question_id')
            ->whereNull('respondent_id')
            ->groupBy('assessment_id', 'question_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateExists) {
            throw new RuntimeException(
                'Cannot enforce staff response uniqueness: duplicate assessment/question rows exist. Resolve them explicitly before migrating.'
            );
        }

        DB::statement(sprintf(
            'CREATE UNIQUE INDEX %s ON responses (assessment_id, question_id) WHERE respondent_id IS NULL',
            self::INDEX_NAME
        ));
    }

    public function down(): void
    {
        DB::statement(sprintf('DROP INDEX IF EXISTS %s', self::INDEX_NAME));
    }
};
