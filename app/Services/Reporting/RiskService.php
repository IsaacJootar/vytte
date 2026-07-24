<?php

namespace App\Services\Reporting;

/**
 * Turns findings into risks: likelihood × impact, with the consequence of inaction.
 *
 * A weakness describes the present; a risk describes what the present threatens. Likelihood
 * comes from how firmly the finding is established (its severity); impact comes from how much
 * the domain matters ([DomainRiskProfile]). The two combine into a risk level a manager can
 * triage, and each risk carries the plain answer to "what happens if nothing changes?".
 */
class RiskService
{
    /**
     * @param  array<int, array<string, mixed>>  $findings
     * @return array<int, array<string, mixed>>
     */
    public function risks(array $findings): array
    {
        $risks = [];

        foreach ($findings as $finding) {
            if (! in_array($finding['category'], ['WEAKNESS', 'CRITICAL_FINDING'], true)) {
                continue;
            }

            $likelihood = $this->likelihood($finding['severity']);
            $impact = $finding['category'] === 'CRITICAL_FINDING'
                ? 'HIGH'
                : DomainRiskProfile::criticality($finding['measurement_domain'] ?? null);
            $level = $this->level($likelihood, $impact);

            $risks[] = [
                'subject' => $finding['subject'],
                'measurement_domain' => $finding['measurement_domain'] ?? null,
                'likelihood' => $likelihood,
                'impact' => $impact,
                'level' => $level,
                'statement' => $finding['subject'].' carries a '.strtolower($level).' risk ('
                    .strtolower($likelihood).' likelihood, '.strtolower($impact).' impact).',
                'consequence' => $finding['consequence'] ?? null,
            ];
        }

        // Worst risk first.
        usort($risks, fn ($a, $b) => $this->rank($b['level']) <=> $this->rank($a['level']));

        return $risks;
    }

    private function likelihood(string $severity): string
    {
        return match ($severity) {
            'HIGH' => 'HIGH',
            'MEDIUM' => 'MEDIUM',
            default => 'LOW',
        };
    }

    /**
     * The risk matrix: likelihood × impact → level.
     */
    private function level(string $likelihood, string $impact): string
    {
        $score = $this->rank($likelihood) + $this->rank($impact);

        return match (true) {
            $score >= 5 => 'HIGH',
            $score >= 3 => 'MEDIUM',
            default => 'LOW',
        };
    }

    private function rank(string $band): int
    {
        return match ($band) {
            'HIGH' => 3,
            'MEDIUM' => 2,
            default => 1,
        };
    }
}
