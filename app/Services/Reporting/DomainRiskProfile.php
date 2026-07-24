<?php

namespace App\Services\Reporting;

/**
 * The risk character of each measurement domain.
 *
 * Two things the score alone cannot tell you: how much it matters when a domain is weak
 * (criticality → impact), and what concretely happens if it is left weak (consequence).
 * Both are properties of the domain, not the number, so they live here as governed reference
 * data rather than being invented per report. Deterministic and auditable.
 */
class DomainRiskProfile
{
    /**
     * Impact if this domain is failing. HIGH domains put patients, compliance, or the
     * facility's ability to function at stake; LOW domains degrade quality without immediate
     * danger.
     *
     * @var array<string, string>
     */
    private const CRITICALITY = [
        'SAFE' => 'HIGH',   // patient & staff safety
        'SERV' => 'HIGH',   // service delivery — the core function
        'GOV' => 'HIGH',    // governance & leadership
        'WORK' => 'MEDIUM', // workforce
        'RES' => 'MEDIUM',  // infrastructure & supplies
        'INFO' => 'MEDIUM', // information & records
        'FIN' => 'MEDIUM',  // financing
        'PCOM' => 'LOW',    // community & patient experience
    ];

    /**
     * What happens if this domain is left weak — the answer to "what happens if nothing
     * changes?" Stated plainly, in domain terms.
     *
     * @var array<string, string>
     */
    private const CONSEQUENCE = [
        'SAFE' => 'the risk of avoidable harm to patients and staff keeps rising, and a serious incident becomes more likely over time.',
        'SERV' => 'services stay unreliable — patients wait longer, are turned away, or receive inconsistent care, and demand quietly shifts elsewhere.',
        'GOV' => 'decisions stay slow and unaccountable, problems recur because no one owns them, and every other area is harder to fix.',
        'WORK' => 'staff stay overstretched and undersupported, mistakes and turnover climb, and the workload lands on fewer, more tired people.',
        'RES' => 'stock-outs and broken equipment keep interrupting care, and the facility keeps paying more to work around gaps.',
        'INFO' => 'records stay unreliable, so reporting, follow-up, and planning all rest on numbers that cannot be trusted.',
        'FIN' => 'money keeps leaking through untracked spending, and the facility cannot plan, justify, or sustain its work.',
        'PCOM' => 'patients feel unheard and trust erodes, which shows up later as missed appointments, complaints, and lost goodwill.',
    ];

    public static function criticality(?string $domainCode): string
    {
        return self::CRITICALITY[$domainCode] ?? 'MEDIUM';
    }

    public static function consequence(?string $domainCode, string $subject): string
    {
        $tail = self::CONSEQUENCE[$domainCode] ?? 'the gap persists and compounds, making it harder and more costly to close later.';

        return 'If '.$subject.' is left as it is, '.$tail;
    }
}
