<?php

namespace App\Services\Reporting;

/**
 * The governed lenses a report can be read through, and what each one foregrounds.
 *
 * A lens is not a re-sort of the same list — it is a different reading of the same
 * assessment. Each lens declares which measurement domains it cares about, which insight
 * categories it leads with, and how it orders what it finds. The Risk lens leads with what
 * could go wrong across every domain; the Value lens leads with strengths and quick wins in
 * the money-and-resource domains; the Clinical lens ignores financing entirely. Same data,
 * genuinely different reports.
 *
 * The seven codes map to seeded `analysis_lenses` (verified by LensCatalogTest). Kept as a
 * pure constant so the engine stays deterministic and frozen-snapshot-safe.
 */
class LensCatalog
{
    /**
     * @var array<string, array{name: string, question: string, domains: array<int, string>, categories: array<int, string>, emphasis: string}>
     */
    public const LENSES = [
        'EXECUTIVE' => [
            'name' => 'Executive',
            'question' => 'What does leadership need to know?',
            'domains' => [], // all — leadership sees the whole picture, summarised
            'categories' => ['CRITICAL_FINDING', 'STRATEGIC_PRIORITY', 'SYSTEMIC_ISSUE', 'ACHIEVEMENT'],
            'emphasis' => 'executive',
        ],
        'OPERATIONS' => [
            'name' => 'Operational',
            'question' => 'Is the facility running smoothly day to day?',
            'domains' => ['SERV', 'WORK', 'RES', 'INFO'],
            'categories' => ['OPERATIONAL_RISK', 'LOW_PERFORMING', 'PAIN_POINT', 'QUICK_WIN'],
            'emphasis' => 'severity',
        ],
        'QUALITY' => [
            'name' => 'Clinical & Quality',
            'question' => 'Is care safe and of good quality?',
            'domains' => ['SAFE', 'SERV'],
            'categories' => ['CLINICAL_RISK', 'CRITICAL_FINDING', 'PAIN_POINT', 'GOOD_PRACTICE'],
            'emphasis' => 'severity',
        ],
        'RISK' => [
            'name' => 'Risk',
            'question' => 'What could go wrong?',
            'domains' => [], // all — risk respects no boundary
            'categories' => ['CRITICAL_FINDING', 'CLINICAL_RISK', 'OPERATIONAL_RISK', 'COMPLIANCE_RISK'],
            'emphasis' => 'severity',
        ],
        'COMPLIANCE' => [
            'name' => 'Compliance',
            'question' => 'Where do we fall short of the standard?',
            'domains' => ['GOV', 'INFO', 'FIN'],
            'categories' => ['COMPLIANCE_RISK', 'DATA_GAP', 'INSUFFICIENT_EVIDENCE', 'WEAKNESS'],
            'emphasis' => 'severity',
        ],
        'PROGRAMME_EFFECTIVENESS' => [
            'name' => 'Programme',
            'question' => 'Is the programme delivering results?',
            'domains' => ['SERV', 'PCOM', 'INFO'],
            'categories' => ['LOW_PERFORMING', 'STRATEGIC_PRIORITY', 'OPPORTUNITY', 'HIGH_PERFORMING'],
            'emphasis' => 'severity',
        ],
        'EFFICIENCY' => [
            'name' => 'Value',
            'question' => 'Are we getting value, and what would investment unlock?',
            'domains' => ['FIN', 'RES', 'WORK'],
            'categories' => ['STRENGTH', 'ACHIEVEMENT', 'GOOD_PRACTICE', 'QUICK_WIN', 'STRATEGIC_PRIORITY'],
            'emphasis' => 'positive',
        ],
    ];

    public const DEFAULT = 'EXECUTIVE';

    public static function resolve(?string $code): string
    {
        return $code !== null && array_key_exists($code, self::LENSES) ? $code : self::DEFAULT;
    }

    /**
     * @return array<string, array{name: string, question: string}>
     */
    public static function options(): array
    {
        return array_map(fn ($lens) => ['name' => $lens['name'], 'question' => $lens['question']], self::LENSES);
    }
}
