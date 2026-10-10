<?php

namespace App\Services\Reporting;

use App\Models\Assessment;
use App\Models\PublicResponseSession;
use Illuminate\Support\Collection;

/**
 * Combines the answers of everyone who completed a school or PHC assessment into the single set of
 * findings its report reads: what is weak, what is working, which must-haves are missing, and what
 * the profile answers were. It exists because a combined result keeps only section scores; the
 * per-question detail and must-have warnings of a one-person assessment would otherwise be lost.
 *
 * Who answers does not matter. With one respondent the pooled findings are that person's own, exactly
 * as before. With several:
 *  - a question's score is the average of their scores for it, and its answer is the most common one;
 *  - a must-have counts as missing when at least half of the respondents tripped it, so one mistaken
 *    answer cannot flip a school to "not ready" and a real gap cannot be outvoted by a single optimist;
 *  - a profile question shows the most common answer (single choice), the average (a number) or every
 *    option anybody chose (select all that apply).
 *
 * Only the respondents the combined result itself counted are used, taken from its frozen references.
 */
class ProgrammeRespondentPoolService
{
    public function __construct(private readonly ProgrammeIndicatorService $indicators) {}

    /**
     * @param  array<string, string>  $roles  domain code => PROVISION|READINESS
     * @param  array<string, string>  $sectionByDomain  domain code => section name
     * @return array{respondents: int, flags: array<int, string>, items: Collection<int, array<string, mixed>>, context: array<int, array<string, mixed>>}
     */
    public function forAssessment(Assessment $assessment, array $roles, array $sectionByDomain): array
    {
        $assessment->loadMissing(['snapshot', 'aggregationResult']);
        $sessions = $this->indicators->eligibleSessions($assessment)->load('scoreResult');
        $questions = $this->questions($assessment);

        return [
            'respondents' => $sessions->count(),
            'flags' => $this->flags($sessions),
            'items' => $this->items($sessions, $questions, $roles, $sectionByDomain),
            'context' => $this->context($sessions, $questions),
        ];
    }

    /** @return array<int, string> */
    private function flags(Collection $sessions): array
    {
        $total = $sessions->count();
        $counts = [];
        foreach ($sessions as $session) {
            foreach (array_unique(array_filter((array) ($session->scoreResult?->payload['critical_findings'] ?? []), 'is_string')) as $finding) {
                $counts[$finding] = ($counts[$finding] ?? 0) + 1;
            }
        }

        return collect($counts)
            ->filter(fn (int $count) => $total > 0 && $count * 2 >= $total)
            ->keys()
            ->values()
            ->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function items(Collection $sessions, array $questions, array $roles, array $sectionByDomain): Collection
    {
        $byQuestion = [];
        foreach ($sessions as $session) {
            foreach ((array) ($session->scoreResult?->payload['domains'] ?? []) as $domain) {
                $code = $domain['domain_code'] ?? '';
                if (! isset($roles[$code])) {
                    continue;
                }
                foreach ((array) ($domain['contributing_questions'] ?? []) as $item) {
                    $slot = &$byQuestion[$item['question_id']];
                    $slot ??= ['domain' => $code, 'scores' => [], 'answers' => []];
                    $slot['scores'][] = (float) ($item['score'] ?? 0);
                    if (! empty($item['answer'])) {
                        $slot['answers'][] = $item['answer'];
                    }
                    unset($slot);
                }
            }
        }

        return collect($byQuestion)->map(function (array $slot, string $questionId) use ($questions, $roles, $sectionByDomain): array {
            $answers = array_count_values($slot['answers']);
            arsort($answers);

            return [
                'question' => $questions[$questionId]['text'] ?? 'Question',
                'answer' => array_key_first($answers),
                'score' => round(array_sum($slot['scores']) / max(count($slot['scores']), 1), 2),
                'role' => $roles[$slot['domain']],
                'section' => $sectionByDomain[$slot['domain']] ?? 'Section',
            ];
        })->values();
    }

    /** @return array<int, array<string, mixed>> */
    private function context(Collection $sessions, array $questions): array
    {
        $groups = [];
        foreach ($questions as $questionId => $question) {
            if ($question['scored'] || $question['type'] === 'OPEN_ENDED') {
                continue;
            }
            $answer = $this->pooledAnswer($sessions, $questionId, $question);
            if ($answer === null) {
                continue;
            }
            $groups[$question['section']][] = ['question' => $question['text'], 'answer' => $answer];
        }

        return collect($groups)->map(fn ($items, $section) => ['section' => $section, 'items' => $items])->values()->all();
    }

    private function pooledAnswer(Collection $sessions, string $questionId, array $question): ?string
    {
        $responses = $sessions
            ->map(fn (PublicResponseSession $session) => collect((array) $session->response_snapshot)
                ->first(fn ($response) => ($response['question_id'] ?? null) === $questionId && ($response['response_state'] ?? 'ANSWERED') === 'ANSWERED'))
            ->filter()
            ->values();

        if ($responses->isEmpty()) {
            return null;
        }

        if ($question['type'] === 'NUMERIC') {
            $numbers = $responses->pluck('value_numeric')->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value);
            if ($numbers->isEmpty()) {
                return null;
            }
            $unit = $question['unit'] ? ' '.$question['unit'] : '';

            return number_format((float) round($numbers->avg()), 0, '.', '').$unit;
        }

        if ($question['type'] === 'MULTI_SELECT') {
            $labels = $responses
                ->flatMap(fn ($response) => (array) ($response['typed_value']['option_ids'] ?? []))
                ->unique()
                ->map(fn ($id) => $question['options'][(int) $id] ?? null)
                ->filter();

            return $labels->isEmpty() ? null : $labels->implode(', ');
        }

        $counts = $responses
            ->map(fn ($response) => $question['options'][(int) ($response['value_option_id'] ?? 0)] ?? null)
            ->filter()
            ->countBy()
            ->sortDesc();

        return $counts->isEmpty() ? null : (string) $counts->keys()->first();
    }

    /**
     * Question wording, type, section and options from the frozen snapshot, keyed by question id.
     *
     * @return array<string, array<string, mixed>>
     */
    private function questions(Assessment $assessment): array
    {
        $map = [];
        foreach ((array) $assessment->snapshot?->payload as $module) {
            foreach ((array) ($module['questions'] ?? []) as $question) {
                $map[$question['question_id']] = [
                    'text' => $question['question_text'] ?? 'Question',
                    'type' => $question['response_type'] ?? null,
                    'scored' => (bool) ($question['is_scored'] ?? false),
                    'section' => $question['section_name'] ?? 'Context',
                    'unit' => $question['numeric_config']['unit'] ?? null,
                    'options' => collect($question['options'] ?? [])->mapWithKeys(fn ($option) => [(int) $option['option_id'] => $option['option_label'] ?? null])->all(),
                ];
            }
        }

        return $map;
    }
}
