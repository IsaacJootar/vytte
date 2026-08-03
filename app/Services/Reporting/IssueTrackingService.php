<?php

namespace App\Services\Reporting;

class IssueTrackingService
{
    /** @return array{open: array<int, mixed>, resolved: array<int, mixed>, counts: array<string, int>} */
    public function compare(array $currentViews, ?array $previousViews): array
    {
        $current = collect($currentViews['issue_register'] ?? [])->keyBy('issue_key');
        $previous = collect($previousViews['issue_register'] ?? [])->keyBy('issue_key');

        $open = $current->map(function ($issue, $key) use ($previous) {
            $issue['progress_status'] = $previous->has($key) ? 'PERSISTENT' : 'NEW';

            return $issue;
        })->values();
        $resolved = $previous->reject(fn ($issue, $key) => $current->has($key))
            ->map(function ($issue) {
                $issue['progress_status'] = 'RESOLVED';

                return $issue;
            })->values();

        return [
            'open' => $open->all(),
            'resolved' => $resolved->all(),
            'counts' => [
                'new' => $open->where('progress_status', 'NEW')->count(),
                'persistent' => $open->where('progress_status', 'PERSISTENT')->count(),
                'resolved' => $resolved->count(),
            ],
        ];
    }
}
