<?php

namespace App\Services\Reporting;

use App\Models\Assessment;

class ComparisonEligibilityService
{
    public function __construct(private readonly ComparisonSeriesService $series) {}

    /** @return array{classification: string, comparable: bool, show_deltas: bool, reason: string, signature: ?string} */
    public function between(Assessment $first, Assessment $second): array
    {
        $firstSignature = $this->series->signatureFor($first);
        $secondSignature = $this->series->signatureFor($second);

        if ($firstSignature && $secondSignature && hash_equals($firstSignature, $secondSignature)) {
            return [
                'classification' => 'DIRECTLY_COMPARABLE',
                'label' => 'Comparable',
                'comparable' => true,
                'show_deltas' => true,
                'reason' => 'Both reports use the same set of questions and scoring, so this comparison is reliable.',
                'signature' => $firstSignature,
            ];
        }

        if (! $firstSignature && ! $secondSignature && $first->composition_hash && hash_equals($first->composition_hash, (string) $second->composition_hash)) {
            return [
                'classification' => 'LEGACY_DIRECTLY_COMPARABLE',
                'label' => 'Comparable',
                'comparable' => true,
                'show_deltas' => true,
                'reason' => 'Both reports use the same set of questions and scoring, so this comparison is reliable. (The older report predates our comparability tracking, but its content matches.)',
                'signature' => null,
            ];
        }

        return [
            'classification' => 'NOT_COMPARABLE',
            'label' => 'Not comparable',
            'comparable' => false,
            'show_deltas' => false,
            'reason' => 'These two reports used different questions or scoring, so Vytte will not calculate changes, ranks, or improvement claims between them. They are shown side by side for context only.',
            'signature' => null,
        ];
    }
}
