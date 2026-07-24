<?php

namespace App\Services\Reporting;

/**
 * A curated library of interventions, keyed by measurement domain and severity.
 *
 * This is what stops recommendations being generic. Instead of "strengthen governance", the
 * engine reaches into a governed library for the specific lever that moves that domain —
 * different advice for a domain in crisis than for one merely lagging. Kept as a pure
 * constant (like [DomainRiskProfile] and [InsightCatalog]) so recommendations stay
 * deterministic and frozen-snapshot-safe; it can graduate to a seeded table later without
 * changing the recommendation engine.
 */
class InterventionLibrary
{
    /**
     * domain => [severe (score in crisis), lagging (weak but not critical)].
     *
     * @var array<string, array{severe: string, lagging: string}>
     */
    private const INTERVENTIONS = [
        'GOV' => [
            'severe' => 'Stand up basic governance first: name who is accountable for what, hold a regular leadership meeting, and record decisions and actions so nothing is dropped.',
            'lagging' => 'Tighten governance: make decision-making and accountability visible, and review the actions agreed at each leadership meeting.',
        ],
        'WORK' => [
            'severe' => 'Stabilise the workforce: map the critical gaps in numbers and skills, cover the most dangerous shortfalls first, and put a simple rota and supervision routine in place.',
            'lagging' => 'Strengthen the workforce: close the priority skill gaps with targeted training and regular supervision, and ease the heaviest workloads.',
        ],
        'SERV' => [
            'severe' => 'Restore core service delivery: identify where patients are being turned away or waiting unsafely and fix those points first, with clear steps for each service.',
            'lagging' => 'Improve service delivery: standardise the main care processes and remove the bottlenecks that slow patients down.',
        ],
        'SAFE' => [
            'severe' => 'Treat safety as urgent: put the essential safety practices (infection control, safe medication, incident reporting) in place immediately and check they are followed.',
            'lagging' => 'Reinforce safety: audit the key safety practices, close the gaps, and make incident reporting routine so problems surface early.',
        ],
        'RES' => [
            'severe' => 'Secure essential supplies and equipment: fix the stock-outs and critical equipment failures that are stopping care now, and set a minimum re-order level.',
            'lagging' => 'Improve supply reliability: track stock against re-order levels and schedule preventive maintenance so gaps do not recur.',
        ],
        'INFO' => [
            'severe' => 'Make the records trustworthy: agree what must be recorded, by whom and when, and stop relying on numbers that cannot be verified.',
            'lagging' => 'Improve information quality: tighten how data is captured and check it regularly so reporting and planning rest on solid figures.',
        ],
        'FIN' => [
            'severe' => 'Get spending under control: track where money goes, stop untracked spending, and produce a simple monthly picture of income and cost.',
            'lagging' => 'Strengthen financial management: budget against actuals monthly and justify major spend so resources can be planned and sustained.',
        ],
        'PCOM' => [
            'severe' => 'Rebuild trust with patients and community: create an easy way for people to raise concerns and be seen to act on them.',
            'lagging' => 'Improve patient and community experience: gather feedback routinely and close the loop on the issues raised.',
        ],
    ];

    /**
     * The most fitting intervention for a domain at a given severity.
     */
    public static function forDomain(?string $domainCode, string $severity): ?string
    {
        $entry = self::INTERVENTIONS[$domainCode] ?? null;
        if ($entry === null) {
            return null;
        }

        return $severity === 'HIGH' ? $entry['severe'] : $entry['lagging'];
    }
}
