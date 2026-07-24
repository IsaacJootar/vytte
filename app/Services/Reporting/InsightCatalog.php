<?php

namespace App\Services\Reporting;

/**
 * The governed insight vocabulary — the 21 seeded insight categories, as a pure reference.
 *
 * Kept as a constant (not a DB read) so the insights engine stays pure and deterministic,
 * frozen-snapshot-safe, and unit-testable without a database. A feature test asserts this
 * catalog matches the seeded `insight_categories` table exactly, so the two can never drift.
 */
class InsightCatalog
{
    /**
     * code => [name, polarity]. Mirrors the seeded methodology insight categories.
     *
     * @var array<string, array{name: string, polarity: string}>
     */
    public const CATEGORIES = [
        // NEGATIVE
        'WEAKNESS' => ['name' => 'Weaknesses', 'polarity' => 'NEGATIVE'],
        'GAP' => ['name' => 'Gaps', 'polarity' => 'NEGATIVE'],
        'LOW_PERFORMING' => ['name' => 'Low-Performing Areas', 'polarity' => 'NEGATIVE'],
        'PAIN_POINT' => ['name' => 'Pain Points', 'polarity' => 'NEGATIVE'],
        'CRITICAL_FINDING' => ['name' => 'Critical Findings', 'polarity' => 'NEGATIVE'],
        'CLINICAL_RISK' => ['name' => 'Clinical Risks', 'polarity' => 'NEGATIVE'],
        'OPERATIONAL_RISK' => ['name' => 'Operational Risks', 'polarity' => 'NEGATIVE'],
        'COMPLIANCE_RISK' => ['name' => 'Compliance Risks', 'polarity' => 'NEGATIVE'],
        'EMERGING_ISSUE' => ['name' => 'Emerging Issues', 'polarity' => 'NEGATIVE'],
        'DECLINE' => ['name' => 'Deterioration', 'polarity' => 'NEGATIVE'],
        'SYSTEMIC_ISSUE' => ['name' => 'Systemic Issues', 'polarity' => 'NEGATIVE'],
        // NEUTRAL
        'OPPORTUNITY' => ['name' => 'Opportunities', 'polarity' => 'NEUTRAL'],
        'QUICK_WIN' => ['name' => 'Quick Wins', 'polarity' => 'NEUTRAL'],
        'STRATEGIC_PRIORITY' => ['name' => 'Strategic Priorities', 'polarity' => 'NEUTRAL'],
        'DATA_GAP' => ['name' => 'Data Gaps', 'polarity' => 'NEUTRAL'],
        'INSUFFICIENT_EVIDENCE' => ['name' => 'Insufficient Evidence', 'polarity' => 'NEUTRAL'],
        'NO_CHANGE' => ['name' => 'No Change', 'polarity' => 'NEUTRAL'],
        // POSITIVE
        'STRENGTH' => ['name' => 'Strengths', 'polarity' => 'POSITIVE'],
        'ACHIEVEMENT' => ['name' => 'Achievements', 'polarity' => 'POSITIVE'],
        'HIGH_PERFORMING' => ['name' => 'High-Performing Areas', 'polarity' => 'POSITIVE'],
        'GOOD_PRACTICE' => ['name' => 'Good Practice to Share', 'polarity' => 'POSITIVE'],
    ];

    /** Categories that only make sense with history across assessments (produced from P4). */
    public const TREND_ONLY = ['EMERGING_ISSUE', 'DECLINE', 'NO_CHANGE'];

    public static function name(string $code): string
    {
        return self::CATEGORIES[$code]['name'] ?? $code;
    }

    public static function polarity(string $code): string
    {
        return self::CATEGORIES[$code]['polarity'] ?? 'NEUTRAL';
    }

    public static function exists(string $code): bool
    {
        return array_key_exists($code, self::CATEGORIES);
    }
}
