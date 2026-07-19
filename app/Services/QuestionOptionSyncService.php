<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionOption;

/**
 * Keeps a question version's answer options aligned with the `question_options` rows the
 * runtime stores answers against.
 *
 * `responses.value_option_id` is a foreign key to `question_options.option_id`. An option
 * that exists only inside version JSON therefore cannot be answered: saving a response
 * against it fails with a foreign key violation at run time, long after the content looked
 * correct in the admin screens.
 *
 * Every path that writes answer options must run them through this service so the ids in
 * the payload are real.
 */
class QuestionOptionSyncService
{
    /**
     * Persists the given options against the question and returns the payload with real
     * option ids substituted in.
     *
     * Rows are matched by option order and never deleted. Historical responses reference
     * option ids, so removing a row would break stored answers and previously published
     * versions that froze that id.
     *
     * @param  list<array{option_label: string, option_order: int, score_weight: float|null, critical_failure?: bool, option_key?: string|null}>  $options
     * @return list<array{option_id: int, option_key: string, option_label: string, option_order: int, score_weight: float|null, critical_failure: bool}>
     */
    public function sync(Question $question, array $options): array
    {
        $synced = [];

        foreach ($options as $option) {
            $order = (int) $option['option_order'];
            $label = (string) $option['option_label'];
            $score = $option['score_weight'] ?? null;
            $criticalFailure = (bool) ($option['critical_failure'] ?? false);

            $row = QuestionOption::firstOrNew([
                'question_id' => $question->question_id,
                'option_order' => $order,
            ]);

            $row->option_label = $label;
            $row->score_weight = $score;
            $row->is_flagged_pain_point = $criticalFailure;
            $row->save();

            $synced[] = [
                'option_id' => (int) $row->option_id,
                'option_key' => $option['option_key'] ?? 'OPT'.$order,
                'option_label' => $label,
                'option_order' => $order,
                'score_weight' => $score === null ? null : (float) $score,
                'critical_failure' => $criticalFailure,
            ];
        }

        return $synced;
    }
}
