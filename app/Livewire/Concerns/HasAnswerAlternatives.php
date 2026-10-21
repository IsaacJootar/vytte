<?php

namespace App\Livewire\Concerns;

use App\Models\Assessment;

/**
 * The "I cannot give a direct answer" choices a respondent sees under a question.
 *
 * Every template offers all five by default, which suits an assessor who walks around and records
 * what they find. A template can narrow them in its frozen collection settings
 * (`answer_alternatives`: state => label), for example to just "I don't know" and "I prefer not to
 * say" when ordinary people answer about themselves. The settings are read from the assessment's own
 * snapshot, so the choices offered cannot be changed from the browser, and a choice the template does
 * not offer is refused when it is sent.
 */
trait HasAnswerAlternatives
{
    /** @var array<string, string> */
    private const DEFAULT_ANSWER_ALTERNATIVES = [
        'NOT_APPLICABLE' => 'Not applicable',
        'UNKNOWN' => 'Unknown',
        'NOT_ASSESSED' => 'Not assessed',
        'NOT_OBSERVED' => 'Not observed',
        'DECLINED' => 'Prefer not to answer',
    ];

    /** @return array<string, string> state => label */
    protected function answerAlternativesFor(?Assessment $assessment): array
    {
        $configured = $assessment?->snapshot?->collection_config['answer_alternatives'] ?? null;

        if (! is_array($configured) || $configured === []) {
            return self::DEFAULT_ANSWER_ALTERNATIVES;
        }

        // Only states the platform knows, in the order the template gave them.
        return array_intersect_key($configured, self::DEFAULT_ANSWER_ALTERNATIVES) ?: self::DEFAULT_ANSWER_ALTERNATIVES;
    }
}
