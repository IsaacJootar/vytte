<?php

namespace App\Services\Reporting;

use Illuminate\Support\Collection;

/**
 * Assembles the intelligence for a report, and shapes it for a lens.
 *
 * A report is not a coded type; it is one diagnostic result read through an analysis lens.
 * The engine below runs once — diagnostics, insights, recommendations, root causes, risks —
 * and each lens reinterprets that same output: which domains it foregrounds, which insight
 * categories it leads with, how it orders findings ([LensCatalog]). The Risk lens leads with
 * what could go wrong across every domain; the Clinical lens ignores financing; the Value
 * lens leads with strengths and quick wins. None recomputes anything, which is why one
 * assessment legitimately produces reports that read very differently.
 */
class ReportComposer
{
    public function __construct(
        private readonly DiagnosticsService $diagnostics,
        private readonly InsightService $insights,
        private readonly RecommendationService $recommendations,
        private readonly RootCauseService $rootCauses,
        private readonly RiskService $risks,
    ) {}

    /**
     * The full intelligence, computed once and frozen into the report snapshot payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function intelligence(array $payload): array
    {
        $findings = $this->diagnostics->findings($payload);

        return [
            'findings' => $findings,
            'insights' => $this->insights->insights($findings),
            'recommendations' => $this->recommendations->recommendations($findings),
            'root_causes' => $this->rootCauses->rootCauses($findings),
            'risks' => $this->risks->risks($findings),
            'generated_at' => now()->toIso8601String(),
            'engine_version' => 'vytte-reporting-2.0',
        ];
    }

    /**
     * The governed lenses a report can be read through.
     *
     * @return array<string, array{name: string, question: string}>
     */
    public static function lenses(): array
    {
        return LensCatalog::options();
    }

    /**
     * Read the frozen intelligence through one lens — a genuinely different report, not a
     * re-sort. The lens decides which domains are foregrounded, which insight categories it
     * leads with, and how findings are ordered. A critical failure is always surfaced,
     * whatever the lens, because it outranks any framing.
     *
     * @param  array<string, mixed>  $intelligence
     * @return array{lens: string, lens_name: string, lens_question: string, lead: array<int, mixed>, lens_insights: array<int, mixed>, findings: array<int, mixed>, recommendations: array<int, mixed>}
     */
    public function throughLens(array $intelligence, string $lens): array
    {
        $lens = LensCatalog::resolve($lens);
        $def = LensCatalog::LENSES[$lens];
        $findings = collect($intelligence['findings'] ?? []);
        $recommendations = collect($intelligence['recommendations'] ?? []);
        $insights = collect($intelligence['insights']['items'] ?? []);

        // Foreground the domains this lens cares about (all, if it declares none), but never
        // hide a critical failure.
        $inScope = fn ($item) => $def['domains'] === []
            || in_array($item['measurement_domain'] ?? null, $def['domains'], true)
            || ($item['category'] ?? null) === 'CRITICAL_FINDING';

        $scoped = $findings->filter($inScope)->values();
        $lead = $this->emphasise($scoped, $def['emphasis']);

        // The insight categories this lens leads with — different lenses, different meaning.
        $lensInsights = $insights
            ->whereIn('category_code', $def['categories'])
            ->when($def['domains'] !== [], fn ($c) => $c->filter(fn ($i) => in_array($i['measurement_domain'] ?? null, $def['domains'], true) || $i['category_code'] === 'CRITICAL_FINDING'))
            ->unique(fn ($i) => $i['category_code'].'|'.$i['subject'])
            ->values();

        // Recommendations relevant to the lens's domains (all, for the whole-picture lenses).
        $recs = $recommendations
            ->filter(fn ($r) => $def['domains'] === [] || in_array($r['measurement_domain'] ?? null, $def['domains'], true))
            ->when($def['emphasis'] === 'executive', fn ($c) => $c->take(3))
            ->values();

        return [
            'lens' => $lens,
            'lens_name' => $def['name'],
            'lens_question' => $def['question'],
            'lead' => $lead->all(),
            'lens_insights' => $lensInsights->all(),
            'findings' => $findings->all(),
            'recommendations' => $recs->all(),
        ];
    }

    /**
     * Order the foregrounded findings the way this lens reads them.
     *
     * @param  Collection<int, array<string, mixed>>  $findings
     * @return Collection<int, array<string, mixed>>
     */
    private function emphasise($findings, string $emphasis)
    {
        return match ($emphasis) {
            // The single most important item of each kind — for leadership.
            'executive' => collect([
                $findings->firstWhere('category', 'CRITICAL_FINDING'),
                $findings->firstWhere('category', 'WEAKNESS'),
                $findings->firstWhere('category', 'STRENGTH'),
            ])->filter()->values(),
            // Strengths and easy wins first — for a value/investment reading.
            'positive' => $findings->sortByDesc(fn ($f) => $this->rank($f))->values(),
            // Default: worst news first.
            default => $findings->sortBy(fn ($f) => $this->rank($f))->values(),
        };
    }

    /**
     * A sortable weight: lower is worse. Weakness/critical sink to the top under the default
     * (worst-first) emphasis and to the bottom under the positive emphasis.
     *
     * @param  array<string, mixed>  $finding
     */
    private function rank(array $finding): int
    {
        return match ($finding['category'] ?? '') {
            'CRITICAL_FINDING' => 0,
            'WEAKNESS' => ($finding['severity'] ?? '') === 'HIGH' ? 1 : 2,
            'DATA_GAP' => 3,
            'OPPORTUNITY' => 4,
            'STRENGTH' => 5,
            default => 3,
        };
    }
}
