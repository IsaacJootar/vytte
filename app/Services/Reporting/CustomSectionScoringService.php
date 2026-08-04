<?php

namespace App\Services\Reporting;

use App\Support\LocalQuestionFormat;

/**
 * Scores a workspace's own local questions — as an optional local score, never touching the
 * published assessment's frozen score or benchmark.
 *
 * It deliberately mirrors the published method's 0-100 normalisation so the local score reads
 * on the same scale, then averages only the questions explicitly marked scored. Two separate
 * numbers on one scale: the published (comparable) and the local (the workspace's own).
 * Unscored questions retain a displayable answer but never contribute a score.
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
                'answer' => LocalQuestionFormat::displayAnswer($q, $answer),
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
            $recordedAnswers = [];
            foreach ($answerSets as $answers) {
                $answer = is_array($answers) ? ($answers[$q['id']] ?? null) : null;
                if (! LocalQuestionFormat::isBlank($answer)) {
                    $recordedAnswers[] = $answer;
                }
                $value = $this->questionScore($q, $answer);
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
                'answer' => $mean !== null
                    ? number_format($mean, 0).' / 100'
                    : (count($recordedAnswers) === 1
                        ? LocalQuestionFormat::displayAnswer($q, $recordedAnswers[0])
                        : (count($recordedAnswers) > 1 ? count($recordedAnswers).' responses' : null)),
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
        if (LocalQuestionFormat::isBlank($answer) || array_key_exists('is_scored', $q) && ! $q['is_scored']) {
            return null;
        }

        $type = $q['response_type'] ?? LocalQuestionFormat::YES_NO;
        if (in_array($type, [LocalQuestionFormat::YES_NO, LocalQuestionFormat::YES_NO_NA], true)) {
            if ($answer === 'NOT_APPLICABLE') {
                return null;
            }
            $good = $q['good_answer'] ?? 'YES';

            return strtoupper((string) $answer) === strtoupper((string) $good) ? 100.0 : 0.0;
        }

        if ($type !== LocalQuestionFormat::SCALE_5) {
            return null;
        }

        // 1-5 scale: 1 => 0, 5 => 100, reversed when 1 is best.
        $value = (int) $answer;
        if ($value < 1 || $value > 5) {
            return null;
        }
        $base = ($value - 1) / 4 * 100;

        $lowerIsBetter = ($q['score_direction'] ?? null) === 'LOWER_IS_BETTER'
            || ! empty($q['reversed']);

        return $lowerIsBetter ? round(100 - $base, 1) : round($base, 1);
    }
}
