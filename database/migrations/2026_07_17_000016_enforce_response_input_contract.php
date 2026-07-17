<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('numeric_unit', 40)->nullable();
            $table->decimal('numeric_min', 15, 4)->nullable();
            $table->decimal('numeric_max', 15, 4)->nullable();
            $table->decimal('numeric_step', 15, 4)->nullable();
        });

        DB::table('questions')->where('question_code', 'IPD.D1.Q2')->update([
            'question_text' => 'Average bed occupancy rate',
            'numeric_unit' => '%',
            'numeric_min' => 0,
            'numeric_max' => 100,
            'numeric_step' => 0.1,
        ]);
        DB::table('questions')->where('question_code', 'IPD.D1.Q3')->update([
            'numeric_unit' => 'days',
            'numeric_min' => 0,
            'numeric_step' => 0.1,
        ]);

        $numericTypeId = DB::table('question_types')->where('type_code', 'NUMERIC')->value('type_id');
        if ($numericTypeId) {
            $unbandedScoredNumericIds = DB::table('questions as q')
                ->leftJoin('question_numeric_bands as qnb', 'qnb.question_id', '=', 'q.question_id')
                ->where('q.type_id', $numericTypeId)
                ->where('q.is_scored', true)
                ->groupBy('q.question_id')
                ->havingRaw('COUNT(qnb.band_id) = 0')
                ->pluck('q.question_id');

            DB::table('sub_index_questions')->whereIn('question_id', $unbandedScoredNumericIds)->delete();
            DB::table('questions')->whereIn('question_id', $unbandedScoredNumericIds)->update(['is_scored' => false]);
        }

        $supportedTypes = ['SINGLE_SELECT', 'LIKERT', 'OPEN_ENDED', 'NUMERIC'];
        $malformedIds = DB::table('questions as q')
            ->join('question_types as qt', 'qt.type_id', '=', 'q.type_id')
            ->leftJoin('question_options as qo', 'qo.question_id', '=', 'q.question_id')
            ->where('q.source', 'PHSAI_V1')
            ->groupBy('q.question_id', 'qt.type_code', 'q.is_scored')
            ->havingRaw(
                '(qt.type_code NOT IN (?, ?, ?, ?)) OR
                 (qt.type_code = ? AND q.is_scored = 1) OR
                 (qt.type_code IN (?, ?) AND COUNT(qo.option_id) = 0)',
                [...$supportedTypes, 'OPEN_ENDED', 'SINGLE_SELECT', 'LIKERT']
            )
            ->pluck('q.question_id');

        if ($malformedIds->isNotEmpty()) {
            $responseIds = DB::table('responses')->whereIn('question_id', $malformedIds)->pluck('response_id');
            DB::table('response_options')->whereIn('response_id', $responseIds)->delete();
            DB::table('responses')->whereIn('question_id', $malformedIds)->delete();
            DB::table('observation_records')->whereIn('question_id', $malformedIds)->delete();
            DB::table('question_drafts')->whereIn('question_id', $malformedIds)->update(['question_id' => null]);
            DB::table('sub_index_questions')->whereIn('question_id', $malformedIds)->delete();
            DB::table('questions')->whereIn('question_id', $malformedIds)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['numeric_unit', 'numeric_min', 'numeric_max', 'numeric_step']);
        });
    }
};
