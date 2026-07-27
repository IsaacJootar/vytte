<?php

namespace App\Services\Reporting;

/**
 * Scores a workspace's own "Tailored by your team" questions — in their own private lane,
 * never touching the official Vytte score.
 *
 * It deliberately mirrors the official method's 0-100 normalisation so the custom score reads
 * on the same scale, then averages the answered questions. Two separate numbers on one scale:
 * the official (comparable) and the tailored (the workspace's own).
 */
class CustomSectionScoringService
{
    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @param  array<string, mixed>  $answers  question id => answer (YES/NO or 1-5)
     * @return array{overall: ?float, questions: array<int, array<string, mixed>>, answered: int, total: int}
     */
    public function score(array $questions, array $answers): array
    {
        $scored = [];
        $values = [];

        foreach ($questions as $q) {
            $answer = $answers[$q['id']] ?? null;
            $questionScore = $this->questionScore($q, $answer);

            if ($questionScore !== null) {
                $values[] = $questionScore;
            }

            $scored[] = [
                'id' => $q['id'],
                'text' => $q['text'] ?? '',
                'response_type' => $q['response_type'] ?? 'YES_NO',
                'answer' => $answer,
                'score' => $questionScore,
            ];
        }

        return [
            'overall' => $values === [] ? null : round(array_sum($values) / count($values), 1),
            'questions' => $scored,
            'answered' => count($values),
            'total' => count($questions),
        ];
    }

    /**
     * Aggregates a tailored section answered by many respondents. Each respondent's answer set
     * is scored 0-100 on its own; the section score is the arithmetic mean of those private
     * scores — the same method the official multi-respondent aggregation uses. Per question, the
     * shown value is the mean of the respondents who answered it.
     *
     * @param  array<int, array<string, mixed>>  $questions
     * @param  array<int, array<string, mixed>>  $answerSets  one answer map per respondent
     * @return array{overall: ?float, questions: array<int, array<string, mixed>>, answered: int, total: int, respondents: int}
     */
    public function aggregate(array $questions, array $answerSets): array
    {
        $respondentOveralls = [];
        foreach ($answerSets as $answers) {
            $overall = $this->score($questions, is_array($answers) ? $answers : [])['overall'];
            if ($overall !== null) {
                $respondentOveralls[] = $overall;
            }
        }

        $scored = [];
        foreach ($questions as $q) {
            $perQuestion = [];
            foreach ($answerSets as $answers) {
                $value = $this->questionScore($q, is_array($answers) ? ($answers[$q['id']] ?? null) : null);
                if ($value !== null) {
                    $perQuestion[] = $value;
                }
            }
            $mean = $perQuestion === [] ? null : round(array_sum($perQuestion) / count($perQuestion), 1);
            $scored[] = [
                'id' => $q['id'],
                'text' => $q['text'] ?? '',
                'response_type' => $q['response_type'] ?? 'YES_NO',
                // The report shows one value per row; for many respondents that is the mean score.
                'answer' => $mean === null ? null : number_format($mean, 0).' / 100',
                'score' => $mean,
            ];
        }

        return [
            'overall' => $respondentOveralls === [] ? null : round(array_sum($respondentOveralls) / count($respondentOveralls), 1),
            'questions' => $scored,
            'answered' => count($respondentOveralls),
            'total' => count($questions),
            'respondents' => count($respondentOveralls),
        ];
    }

    /**
     * A single question's 0-100 score, or null when unanswered.
     *
     * @param  array<string, mixed>  $q
     */
    private function questionScore(array $q, mixed $answer): ?float
    {
        if ($answer === null || $answer === '') {
            return null;
        }

        if (($q['response_type'] ?? 'YES_NO') === 'YES_NO') {
            $good = $q['good_answer'] ?? 'YES';

            return strtoupper((string) $answer) === strtoupper((string) $good) ? 100.0 : 0.0;
        }

        // 1-5 scale: 1 => 0, 5 => 100, reversed when 1 is best.
        $value = (int) $answer;
        if ($value < 1 || $value > 5) {
            return null;
        }
        $base = ($value - 1) / 4 * 100;

        return ! empty($q['reversed']) ? round(100 - $base, 1) : round($base, 1);
    }
}
