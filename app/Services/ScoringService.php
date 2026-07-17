<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Response;
use App\Models\SubIndex;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScoringService
{
    public const ALGORITHM_VERSION = 'vytte-3.0-snapshot-profile';

    public function calculate(Assessment $assessment): void
    {
        $responses = Response::where('assessment_id', $assessment->assessment_id)
            ->whereNull('respondent_id')
            ->whereNull('public_response_session_id')
            ->whereNotNull('value_option_id')
            ->get()
            ->keyBy('question_id');

        $result = $this->scoreResponseSet($assessment, $responses);
        $this->persistResult($assessment, $result, 'STAFF');
    }

    public function scoreResponseSet(Assessment $assessment, Collection $responses): array
    {
        $moduleIds = DB::table('assessment_module_scope')
            ->where('assessment_id', $assessment->assessment_id)
            ->where('in_scope', true)
            ->pluck('module_id')
            ->toArray();

        if (empty($moduleIds)) {
            return [
                'sub_indices' => [],
                'domains' => [],
                'overall_score' => null,
                'calibration_status' => 'NOT_CALIBRATED',
                'scoring_version' => self::ALGORITHM_VERSION,
            ];
        }

        if (! $responses->isEmpty() && ! $responses->has($responses->first()->question_id)) {
            $responses = $responses->keyBy('question_id');
        }

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
                'sub_index_id' => (int) $subIndex['sub_index_id'],
                'score' => $score,
                'calibration_status' => $status,
                'domain_id' => (int) $subIndex['domain_id'],
            ];
        }

        $domainGroups = [];
        foreach ($subIndexResults as $data) {
            $domainGroups[$data['domain_id']][] = $data;
        }

        $domainResults = [];
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

            $domainResults[$domainId] = [
                'domain_id' => (int) $domainId,
                'score' => $domainScore,
                'calibration_status' => $domainStatus,
            ];
        }

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

        return [
            'sub_indices' => array_values($subIndexResults),
            'domains' => array_values($domainResults),
            'overall_score' => $overallScore,
            'calibration_status' => $overallStatus,
            'scoring_version' => self::ALGORITHM_VERSION,
        ];
    }

    public function persistResult(Assessment $assessment, array $result, string $respondentType): void
    {
        $calculatedAt = now();
        foreach ($result['sub_indices'] as $subIndex) {
            DB::table('sub_index_scores')->upsert(
                [[
                    'assessment_id' => $assessment->assessment_id,
                    'sub_index_id' => $subIndex['sub_index_id'],
                    'respondent_type' => $respondentType,
                    'score' => $subIndex['score'],
                    'calibration_status' => $subIndex['calibration_status'],
                    'scoring_version' => $result['scoring_version'],
                    'calculated_at' => $calculatedAt,
                ]],
                ['assessment_id', 'sub_index_id', 'respondent_type'],
                ['score', 'calibration_status', 'scoring_version', 'calculated_at']
            );
        }

        foreach ($result['domains'] as $domain) {
            DB::table('domain_scores')->upsert(
                [[
                    'assessment_id' => $assessment->assessment_id,
                    'domain_id' => $domain['domain_id'],
                    'score' => $domain['score'],
                    'calibration_status' => $domain['calibration_status'],
                    'scoring_version' => $result['scoring_version'],
                    'calculated_at' => $calculatedAt,
                ]],
                ['assessment_id', 'domain_id'],
                ['score', 'calibration_status', 'scoring_version', 'calculated_at']
            );
        }

        $overallScore = $result['overall_score'];
        $maturityLevelId = null;
        if ($overallScore !== null) {
            $maturityLevelId = DB::table('maturity_levels')
                ->where('min_score', '<=', $overallScore)
                ->where('max_score', '>', $overallScore)
                ->value('level_id');
            if ($maturityLevelId === null && $overallScore >= 100) {
                $maturityLevelId = DB::table('maturity_levels')->orderByDesc('level_number')->value('level_id');
            }
        }

        $moduleCount = DB::table('assessment_module_scope')
            ->where('assessment_id', $assessment->assessment_id)
            ->where('in_scope', true)
            ->count();
        DB::table('assessment_scores')->upsert(
            [[
                'assessment_id' => $assessment->assessment_id,
                'overall_score' => $overallScore,
                'calibration_status' => $result['calibration_status'],
                'scoring_version' => $result['scoring_version'],
                'expected_module_count' => $moduleCount,
                'active_module_count' => $moduleCount,
                'maturity_level_id' => $maturityLevelId,
                'calculated_at' => $calculatedAt,
            ]],
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
