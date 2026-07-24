<?php

namespace App\Services\Reporting;

/**
 * Assembles the intelligence for a report, and shapes it for a lens.
 *
 * A report is not a coded type; it is one diagnostic result read through an analysis lens.
 * The engine below runs once — diagnostics, insights, recommendations — and each lens is a
 * different ordering and emphasis of that same output. The Risk lens leads with critical
 * findings; the Performance lens with scores; the Executive lens with the shortest
 * defensible summary. None recomputes anything, which is why one assessment legitimately
 * produces reports that read very differently.
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
     * The analysis lenses a report can be read through, with the emphasis each applies.
     * Kept small for the first reporting release; more of the twenty seeded lenses are
     * wired as their emphasis rules are defined.
     *
     * @return array<string, array{name: string, question: string}>
     */
    public static function lenses(): array
    {
        return [
            'PERFORMANCE' => ['name' => 'Performance', 'question' => 'How well is this performing?'],
            'RISK' => ['name' => 'Risk', 'question' => 'What could go wrong?'],
            'EXECUTIVE' => ['name' => 'Executive summary', 'question' => 'What does leadership need to know?'],
            'COMPLIANCE' => ['name' => 'Compliance', 'question' => 'Where do we fall short of the standard?'],
        ];
    }

    /**
     * Shape the frozen intelligence for one lens: which findings lead, and how they are
     * ordered. The data is identical across lenses; only the emphasis changes.
     *
     * @param  array<string, mixed>  $intelligence
     * @return array{lens: string, lens_name: string, lens_question: string, lead: array<int, mixed>, findings: array<int, mixed>, recommendations: array<int, mixed>}
     */
    public function throughLens(array $intelligence, string $lens): array
    {
        $lens = array_key_exists($lens, self::lenses()) ? $lens : 'PERFORMANCE';
        $findings = collect($intelligence['findings'] ?? []);
        $recommendations = collect($intelligence['recommendations'] ?? []);

        $lead = match ($lens) {
            // Risk leads with anything that could cause harm, ignoring the average.
            'RISK' => $findings->whereIn('category', ['CRITICAL_FINDING', 'WEAKNESS'])
                ->where('severity', 'HIGH')->values(),
            // Compliance leads with what is unmet or unproven.
            'COMPLIANCE' => $findings->whereIn('category', ['CRITICAL_FINDING', 'WEAKNESS', 'DATA_GAP'])->values(),
            // Executive: the single most important item of each kind.
            'EXECUTIVE' => collect([
                $findings->firstWhere('category', 'CRITICAL_FINDING'),
                $findings->firstWhere('category', 'WEAKNESS'),
                $findings->firstWhere('category', 'STRENGTH'),
            ])->filter()->values(),
            // Performance: read by score, best and worst.
            default => $findings->whereNotNull('score')->sortBy('score')->values(),
        };

        $recs = match ($lens) {
            'RISK', 'COMPLIANCE' => $recommendations->where('priority', 'HIGH')->values(),
            'EXECUTIVE' => $recommendations->take(3)->values(),
            default => $recommendations->values(),
        };

        $meta = self::lenses()[$lens];

        return [
            'lens' => $lens,
            'lens_name' => $meta['name'],
            'lens_question' => $meta['question'],
            'lead' => $lead->all(),
            'findings' => $findings->all(),
            'recommendations' => $recs->all(),
        ];
    }
}
