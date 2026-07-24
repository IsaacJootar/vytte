<?php

namespace App\Services\Reporting;

/**
 * Generates recommendations, each citing the finding it came from.
 *
 * The governing rule from RECOMMENDATION_FRAMEWORK.md: a recommendation must name the
 * finding it came from. No cited finding, no recommendation. Generic advice is banned by
 * construction, because in a health report generic advice borrows the authority of the
 * assessment without its evidence.
 *
 * Rule-based and deterministic. This is the floor the future AI layer narrates over and
 * is tested against; AI may rephrase these but may never invent one without a finding.
 */
class RecommendationService
{
    /**
     * Which kind of action a measurement domain most naturally calls for. A suggestion for
     * the reader, not a constraint.
     *
     * @var array<string, string>
     */
    private const DOMAIN_ACTION = [
        'GOV' => 'Governance',
        'WORK' => 'Workforce',
        'SERV' => 'Service delivery',
        'SAFE' => 'Quality and safety',
        'RES' => 'Infrastructure and supplies',
        'INFO' => 'Information and records',
        'PCOM' => 'Community and experience',
        'FIN' => 'Financing',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $findings
     * @return array<int, array<string, mixed>>
     */
    public function recommendations(array $findings): array
    {
        $recommendations = [];

        foreach ($findings as $finding) {
            // A recommendation only comes from a finding, and only these three kinds of
            // finding call for an action. A weakness or critical finding needs fixing; a
            // data gap — a domain that could not be scored — needs the missing data, which
            // is itself an action, and the most useful one when little was answered. A
            // partial data gap (the domain scored, just thinly) is skipped: the score-based
            // finding for that same domain already carries its recommendation.
            $isUnscoredGap = $finding['category'] === 'DATA_GAP' && ($finding['score'] ?? null) === null;
            if (! in_array($finding['category'], ['CRITICAL_FINDING', 'WEAKNESS'], true) && ! $isUnscoredGap) {
                continue;
            }

            $recommendations[] = [
                'statement' => $this->statementFor($finding),
                'type' => $isUnscoredGap ? 'Data collection' : (self::DOMAIN_ACTION[$finding['measurement_domain'] ?? ''] ?? 'General'),
                'measurement_domain' => $finding['measurement_domain'] ?? null,
                'horizon' => ($finding['severity'] === 'HIGH' || $isUnscoredGap) ? 'IMMEDIATE' : 'MEDIUM_TERM',
                'priority' => $isUnscoredGap ? 'MEDIUM' : $finding['severity'],
                'expected_impact' => $finding['expected_impact'] ?? null,
                // The concrete items this action should tackle first (up to three).
                'focus_items' => collect($finding['failed_indicators'] ?? [])->take(3)->pluck('question_text')->all(),
                // The citation. This is what makes the recommendation defensible.
                'from_finding' => [
                    'subject' => $finding['subject'],
                    'category' => $finding['category'],
                    'statement' => $finding['statement'],
                ],
            ];
        }

        return $recommendations;
    }

    /**
     * Build a specific, contextual recommendation: the governed intervention for this domain
     * and severity, aimed at the concrete failing items where they exist. Falls back to a
     * plain statement only when no library entry applies.
     *
     * @param  array<string, mixed>  $finding
     */
    private function statementFor(array $finding): string
    {
        if ($finding['category'] === 'CRITICAL_FINDING') {
            return 'Address the critical failure before relying on any other result. It outranks everything else in this report.';
        }

        if ($finding['category'] === 'DATA_GAP') {
            return 'Collect the missing answers for '.$finding['subject'].'. It could not be scored, and a blank is not a pass — this is the first step before the area can be judged at all.';
        }

        $intervention = InterventionLibrary::forDomain($finding['measurement_domain'] ?? null, $finding['severity'] ?? 'MEDIUM');
        if ($intervention === null) {
            return 'Strengthen '.$finding['subject'].'. It is a weak area, and improving it will move the overall result.';
        }

        $focus = collect($finding['failed_indicators'] ?? [])->take(2)->pluck('question_text')->all();
        $suffix = $focus !== [] ? ' Start with the items scoring lowest: "'.implode('", "', $focus).'".' : '';

        return $intervention.$suffix;
    }
}
