<?php

namespace App\Services\Reporting;

/**
 * Classifies findings into insights a human can act on.
 *
 * An insight is not new information — it is findings grouped and ranked by what they mean.
 * The 21 insight categories carry a polarity (good, bad, neutral), so a report can lead
 * with what matters rather than an arbitrary order.
 *
 * Deterministic and pure, like the diagnostics it reads.
 */
class InsightService
{
    /**
     * @param  array<int, array<string, mixed>>  $findings
     * @return array{strengths: array<int, mixed>, weaknesses: array<int, mixed>, critical: array<int, mixed>, data_gaps: array<int, mixed>, headline: ?string}
     */
    public function insights(array $findings): array
    {
        $collection = collect($findings);

        $critical = $collection->where('category', 'CRITICAL_FINDING')->values()->all();
        $weaknesses = $collection->whereIn('category', ['WEAKNESS'])
            ->sortByDesc(fn ($f) => $f['severity'] === 'HIGH' ? 2 : 1)->values()->all();
        $strengths = $collection->where('category', 'STRENGTH')
            ->sortByDesc('score')->values()->all();
        $dataGaps = $collection->where('category', 'DATA_GAP')->values()->all();

        return [
            'critical' => $critical,
            'weaknesses' => $weaknesses,
            'strengths' => $strengths,
            'data_gaps' => $dataGaps,
            'headline' => $this->headline($critical, $weaknesses, $strengths, $dataGaps),
        ];
    }

    /**
     * One honest sentence for the top of a report.
     */
    private function headline(array $critical, array $weaknesses, array $strengths, array $dataGaps): ?string
    {
        if ($critical !== []) {
            return 'A critical failure was found and needs attention regardless of the overall score.';
        }

        // If most of the assessment could not be scored, say so before anything else.
        if (count($dataGaps) > 0 && count($weaknesses) + count($strengths) === 0) {
            return 'Too little was answered to draw firm conclusions. Treat the results below as provisional.';
        }

        if (count($weaknesses) >= 3) {
            return 'Several areas are weak, which usually points to a shared underlying cause worth investigating.';
        }

        if ($weaknesses !== []) {
            return 'Most areas are holding up, with a small number needing attention.';
        }

        if ($strengths !== []) {
            return 'A generally strong result, with the areas below performing particularly well.';
        }

        return null;
    }
}
