<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Response;
use App\Models\SubIndex;
use Illuminate\Support\Facades\DB;

class ScoringService
{
    public function calculate(Assessment $assessment): void
    {
        $scope = DB::table('assessment_module_scope')
            ->where('assessment_id', $assessment->assessment_id)
            ->where('in_scope', true)
            ->first();

        if (! $scope) {
            return;
        }

        // All responses for this assessment that have a selected option
        $responses = Response::where('assessment_id', $assessment->assessment_id)
            ->whereNull('respondent_id')
            ->whereNotNull('value_option_id')
            ->with('selectedOption:option_id,score_weight')
            ->get()
            ->keyBy('question_id');

        // Sub-indices for the module, with their linked scored questions
        $subIndices = SubIndex::where('module_id', $scope->module_id)
            ->with(['questions' => function ($q) {
                $q->select('questions.question_id', 'questions.is_scored')
                    ->withPivot('weight');
            }])
            ->get();

        $subIndexResults = [];

        foreach ($subIndices as $subIndex) {
            $totalWeight = 0.0;
            $weightedSum = 0.0;
            $scoredTotal = 0;
            $answeredCount = 0;

            foreach ($subIndex->questions as $question) {
                if (! $question->is_scored) {
                    continue;
                }

                $scoredTotal++;
                $weight = (float) ($question->pivot->weight ?? 1.0);
                $response = $responses->get($question->question_id);

                if ($response && $response->selectedOption && $response->selectedOption->score_weight !== null) {
                    $weightedSum += (float) $response->selectedOption->score_weight * $weight;
                    $totalWeight += $weight;
                    $answeredCount++;
                }
            }

            if ($totalWeight > 0) {
                $score = round($weightedSum / $totalWeight, 2);
                $status = ($answeredCount === $scoredTotal) ? 'CALIBRATED' : 'PARTIAL';
            } else {
                $score = null;
                $status = 'NOT_CALIBRATED';
            }

            $subIndexResults[$subIndex->sub_index_id] = [
                'score' => $score,
                'status' => $status,
                'domain_id' => $subIndex->domain_id,
            ];

            DB::table('sub_index_scores')->upsert(
                [
                    'assessment_id' => $assessment->assessment_id,
                    'sub_index_id' => $subIndex->sub_index_id,
                    'respondent_type' => 'STAFF',
                    'score' => $score,
                    'calibration_status' => $status,
                    'calculated_at' => now(),
                ],
                ['assessment_id', 'sub_index_id', 'respondent_type'],
                ['score', 'calibration_status', 'calculated_at']
            );
        }

        // Domain scores: aggregate sub-index scores per global domain
        $domainGroups = [];
        foreach ($subIndexResults as $data) {
            $domainGroups[$data['domain_id']][] = $data;
        }

        foreach ($domainGroups as $domainId => $items) {
            $nonNull = array_filter($items, fn ($d) => $d['score'] !== null);

            if (empty($nonNull)) {
                $domainScore = null;
                $domainStatus = 'NOT_CALIBRATED';
            } else {
                $domainScore = round(
                    array_sum(array_column($nonNull, 'score')) / count($nonNull),
                    2
                );
                $domainStatus = count($nonNull) === count($items) ? 'CALIBRATED' : 'PARTIAL';
            }

            DB::table('domain_scores')->upsert(
                [
                    'assessment_id' => $assessment->assessment_id,
                    'domain_id' => $domainId,
                    'score' => $domainScore,
                    'calibration_status' => $domainStatus,
                    'calculated_at' => now(),
                ],
                ['assessment_id', 'domain_id'],
                ['score', 'calibration_status', 'calculated_at']
            );
        }

        // Overall score: average of all non-null sub-index scores
        $nonNullSubs = array_filter($subIndexResults, fn ($d) => $d['score'] !== null);

        if (empty($subIndexResults) || empty($nonNullSubs)) {
            $overallScore = null;
            $overallStatus = 'NOT_CALIBRATED';
        } else {
            $overallScore = round(
                array_sum(array_column($nonNullSubs, 'score')) / count($nonNullSubs),
                2
            );
            $overallStatus = count($nonNullSubs) === count($subIndexResults) ? 'CALIBRATED' : 'PARTIAL';
        }

        // Maturity level lookup
        $maturityLevelId = null;
        if ($overallScore !== null) {
            $maturityLevelId = DB::table('maturity_levels')
                ->where('min_score', '<=', $overallScore)
                ->where('max_score', '>', $overallScore)
                ->value('level_id');

            // score = 100 falls on the upper boundary of the highest level
            if ($maturityLevelId === null && $overallScore >= 100) {
                $maturityLevelId = DB::table('maturity_levels')
                    ->orderByDesc('level_number')
                    ->value('level_id');
            }
        }

        DB::table('assessment_scores')->upsert(
            [
                'assessment_id' => $assessment->assessment_id,
                'overall_score' => $overallScore,
                'calibration_status' => $overallStatus,
                'maturity_level_id' => $maturityLevelId,
                'calculated_at' => now(),
            ],
            ['assessment_id'],
            ['overall_score', 'calibration_status', 'maturity_level_id', 'calculated_at']
        );
    }

    /**
     * Returns the score band for display: 'strong', 'moderate', 'weak', or 'uncalibrated'.
     * Strong ≥ 70 | Moderate 45–69 | Weak < 45 | null → uncalibrated
     */
    public function bandFor(?float $score): string
    {
        if ($score === null) {
            return 'uncalibrated';
        }
        if ($score >= 70.0) {
            return 'strong';
        }
        if ($score >= 45.0) {
            return 'moderate';
        }

        return 'weak';
    }
}
