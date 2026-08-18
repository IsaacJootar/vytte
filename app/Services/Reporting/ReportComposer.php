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
     * Build a user-tailored reading without changing any frozen fact or calculation.
     *
     * @return array<string, mixed>
     */
    public function customView(array $intelligence, string $focus, string $detail, ?string $domain = null): array
    {
        $focus = in_array($focus, ['PRIORITIES', 'RISKS', 'STRENGTHS', 'ALL'], true) ? $focus : 'PRIORITIES';
        $detail = in_array($detail, ['BRIEF', 'STANDARD', 'DETAILED'], true) ? $detail : 'STANDARD';
        $domain = filled($domain) ? strtoupper(trim($domain)) : null;
        $categories = match ($focus) {
            'RISKS' => ['CRITICAL_FINDING', 'WEAKNESS', 'DATA_GAP'],
            'STRENGTHS' => ['STRENGTH', 'OPPORTUNITY'],
            'ALL' => [],
            default => ['CRITICAL_FINDING', 'WEAKNESS', 'OPPORTUNITY'],
        };
        $limit = match ($detail) {
            'BRIEF' => 3,
            'DETAILED' => null,
            default => 8,
        };
        $filter = fn ($item) => ($item['category'] ?? null) === 'CRITICAL_FINDING' || (
            ($domain === null || ($item['measurement_domain'] ?? null) === $domain)
            && ($categories === [] || in_array($item['category'] ?? null, $categories, true))
        );
        $findings = collect($intelligence['findings'] ?? [])->filter($filter)->sortBy(fn ($item) => $this->rank($item))->values();
        $recommendations = collect($intelligence['recommendations'] ?? [])
            ->filter(fn ($item) => $domain === null || ($item['measurement_domain'] ?? null) === $domain)
            ->values();
        if ($limit !== null) {
            $findings = $findings->take($limit);
            $recommendations = $recommendations->take($limit);
        }

        return [
            'lens' => 'CUSTOM',
            'lens_name' => 'Custom report view',
            'lens_question' => str($focus)->lower()->replace('_', ' ')->ucfirst().' · '.str($detail)->lower()->ucfirst(),
            'lead' => $findings->all(),
            'lens_insights' => [],
            'findings' => $intelligence['findings'] ?? [],
            'recommendations' => $recommendations->all(),
            'custom' => ['focus' => $focus, 'detail' => $detail, 'domain' => $domain],
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
            // Operations: what's concretely broken right now — most failing items together first.
            'operational' => $findings->sortBy(fn ($f) => (-count($f['failed_indicators'] ?? [])) * 1000 + $this->rank($f))->values(),
            // Clinical & Quality: patient safety findings lead ahead of service-delivery ones.
            'safety_first' => $findings->sortBy(fn ($f) => ((($f['measurement_domain'] ?? null) === 'SAFE') ? 0 : 1) * 1000 + $this->rank($f))->values(),
            // Risk: real likelihood x impact (RiskService), not raw severity.
            'risk_level' => $findings->sortBy(fn ($f) => (-$this->riskRank($f)) * 1000 + $this->rank($f))->values(),
            // Compliance: undocumented / unevidenced gaps lead — the gap itself is the finding.
            'evidence_gap' => $findings->sortBy(fn ($f) => ((($f['category'] ?? null) === 'DATA_GAP') ? 0 : 1) * 1000 + $this->rank($f))->values(),
            // Programme: where investment would move the result most, not just what scores worst.
            'impact_potential' => $findings->sortBy(fn ($f) => $this->impactRank($f) * 1000 + $this->rank($f))->values(),
            // Default: worst news first.
            default => $findings->sortBy(fn ($f) => $this->rank($f))->values(),
        };
    }

    /**
     * Sortable risk weight for the Risk lens — higher is worse. Findings with no risk level
     * (strengths, opportunities, data gaps) sort after every risk-bearing finding.
     *
     * @param  array<string, mixed>  $finding
     */
    private function riskRank(array $finding): int
    {
        return match ($this->risks->levelFor($finding)) {
            'HIGH' => 3,
            'MEDIUM' => 2,
            'LOW' => 1,
            default => 0,
        };
    }

    /**
     * Sortable improvement-potential weight for the Programme lens — lower sorts first.
     *
     * @param  array<string, mixed>  $finding
     */
    private function impactRank(array $finding): int
    {
        return match ($finding['expected_impact'] ?? null) {
            'HIGH' => 0,
            'MEDIUM' => 1,
            'LOW' => 2,
            default => 3,
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
