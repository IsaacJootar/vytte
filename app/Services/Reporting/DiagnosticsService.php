<?php

namespace App\Services\Reporting;

/**
 * Turns a frozen report payload into findings.
 *
 * A finding is the atomic unit of the intelligence engine: this specific thing, this bad,
 * for this reason, with this evidence. Everything downstream — insights, recommendations,
 * lens views, and eventually AI narrative — reads findings, never raw scores.
 *
 * Deterministic and pure. It reads the frozen score payload and returns findings; it
 * touches no database and holds no state, so a report produced today reads identically
 * next year. This is why the engine is safe to freeze into the report snapshot.
 */
class DiagnosticsService
{
    /** A score at or below this is a serious weakness. */
    private const WEAK = 45.0;

    /** A score at or above this is a strength. */
    private const STRONG = 70.0;

    /** Below this a weakness is treated as high severity. */
    private const SEVERE = 30.0;

    /**
     * @param  array<string, mixed>  $payload  a report snapshot payload
     * @return array<int, array<string, mixed>>
     */
    public function findings(array $payload): array
    {
        $findings = [];

        $overall = $payload['score'] ?? [];
        if (($overall['calibration_status'] ?? null) === 'CRITICAL_FAILURE') {
            $findings[] = $this->finding(
                subject: 'Overall assessment',
                domain: null,
                category: 'CRITICAL_FINDING',
                severity: 'HIGH',
                score: 0.0,
                statement: 'A critical failure was recorded. One or more answers indicate a problem serious enough to override the overall score.',
                why: 'A critical failure is a single finding grave enough to matter regardless of how everything else scored.',
                evidence: ['calibration' => 'CRITICAL_FAILURE'],
                consequence: 'If the critical failure is left unaddressed, it undermines the trustworthiness of the entire result and exposes the facility to serious, immediate risk.',
                expectedImpact: 'HIGH',
            );
        }

        foreach ($payload['domain_scores'] ?? [] as $domain) {
            $findings = array_merge($findings, $this->fromDomain($domain));
        }

        // Order: worst news first, so the report leads with what matters.
        usort($findings, fn ($a, $b) => $this->rank($b) <=> $this->rank($a));

        return array_values($findings);
    }

    /**
     * @param  array<string, mixed>  $domain
     * @return array<int, array<string, mixed>>
     */
    private function fromDomain(array $domain): array
    {
        $name = $domain['domain_name'] ?? 'Measurement domain';
        $code = $domain['domain_code'] ?? null;
        $score = $domain['score'] ?? null;
        $calibration = $domain['calibration_status'] ?? 'NOT_CALIBRATED';
        $expected = $domain['questions_expected'] ?? null;
        $answered = $domain['questions_answered'] ?? null;

        // Too little was answered to judge this domain. Reported as a finding in its own
        // right, so a thin section is never mistaken for a good one.
        if ($score === null || $calibration === 'NOT_CALIBRATED') {
            return [$this->finding(
                subject: $name,
                domain: $code,
                category: 'DATA_GAP',
                severity: 'INFO',
                score: null,
                statement: $name.' could not be assessed — too few of its questions were answered.',
                why: 'A domain with no usable answers cannot be scored, and a blank is not a pass.',
                evidence: ['calibration' => $calibration, 'answered' => $answered, 'expected' => $expected],
            )];
        }

        $findings = [];

        if ($calibration === 'PARTIAL') {
            $findings[] = $this->finding(
                subject: $name,
                domain: $code,
                category: 'DATA_GAP',
                severity: 'INFO',
                score: (float) $score,
                statement: $name.' was only partly answered, so its score rests on incomplete evidence.',
                why: 'A partial score can move once the remaining questions are answered; the reader should know its footing.',
                evidence: ['calibration' => 'PARTIAL', 'answered' => $answered, 'expected' => $expected],
            );
        }

        if ($score < self::WEAK) {
            $severity = $score < self::SEVERE ? 'HIGH' : 'MEDIUM';
            $failed = $domain['failed_indicators'] ?? [];
            $why = $failed !== []
                ? count($failed).' of its measured items are failing, which points to a systemic gap rather than one-off problems.'
                : 'A low domain score points to a systemic gap that cuts across the questions feeding it.';
            $findings[] = $this->finding(
                subject: $name,
                domain: $code,
                category: 'WEAKNESS',
                severity: $severity,
                score: (float) $score,
                statement: $name.' is weak, scoring '.round($score).' out of 100.',
                why: $why,
                evidence: ['score' => (float) $score, 'calibration' => $calibration, 'failed_indicator_count' => count($failed)],
                failedIndicators: $failed,
                consequence: DomainRiskProfile::consequence($code, $name),
                expectedImpact: $this->expectedImpact((float) $score, $code),
            );
        } elseif ($score >= self::STRONG) {
            $findings[] = $this->finding(
                subject: $name,
                domain: $code,
                category: 'STRENGTH',
                severity: 'POSITIVE',
                score: (float) $score,
                statement: $name.' is a strength, scoring '.round($score).' out of 100.',
                why: 'A high domain score is worth protecting and, where possible, spreading to weaker areas.',
                evidence: ['score' => (float) $score, 'calibration' => $calibration],
            );
        } else {
            $findings[] = $this->finding(
                subject: $name,
                domain: $code,
                category: 'OPPORTUNITY',
                severity: 'LOW',
                score: (float) $score,
                statement: $name.' is moderate, scoring '.round($score).' out of 100, with room to improve.',
                why: 'A middling score is neither a failure nor a strength; it is where reasonable effort moves the needle most.',
                evidence: ['score' => (float) $score, 'calibration' => $calibration],
                failedIndicators: $domain['failed_indicators'] ?? [],
                expectedImpact: $this->expectedImpact((float) $score, $code),
            );
        }

        return $findings;
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(
        string $subject,
        ?string $domain,
        string $category,
        string $severity,
        ?float $score,
        string $statement,
        string $why,
        array $evidence,
        array $failedIndicators = [],
        ?string $consequence = null,
        ?string $expectedImpact = null,
    ): array {
        return [
            'subject' => $subject,
            'measurement_domain' => $domain,
            'category' => $category,
            'severity' => $severity,
            'score' => $score,
            'statement' => $statement,
            'why' => $why,
            'evidence' => $evidence,
            // The concrete questions behind the finding, the plain consequence of leaving it,
            // and how much improving it could move the result. Null where not applicable.
            'failed_indicators' => $failedIndicators,
            'consequence' => $consequence,
            'expected_impact' => $expectedImpact,
        ];
    }

    /**
     * How much headroom this domain has, weighted by how much the domain matters. A low score
     * in a high-criticality domain is where effort pays off most.
     */
    private function expectedImpact(float $score, ?string $domainCode): string
    {
        $headroom = 100.0 - $score;
        $base = $headroom >= 40 ? 'HIGH' : ($headroom >= 20 ? 'MEDIUM' : 'LOW');

        // A high-criticality domain bumps the potential up a notch — fixing it matters more.
        if (DomainRiskProfile::criticality($domainCode) === 'HIGH' && $base !== 'HIGH') {
            return $base === 'LOW' ? 'MEDIUM' : 'HIGH';
        }

        return $base;
    }

    /**
     * Sort weight: bad news and certainty first.
     *
     * @param  array<string, mixed>  $finding
     */
    private function rank(array $finding): int
    {
        return match ($finding['severity']) {
            'HIGH' => 5,
            'MEDIUM' => 4,
            'LOW' => 2,
            'POSITIVE' => 1,
            default => 0, // INFO / data gaps sit at the bottom
        };
    }
}
