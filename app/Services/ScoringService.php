<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Response;
use App\Models\SubIndex;
use Illuminate\Support\Facades\DB;

class ScoringService
{
    public const ALGORITHM_VERSION = 'vytte-3.0-snapshot-profile';

    public function calculate(Assessment $assessment): void
    {
        $moduleIds = DB::table('assessment_module_scope')
            ->where('assessment_id', $assessment->assessment_id)
            ->where('in_scope', true)
            ->pluck('module_id')
            ->toArray();

        if (empty($moduleIds)) {
            return;
        }

        // The current compatibility profile calculates the assessor-authored response set.
        $responses = Response::where('assessment_id', $assessment->assessment_id)
            ->whereNull('respondent_id')
            ->whereNotNull('value_option_id')
            ->get()
            ->keyBy('question_id');

        $subIndices = $this->scoringProfile($assessment, $moduleIds);

        $subIndexResults = [];

        foreach ($subIndices as $subIndex) {
            $totalWeight = 0.0;
            $weightedSum = 0.0;
            $scoredTotal = 0;
            $answeredCount = 0;

            foreach ($subIndex['questions'] as $question) {
                if (! $question['is_scored']) {
                    continue;
                }

                $scoredTotal++;
                $weight = (float) $question['weight'];
                $response = $responses->get($question['question_id']);

                if ($response) {
                    $selectedOption = collect($question['options'])
                        ->firstWhere('option_id', (int) $response->value_option_id);
                    if ($selectedOption === null || $selectedOption['score_weight'] === null) {
                        continue;
                    }

                    $optionScore = (float) $selectedOption['score_weight'];
                    $questionScaleMaximum = collect($question['options'])
                        ->whereNotNull('score_weight')
                        ->max(fn ($option) => (float) $option['score_weight']);

                    if ($questionScaleMaximum !== null && $questionScaleMaximum <= 1.0) {
                        $optionScore *= 100;
                    }

                    $weightedSum += $optionScore * $weight;
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

            $subIndexResults[$subIndex['sub_index_id']] = [
                'score' => $score,
                'status' => $status,
                'domain_id' => $subIndex['domain_id'],
            ];

            DB::table('sub_index_scores')->upsert(
                [
                    'assessment_id' => $assessment->assessment_id,
                    'sub_index_id' => $subIndex['sub_index_id'],
                    'respondent_type' => 'STAFF',
                    'score' => $score,
                    'calibration_status' => $status,
                    'scoring_version' => self::ALGORITHM_VERSION,
                    'calculated_at' => now(),
                ],
                ['assessment_id', 'sub_index_id', 'respondent_type'],
                ['score', 'calibration_status', 'scoring_version', 'calculated_at']
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
                    'scoring_version' => self::ALGORITHM_VERSION,
                    'calculated_at' => now(),
                ],
                ['assessment_id', 'domain_id'],
                ['score', 'calibration_status', 'scoring_version', 'calculated_at']
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
                'scoring_version' => self::ALGORITHM_VERSION,
                'expected_module_count' => count($moduleIds),
                'active_module_count' => count($moduleIds),
                'maturity_level_id' => $maturityLevelId,
                'calculated_at' => now(),
            ],
            ['assessment_id'],
            ['overall_score', 'calibration_status', 'scoring_version', 'expected_module_count', 'active_module_count', 'maturity_level_id', 'calculated_at']
        );
    }

    private function scoringProfile(Assessment $assessment, array $moduleIds): array
    {
        $snapshot = $assessment->snapshot()->first();
        if ($snapshot && collect($snapshot->payload)->every(fn ($module) => array_key_exists('scoring_profile', $module))) {
            return collect($snapshot->payload)->flatMap(function ($module) {
                $questions = collect($module['questions'] ?? [])->keyBy('question_id');

                return collect($module['scoring_profile'] ?? [])->map(function ($subIndex) use ($questions) {
                    $profileQuestions = collect($subIndex['questions'] ?? [])->map(function ($link) use ($questions) {
                        $question = $questions->get($link['question_id']);

                        return [
                            'question_id' => $link['question_id'],
                            'is_scored' => (bool) ($question['is_scored'] ?? false),
                            'weight' => (float) ($link['weight'] ?? 1.0),
                            'options' => $question['options'] ?? [],
                        ];
                    })->all();

                    return [
                        'sub_index_id' => (int) $subIndex['sub_index_id'],
                        'domain_id' => (int) $subIndex['domain_id'],
                        'questions' => $profileQuestions,
                    ];
                });
            })->values()->all();
        }

        return SubIndex::whereIn('module_id', $moduleIds)
            ->with(['questions' => function ($query) {
                $query->select('questions.question_id', 'questions.is_scored')
                    ->withPivot('weight')
                    ->with('options:option_id,question_id,score_weight');
            }])
            ->get()
            ->map(fn ($subIndex) => [
                'sub_index_id' => $subIndex->sub_index_id,
                'domain_id' => $subIndex->domain_id,
                'questions' => $subIndex->questions->map(fn ($question) => [
                    'question_id' => $question->question_id,
                    'is_scored' => (bool) $question->is_scored,
                    'weight' => (float) ($question->pivot->weight ?? 1.0),
                    'options' => $question->options->map(fn ($option) => [
                        'option_id' => $option->option_id,
                        'score_weight' => $option->score_weight,
                    ])->all(),
                ])->all(),
            ])->all();
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
