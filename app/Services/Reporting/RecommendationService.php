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
            // Only findings that describe a problem generate a recommendation. Strengths,
            // opportunities and data gaps are reported but not turned into actions here.
            if (! in_array($finding['category'], ['CRITICAL_FINDING', 'WEAKNESS'], true)) {
                continue;
            }

            $recommendations[] = [
                'statement' => $this->statementFor($finding),
                'type' => self::DOMAIN_ACTION[$finding['measurement_domain'] ?? ''] ?? 'General',
                'horizon' => $finding['severity'] === 'HIGH' ? 'IMMEDIATE' : 'MEDIUM_TERM',
                'priority' => $finding['severity'],
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
     * @param  array<string, mixed>  $finding
     */
    private function statementFor(array $finding): string
    {
        if ($finding['category'] === 'CRITICAL_FINDING') {
            return 'Address the critical failure before relying on any other result. It outranks everything else in this report.';
        }

        return 'Strengthen '.$finding['subject'].'. It is the weakest area measured and the one where improvement will move the overall result most.';
    }
}
