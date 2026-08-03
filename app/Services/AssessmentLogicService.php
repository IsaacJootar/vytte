<?php

namespace App\Services;

use App\Models\DepartmentFrameworkVersion;
use App\Models\FrameworkQuestionPlacement;
use App\Support\ResponseInputContract;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Owns the small, frozen branching language used by assessment snapshots.
 *
 * Rules may only read earlier questions. That makes them deterministic, prevents cycles,
 * and lets the same evaluator serve author preview, both runners, completeness, and scoring.
 */
class AssessmentLogicService
{
    public const COMPARISONS = [
        'IS_ANSWERED',
        'IS_NOT_ANSWERED',
        'OPTION_SELECTED',
        'OPTION_NOT_SELECTED',
        'STATE_IS',
        'NUMBER_EQUALS',
        'NUMBER_GREATER_THAN',
        'NUMBER_AT_LEAST',
        'NUMBER_LESS_THAN',
        'NUMBER_AT_MOST',
        'TEXT_PRESENT',
    ];

    public function saveRule(DepartmentFrameworkVersion $framework, FrameworkQuestionPlacement $target, array $input): array
    {
        $framework->loadMissing('questionPlacements.questionVersion');
        $placements = $framework->questionPlacements->keyBy('question_id');
        $conditions = collect($input['conditions'] ?? [])->map(function (array $condition) use ($placements, $target): array {
            $sourceId = (string) ($condition['source_question_id'] ?? '');
            $comparison = (string) ($condition['comparison'] ?? '');
            $source = $placements->get($sourceId);

            if (! $source || (int) $source->display_order >= (int) $target->display_order) {
                throw ValidationException::withMessages([
                    'conditions' => 'Each condition must use a question that appears earlier in the assessment.',
                ]);
            }
            if (! in_array($comparison, self::COMPARISONS, true)) {
                throw ValidationException::withMessages(['conditions' => 'Choose a supported condition.']);
            }

            $value = $condition['value'] ?? null;
            if (in_array($comparison, ['OPTION_SELECTED', 'OPTION_NOT_SELECTED'], true)) {
                $optionIds = collect($source->questionVersion?->options ?? [])->pluck('option_id')->map(fn ($id) => (string) $id);
                if (! $optionIds->contains((string) $value)) {
                    throw ValidationException::withMessages(['conditions' => 'Choose an answer that belongs to the earlier question.']);
                }
                $value = (int) $value;
            } elseif ($comparison === 'STATE_IS') {
                if (! in_array($value, ResponseInputContract::RESPONSE_STATES, true)) {
                    throw ValidationException::withMessages(['conditions' => 'Choose a valid response state.']);
                }
            } elseif (str_starts_with($comparison, 'NUMBER_')) {
                if (! is_numeric($value)) {
                    throw ValidationException::withMessages(['conditions' => 'Enter a valid number for the condition.']);
                }
                $value = (float) $value;
            } else {
                $value = null;
            }

            return [
                'source_question_id' => $sourceId,
                'comparison' => $comparison,
                'value' => $value,
            ];
        })->values();

        if ($conditions->isEmpty() || $conditions->count() > 10) {
            throw ValidationException::withMessages(['conditions' => 'Add between one and ten conditions.']);
        }

        return [
            'version' => 1,
            'type' => 'response_rule',
            'operator' => ($input['operator'] ?? 'ALL') === 'ANY' ? 'ANY' : 'ALL',
            'conditions' => $conditions->all(),
        ];
    }

    public function isVisible(?array $rule, array $facts): bool
    {
        if (($rule['type'] ?? null) !== 'response_rule' || ($rule['version'] ?? null) !== 1) {
            return true;
        }

        $results = collect($rule['conditions'] ?? [])->map(
            fn (array $condition): bool => $this->conditionMatches($condition, $facts[$condition['source_question_id'] ?? ''] ?? [])
        );
        if ($results->isEmpty()) {
            return true;
        }

        return ($rule['operator'] ?? 'ALL') === 'ANY' ? $results->contains(true) : ! $results->contains(false);
    }

    /** @return array<string, bool> */
    public function visibilityMap(array|Collection $questions, Collection $responses): array
    {
        $facts = $responses->mapWithKeys(fn ($response) => [
            $response->question_id => [
                'state' => $response->response_state ?? 'ANSWERED',
                'option_ids' => collect($response->typed_value['option_ids'] ?? [])
                    ->when($response->value_option_id !== null, fn ($ids) => $ids->push((int) $response->value_option_id))
                    ->map(fn ($id) => (int) $id)->unique()->values()->all(),
                'number' => $response->value_numeric !== null ? (float) $response->value_numeric : null,
                'text' => $response->value_text,
                'has_answer' => $response->value_option_id !== null
                    || $response->value_numeric !== null
                    || filled($response->value_text)
                    || ! empty($response->typed_value['option_ids'] ?? []),
            ],
        ])->all();

        $visibility = [];
        $visibleFacts = [];
        foreach (collect($questions) as $question) {
            $questionId = $question['question_id'];
            $visibility[$questionId] = $this->isVisible($question['applicability'] ?? null, $visibleFacts);
            if ($visibility[$questionId] && isset($facts[$questionId])) {
                $visibleFacts[$questionId] = $facts[$questionId];
            }
        }

        return $visibility;
    }

    public function visibleQuestions(array $questions, array $facts): array
    {
        $visible = [];
        $visibleFacts = [];
        foreach ($questions as $question) {
            $questionId = $question['question_id'];
            if (! $this->isVisible($question['applicability'] ?? null, $visibleFacts)) {
                continue;
            }
            $visible[] = $question;
            if (isset($facts[$questionId])) {
                $visibleFacts[$questionId] = $facts[$questionId];
            }
        }

        return $visible;
    }

    private function conditionMatches(array $condition, array $fact): bool
    {
        $comparison = $condition['comparison'] ?? null;
        $optionIds = collect($fact['option_ids'] ?? [])->map(fn ($id) => (int) $id);
        $number = $fact['number'] ?? null;

        return match ($comparison) {
            'IS_ANSWERED' => (bool) ($fact['has_answer'] ?? false) && ($fact['state'] ?? 'ANSWERED') === 'ANSWERED',
            'IS_NOT_ANSWERED' => ! (bool) ($fact['has_answer'] ?? false) || ($fact['state'] ?? 'MISSING') !== 'ANSWERED',
            'OPTION_SELECTED' => $optionIds->contains((int) ($condition['value'] ?? 0)),
            'OPTION_NOT_SELECTED' => ! $optionIds->contains((int) ($condition['value'] ?? 0)),
            'STATE_IS' => ($fact['state'] ?? 'MISSING') === ($condition['value'] ?? null),
            'NUMBER_EQUALS' => $number !== null && $number === (float) ($condition['value'] ?? 0),
            'NUMBER_GREATER_THAN' => $number !== null && $number > (float) ($condition['value'] ?? 0),
            'NUMBER_AT_LEAST' => $number !== null && $number >= (float) ($condition['value'] ?? 0),
            'NUMBER_LESS_THAN' => $number !== null && $number < (float) ($condition['value'] ?? 0),
            'NUMBER_AT_MOST' => $number !== null && $number <= (float) ($condition['value'] ?? 0),
            'TEXT_PRESENT' => filled($fact['text'] ?? null),
            default => false,
        };
    }
}
