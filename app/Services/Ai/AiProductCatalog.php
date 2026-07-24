<?php

namespace App\Services\Ai;

/**
 * The AI report products — distinct, purpose-built summaries over the same frozen intelligence.
 *
 * Each product is a different audience and a different question, but all obey the same hard
 * rule (never invent; cite the structured findings). What makes them genuinely different is
 * what each is *given*: the donor summary sees strengths and strategic priorities, the clinical
 * summary sees only the safety and service domains, the root-cause analysis sees the causes and
 * the findings feeding them. A pure constant, so the set is governed and testable.
 */
class AiProductCatalog
{
    /**
     * key => [name, blurb, instruction, domains, include, insight_categories].
     * - domains: measurement domains to foreground ([] = all).
     * - include: which structured sections to hand the model.
     * - insight_categories: which insight categories to include ([] = none).
     *
     * @var array<string, array<string, mixed>>
     */
    public const PRODUCTS = [
        'EXECUTIVE_BRIEFING' => [
            'name' => 'Executive briefing',
            'blurb' => 'For leadership: where the facility stands and the decisions needed.',
            'instruction' => 'Write a brief for senior leadership. Three short paragraphs: the overall standing, the one or two things that matter most right now, and the decisions or priorities for leadership. Lead with anything critical.',
            'domains' => [],
            'include' => ['score', 'findings', 'risks', 'recommendations'],
            'insight_categories' => ['CRITICAL_FINDING', 'STRATEGIC_PRIORITY'],
        ],
        'DIAGNOSTIC_SUMMARY' => [
            'name' => 'Diagnostic summary',
            'blurb' => 'What is wrong, how serious, and the evidence for it.',
            'instruction' => 'Write a diagnostic summary: what is wrong with this facility, how serious each problem is, and the concrete evidence (the failing items) behind each. Be precise and honest; do not soften.',
            'domains' => [],
            'include' => ['score', 'findings', 'root_causes'],
            'insight_categories' => ['PAIN_POINT', 'SYSTEMIC_ISSUE'],
        ],
        'ROOT_CAUSE' => [
            'name' => 'Root-cause analysis',
            'blurb' => 'The probable underlying causes and how the weak areas connect.',
            'instruction' => 'Explain the probable underlying causes of the weaknesses and how the weak areas connect. Use only the root causes and findings provided. Frame causes as probable ("the pattern suggests"), never as certain.',
            'domains' => [],
            'include' => ['root_causes', 'findings'],
            'insight_categories' => [],
        ],
        'DONOR_SUMMARY' => [
            'name' => 'Donor summary',
            'blurb' => 'For a funder: strengths, progress, and what investment would unlock.',
            'instruction' => 'Write a summary for a donor or funder. Lead with genuine strengths and achievements, be honest about the priority gaps, and frame the main weaknesses as what targeted investment would unlock. Do not overstate; a funder values candour.',
            'domains' => [],
            'include' => ['score', 'findings', 'recommendations'],
            'insight_categories' => ['STRENGTH', 'ACHIEVEMENT', 'GOOD_PRACTICE', 'STRATEGIC_PRIORITY', 'QUICK_WIN'],
        ],
        'CLINICAL_SUMMARY' => [
            'name' => 'Clinical summary',
            'blurb' => 'For clinical leads: safety, quality, and priority clinical actions.',
            'instruction' => 'Write a summary for clinical leadership focused on safety and quality of care. Cover the clinical risks and the priority clinical actions. Stay within the safety and service areas provided; do not give treatment advice.',
            'domains' => ['SAFE', 'SERV'],
            'include' => ['findings', 'risks', 'recommendations'],
            'insight_categories' => ['CLINICAL_RISK', 'CRITICAL_FINDING', 'PAIN_POINT'],
        ],
        'OPERATIONAL_SUMMARY' => [
            'name' => 'Operational summary',
            'blurb' => 'For operations: day-to-day running, bottlenecks, and quick wins.',
            'instruction' => 'Write a summary for an operations manager: how the facility runs day to day, where the bottlenecks are, and the quick wins to tackle first. Be practical and concrete.',
            'domains' => ['SERV', 'WORK', 'RES', 'INFO'],
            'include' => ['findings', 'recommendations'],
            'insight_categories' => ['OPERATIONAL_RISK', 'PAIN_POINT', 'QUICK_WIN'],
        ],
    ];

    public static function exists(string $product): bool
    {
        return array_key_exists($product, self::PRODUCTS);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $product): ?array
    {
        return self::PRODUCTS[$product] ?? null;
    }

    /**
     * @return array<string, array{name: string, blurb: string}>
     */
    public static function options(): array
    {
        return array_map(fn ($p) => ['name' => $p['name'], 'blurb' => $p['blurb']], self::PRODUCTS);
    }
}
