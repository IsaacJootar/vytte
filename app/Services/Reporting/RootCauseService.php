<?php

namespace App\Services\Reporting;

/**
 * Infers probable root causes from the pattern of findings.
 *
 * A score says a domain is weak; a root cause says *why it is probably weak*. This does not
 * guess — it reads the structure deterministically: a cluster of failing indicators inside a
 * domain is a systemic cause, not bad luck; and a weak domain sitting under other weak domains
 * it plausibly drives is a probable upstream cause. Stated as "the pattern suggests", never as
 * fact, because the platform infers causes, it does not diagnose them.
 */
class RootCauseService
{
    /** Governance is foundational — when it is weak it tends to drag every other domain down. */
    private const UPSTREAM_DOMAIN = 'GOV';

    /**
     * Domain dependencies beyond governance, drawn from the WHO health-system building blocks:
     * an overstretched workforce shows up as safety and service failures; weak financing
     * constrains supplies and staffing; weak infrastructure/supplies compromises safety and
     * service delivery; unreliable information undermines governance decisions. Each of these
     * is a narrower, more specific claim than the governance rule above, so it only fires when
     * the specific downstream domain it names is itself weak — not "many things are wrong".
     *
     * @var array<string, array<int, string>>
     */
    private const UPSTREAM_DEPENDENCIES = [
        'WORK' => ['SAFE', 'SERV'],
        'FIN' => ['RES', 'WORK'],
        'RES' => ['SAFE', 'SERV'],
        'INFO' => ['GOV'],
    ];

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

        $weakDomains = $weaknesses->pluck('measurement_domain')->filter()->unique();
        $nameFor = $weaknesses->mapWithKeys(fn ($f) => [$f['measurement_domain'] ?? null => $f['subject']]);

        // 2. Cross-domain: weak governance under several other weak domains. Broad and
        // foundational — it does not name a specific pair, only that enough else is wrong.
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

        // 3. Cross-domain: specific, narrower upstream/downstream pairs. Only fires when the
        // exact named downstream domain is also weak, not on a general "several things are
        // wrong" signal, so each claim is a named, checkable pattern rather than a guess.
        foreach (self::UPSTREAM_DEPENDENCIES as $upstream => $downstreams) {
            if (! $weakDomains->contains($upstream)) {
                continue;
            }

            $affected = $weakDomains->intersect($downstreams);
            if ($affected->isEmpty()) {
                continue;
            }

            $upstreamName = $nameFor[$upstream] ?? $upstream;
            $affectedNames = $affected->map(fn ($d) => $nameFor[$d] ?? $d)->values()->join(' and ');

            $causes[] = [
                'subject' => $upstreamName,
                'measurement_domain' => $upstream,
                'severity' => 'MEDIUM',
                'statement' => 'Weak '.$upstreamName.' sits underneath weakness in '.$affectedNames.'. '
                    .'The pattern suggests '.$upstreamName.' may be a contributing upstream cause — improving it is likely to help lift '.$affectedNames.' as well.',
                'contributing_indicators' => [],
                'is_upstream' => true,
            ];
        }

        return $causes;
    }
}
