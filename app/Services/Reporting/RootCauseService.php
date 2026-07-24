<?php

namespace App\Services\Reporting;

/**
 * Infers probable root causes from the pattern of findings.
 *
 * A score says a domain is weak; a root cause says *why it is probably weak*. This does not
 * guess — it reads the structure deterministically: a cluster of failing indicators inside a
 * domain is a systemic cause, not bad luck; and weak governance sitting under several other
 * weak domains is a plausible upstream cause. Stated as "the pattern suggests", never as fact,
 * because the platform infers causes, it does not diagnose them.
 */
class RootCauseService
{
    /** Governance is foundational — when it is weak it tends to drag other domains down. */
    private const UPSTREAM_DOMAIN = 'GOV';

    /**
     * @param  array<int, array<string, mixed>>  $findings
     * @return array<int, array<string, mixed>>
     */
    public function rootCauses(array $findings): array
    {
        $causes = [];
        $weaknesses = collect($findings)->whereIn('category', ['WEAKNESS', 'CRITICAL_FINDING']);

        // 1. Domain-level: a cluster of failing indicators inside one domain.
        foreach ($weaknesses as $finding) {
            $failed = $finding['failed_indicators'] ?? [];
            if (count($failed) < 2) {
                continue;
            }

            $examples = collect($failed)->take(3)->pluck('question_text')->all();
            $causes[] = [
                'subject' => $finding['subject'],
                'measurement_domain' => $finding['measurement_domain'] ?? null,
                'severity' => $finding['severity'],
                'statement' => 'The pattern in '.$finding['subject'].' suggests a systemic cause: '
                    .count($failed).' related items are failing together, including "'.implode('", "', $examples).'".',
                'contributing_indicators' => $failed,
            ];
        }

        // 2. Cross-domain: weak governance under several other weak domains.
        $weakDomains = $weaknesses->pluck('measurement_domain')->filter()->unique();
        $governanceWeak = $weakDomains->contains(self::UPSTREAM_DOMAIN);
        $otherWeakCount = $weakDomains->reject(fn ($d) => $d === self::UPSTREAM_DOMAIN)->count();

        if ($governanceWeak && $otherWeakCount >= 2) {
            $causes[] = [
                'subject' => 'Governance & leadership',
                'measurement_domain' => self::UPSTREAM_DOMAIN,
                'severity' => 'HIGH',
                'statement' => 'Weak governance sits underneath '.$otherWeakCount.' other weak areas. '
                    .'The pattern suggests governance is an upstream cause — fixing it is likely to lift the areas that depend on it.',
                'contributing_indicators' => [],
                'is_upstream' => true,
            ];
        }

        return $causes;
    }
}
