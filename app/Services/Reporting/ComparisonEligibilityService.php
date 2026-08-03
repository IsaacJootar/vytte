<?php

namespace App\Services\Reporting;

use App\Models\Assessment;

class ComparisonEligibilityService
{
    /** @return array{classification: string, comparable: bool, show_deltas: bool, reason: string, signature: ?string} */
    public function between(Assessment $first, Assessment $second): array
    {
        $firstSignature = $first->reportSnapshot?->comparison_signature ?? $first->snapshot?->comparison_signature;
        $secondSignature = $second->reportSnapshot?->comparison_signature ?? $second->snapshot?->comparison_signature;

        if ($firstSignature && $secondSignature && hash_equals($firstSignature, $secondSignature)) {
            return [
                'classification' => 'DIRECTLY_COMPARABLE',
                'comparable' => true,
                'show_deltas' => true,
                'reason' => 'Both reports use the same frozen methodology and comparison signature.',
                'signature' => $firstSignature,
            ];
        }

        if (! $firstSignature && ! $secondSignature && $first->composition_hash && hash_equals($first->composition_hash, (string) $second->composition_hash)) {
            return [
                'classification' => 'LEGACY_DIRECTLY_COMPARABLE',
                'comparable' => true,
                'show_deltas' => true,
                'reason' => 'These legacy reports have the same frozen composition, but predate explicit methodology signatures.',
                'signature' => null,
            ];
        }

        return [
            'classification' => 'NOT_COMPARABLE',
            'comparable' => false,
            'show_deltas' => false,
            'reason' => 'These results use different or undocumented methodologies. They are shown side by side for context, but Vytte will not calculate changes, ranks, or improvement claims.',
            'signature' => null,
        ];
    }
}
