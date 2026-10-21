<?php

namespace App\Services\Reporting;

use App\Models\Assessment;
use App\Support\ReportType;

/**
 * What the girls and women themselves reported, for the report of one school or PHC: how many took
 * part, and for each programme outcome the share who gave the answer that matters, with the earlier
 * assessment beside it when there is one. One figure per indicator, the number of answers behind it,
 * and which way is better. Read from the same assessment as the facility findings, so whoever
 * answered it, the facility sections and these figures come from one set of answers.
 */
class ProgrammeOutcomeService
{
    /**
     * @return array<string, mixed>
     */
    public function forAssessment(Assessment $assessment): array
    {
        $results = app(ProgrammeIndicatorService::class)->withComparison($assessment);
        $config = ReportType::config($assessment);
        $notes = (array) ($config['outcome_notes'] ?? []);

        $outcomes = collect($results['indicators'])
            ->groupBy('outcome')
            ->map(fn ($rows, $name) => ['name' => $name ?: 'Other', 'note' => $notes[$name] ?? null, 'indicators' => $rows->values()->all()])
            ->values()
            ->all();

        return [
            'respondents' => $results['respondents'],
            'enough' => $results['enough'],
            'minimum' => $results['minimum'],
            'previous' => $results['previous'] ?? null,
            'outcomes' => $outcomes,
            'endline_indicators' => (array) ($config['endline_indicators'] ?? []),
        ];
    }
}
