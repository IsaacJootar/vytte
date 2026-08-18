<?php

namespace App\Services\Reporting;

/**
 * Classifies exact assessed issues across two reports by their stable issue_key, not by domain
 * score bands. A domain score moving is a symptom; this is the underlying cause list.
 */
class IssueTrackingService
{
    /**
     * @param  bool  $seriesComparable  False when a chronologically earlier report exists for
     *                                  this setting but is not from the same compatible series
     *                                  (methodology changed) — every currently open issue is then
     *                                  reported as NOT_COMPARABLE rather than defaulting to NEW,
     *                                  because Vytte must not infer that an unasked or removed
     *                                  question was resolved.
     * @return array{open: array<int, mixed>, resolved: array<int, mixed>, counts: array<string, int>}
     */
    public function compare(array $currentViews, ?array $previousViews, bool $seriesComparable = true): array
    {
        $current = collect($currentViews['issue_register'] ?? [])->keyBy('issue_key');
        $previous = collect($previousViews['issue_register'] ?? [])->keyBy('issue_key');

        $open = $current->map(function ($issue, $key) use ($previous, $seriesComparable) {
            if (! $seriesComparable) {
                $issue['progress_status'] = 'NOT_COMPARABLE';

                return $issue;
            }

            if (! $previous->has($key)) {
                $issue['progress_status'] = 'NEW';

                return $issue;
            }

            $previousScore = $previous->get($key)['item_score'] ?? null;
            $currentScore = $issue['item_score'] ?? null;
            $issue['progress_status'] = ($previousScore !== null && $currentScore !== null && $currentScore > $previousScore)
                ? 'IMPROVING'
                : 'PERSISTENT';

            return $issue;
        })->values();

        $resolved = $seriesComparable
            ? $previous->reject(fn ($issue, $key) => $current->has($key))
                ->map(function ($issue) {
                    $issue['progress_status'] = 'RESOLVED';

                    return $issue;
                })->values()
            : collect();

        return [
            'open' => $open->all(),
            'resolved' => $resolved->all(),
            'counts' => [
                'new' => $open->where('progress_status', 'NEW')->count(),
                'persistent' => $open->where('progress_status', 'PERSISTENT')->count(),
                'improving' => $open->where('progress_status', 'IMPROVING')->count(),
                'not_comparable' => $open->where('progress_status', 'NOT_COMPARABLE')->count(),
                'resolved' => $resolved->count(),
            ],
        ];
    }
}
