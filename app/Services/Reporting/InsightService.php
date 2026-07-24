<?php

namespace App\Services\Reporting;

/**
 * Classifies findings into the governed insight categories a human can act on.
 *
 * An insight is not new information — it is a finding named as what it *means*, using the 21
 * seeded insight categories ([InsightCatalog]). One weak domain can surface as several
 * insights at once: a Weakness, a Low-Performing Area, a Pain Point (it has concrete failing
 * items), a Systemic Issue (many failing together), an Operational Risk (of its domain), and
 * a Strategic Priority (the domain is critical). Naming them lets a report lead with what
 * matters instead of an arbitrary order.
 *
 * Deterministic and pure. Trend-based categories (emerging issues, deterioration, no change)
 * need history and are produced later, from the trend engine.
 */
class InsightService
{
    /** Risk category per measurement domain — a failing domain's risk takes its character. */
    private const DOMAIN_RISK_CATEGORY = [
        'SAFE' => 'CLINICAL_RISK',
        'SERV' => 'OPERATIONAL_RISK',
        'WORK' => 'OPERATIONAL_RISK',
        'RES' => 'OPERATIONAL_RISK',
        'PCOM' => 'OPERATIONAL_RISK',
        'GOV' => 'COMPLIANCE_RISK',
        'INFO' => 'COMPLIANCE_RISK',
        'FIN' => 'COMPLIANCE_RISK',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $findings
     * @return array<string, mixed>
     */
    public function insights(array $findings): array
    {
        $items = [];
        foreach ($findings as $finding) {
            foreach ($this->classify($finding) as $item) {
                $items[] = $item;
            }
        }

        $collection = collect($items);
        $byPolarity = fn (string $p) => $collection->where('polarity', $p)->values()->all();
        $byCategory = fn (string $c) => $collection->where('category_code', $c)->values()->all();

        return [
            'items' => $items,
            'positive' => $byPolarity('POSITIVE'),
            'negative' => $byPolarity('NEGATIVE'),
            'neutral' => $byPolarity('NEUTRAL'),
            // Convenience buckets the report and headline read directly.
            'critical' => $byCategory('CRITICAL_FINDING'),
            'weaknesses' => $byCategory('WEAKNESS'),
            'strengths' => $byCategory('STRENGTH'),
            'priorities' => $byCategory('STRATEGIC_PRIORITY'),
            'quick_wins' => $byCategory('QUICK_WIN'),
            'pain_points' => $byCategory('PAIN_POINT'),
            'systemic_issues' => $byCategory('SYSTEMIC_ISSUE'),
            'data_gaps' => $collection->whereIn('category_code', ['DATA_GAP', 'INSUFFICIENT_EVIDENCE'])->values()->all(),
            'headline' => $this->headline($byCategory('CRITICAL_FINDING'), $byCategory('WEAKNESS'), $byCategory('STRENGTH'),
                $collection->whereIn('category_code', ['DATA_GAP', 'INSUFFICIENT_EVIDENCE'])->values()->all()),
        ];
    }

    /**
     * The insight categories one finding surfaces as.
     *
     * @param  array<string, mixed>  $finding
     * @return array<int, array<string, mixed>>
     */
    private function classify(array $finding): array
    {
        $codes = [];
        $domain = $finding['measurement_domain'] ?? null;
        $score = $finding['score'] ?? null;
        $severity = $finding['severity'] ?? 'INFO';
        $criticalDomain = DomainRiskProfile::criticality($domain) === 'HIGH';

        switch ($finding['category']) {
            case 'CRITICAL_FINDING':
                $codes[] = 'CRITICAL_FINDING';
                if ($domain) {
                    $codes[] = self::DOMAIN_RISK_CATEGORY[$domain] ?? 'OPERATIONAL_RISK';
                }
                break;

            case 'WEAKNESS':
                $codes[] = 'WEAKNESS';
                $codes[] = 'LOW_PERFORMING';
                if (! empty($finding['failed_indicators'])) {
                    $codes[] = 'PAIN_POINT';
                    if (count($finding['failed_indicators']) >= 2) {
                        $codes[] = 'SYSTEMIC_ISSUE';
                    }
                }
                if (in_array($severity, ['HIGH', 'MEDIUM'], true) && $domain) {
                    $codes[] = self::DOMAIN_RISK_CATEGORY[$domain] ?? 'OPERATIONAL_RISK';
                }
                if ($criticalDomain) {
                    $codes[] = 'STRATEGIC_PRIORITY';
                }
                break;

            case 'OPPORTUNITY':
                $codes[] = 'OPPORTUNITY';
                // A moderate score is an easy lift — close enough to strong to be a quick win.
                if ($score !== null && $score >= 55) {
                    $codes[] = 'QUICK_WIN';
                }
                if ($criticalDomain) {
                    $codes[] = 'STRATEGIC_PRIORITY';
                }
                break;

            case 'STRENGTH':
                $codes[] = 'STRENGTH';
                $codes[] = 'HIGH_PERFORMING';
                if ($score !== null && $score >= 85) {
                    $codes[] = 'ACHIEVEMENT';
                    if ($criticalDomain) {
                        $codes[] = 'GOOD_PRACTICE';
                    }
                }
                break;

            case 'DATA_GAP':
                // A domain scored nothing is a Data Gap; a partial score is Insufficient Evidence.
                $codes[] = $score === null ? 'DATA_GAP' : 'INSUFFICIENT_EVIDENCE';
                break;
        }

        return array_map(fn ($code) => [
            'category_code' => $code,
            'category_name' => InsightCatalog::name($code),
            'polarity' => InsightCatalog::polarity($code),
            'subject' => $finding['subject'],
            'measurement_domain' => $domain,
            'severity' => $severity,
            'score' => $score,
            'statement' => $finding['statement'],
        ], array_values(array_unique($codes)));
    }

    /**
     * One honest sentence for the top of a report.
     *
     * @param  array<int, mixed>  $critical
     * @param  array<int, mixed>  $weaknesses
     * @param  array<int, mixed>  $strengths
     * @param  array<int, mixed>  $dataGaps
     */
    private function headline(array $critical, array $weaknesses, array $strengths, array $dataGaps): ?string
    {
        if ($critical !== []) {
            return 'A critical failure was found and needs attention regardless of the overall score.';
        }

        if (count($dataGaps) > 0 && count($weaknesses) + count($strengths) === 0) {
            return 'Too little was answered to draw firm conclusions. Treat the results below as provisional.';
        }

        if (count($weaknesses) >= 3) {
            return 'Several areas are weak, which usually points to a shared underlying cause worth investigating.';
        }

        if ($weaknesses !== []) {
            return 'Most areas are holding up, with a small number needing attention.';
        }

        if ($strengths !== []) {
            return 'A generally strong result, with the areas below performing particularly well.';
        }

        return null;
    }
}
