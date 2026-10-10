<?php

namespace App\Services\Reporting;

use App\Models\Assessment;
use App\Services\ReportSnapshotService;
use App\Support\ReportType;

/**
 * Builds the programme-evaluation site report from an assessment's frozen report payload.
 *
 * Pure: it reads only the frozen payload and the report policy frozen into the assessment
 * snapshot, so a report reads identically forever. Sections are read by role (PROVISION: how
 * well needs are met today; READINESS: could the site host a Dignity Bank). The two role scores
 * are the plain average of their section scores. A site is "ready" only when its readiness score
 * clears the threshold AND no must-have rule has fired.
 */
class ProgrammeReportService
{
    /** A score at or above this reads as strong; below WEAK as weak; in between as fair. */
    public const STRONG = 70.0;

    public const WEAK = 45.0;

    public function forAssessment(Assessment $assessment): array
    {
        $assessment->loadMissing(['snapshot', 'reportSnapshot', 'project', 'target.targetType']);
        $payload = app(ReportSnapshotService::class)->payloadFor($assessment);

        $config = ReportType::config($assessment);
        $pooled = null;

        // Several people may answer one assessment. Their combined result keeps only section scores,
        // so the per-question findings and must-have warnings are pooled from the respondents it counted.
        if ($payload['respondent_collection']['is_multi_respondent'] ?? false) {
            $roles = $config['roles'] ?? [];
            $sectionByDomain = collect($payload['sub_index_scores'] ?? [])->pluck('full_name', 'domain_code')->all();
            $pooled = app(ProgrammeRespondentPoolService::class)->forAssessment($assessment, $roles, $sectionByDomain);
        }

        return $this->build($payload, $config, $pooled);
    }

    /**
     * @param  array<string, mixed>  $payload  the frozen report payload
     * @param  array<string, mixed>  $config  the `report` policy frozen with the template
     * @param  array<string, mixed>|null  $pooled  findings pooled across several respondents, when more than one answered
     * @return array<string, mixed>
     */
    public function build(array $payload, array $config, ?array $pooled = null): array
    {
        $roles = $config['roles'] ?? [];
        $thresholds = $config['thresholds'] ?? [];

        $sections = collect($payload['sub_index_scores'] ?? [])
            ->map(fn ($section) => [
                'name' => $section['full_name'] ?? 'Section',
                'domain_code' => $section['domain_code'] ?? null,
                'role' => $roles[$section['domain_code'] ?? ''] ?? null,
                'score' => isset($section['score']) ? (float) $section['score'] : null,
            ])
            ->filter(fn ($section) => $section['role'] !== null)
            ->map(fn ($section) => $section + ['band' => $this->band($section['score'])])
            ->values();

        $provision = $this->mean($sections->where('role', 'PROVISION'));
        $readiness = $this->mean($sections->where('role', 'READINESS'));
        $flags = $pooled['flags'] ?? array_values(array_filter((array) ($payload['score']['critical_findings'] ?? []), 'is_string'));

        $goodAt = (float) ($thresholds['provision_good_at_or_above'] ?? 60);
        $readyAt = (float) ($thresholds['readiness_ready_at_or_above'] ?? 60);
        $highNeed = $provision !== null ? $provision < $goodAt : null;
        $ready = $readiness !== null ? ($readiness >= $readyAt && $flags === []) : null;

        $classification = null;
        if ($highNeed !== null && $ready !== null) {
            $key = ($highNeed ? 'HIGH_NEED' : 'LOWER_NEED').'_'.($ready ? 'READY' : 'NOT_READY');
            $definition = $config['classification'][$key] ?? ['label' => $key, 'detail' => ''];
            $classification = ['key' => $key] + $definition;
        }

        $sectionByDomain = $sections->pluck('name', 'domain_code')->all();
        $items = $pooled['items'] ?? $this->scoredItems($payload, $roles, $sectionByDomain);

        $gapCount = (int) ($config['gap_count'] ?? 5);
        $gaps = $items->where('role', 'PROVISION')->filter(fn ($item) => $item['score'] < self::STRONG)
            ->sortBy('score')->take($gapCount)->values()->all();
        $readinessGaps = $items->where('role', 'READINESS')->filter(fn ($item) => $item['score'] < self::STRONG)
            ->sortBy('score')->take(4)->values()->all();
        $strengths = $items->filter(fn ($item) => $item['score'] >= 100)
            ->groupBy('section')->map(fn ($group) => $group->first())->take(4)->values()->all();
        $allGaps = $items->filter(fn ($item) => $item['score'] < self::STRONG)->pluck('question')->values()->all();

        $target = $payload['target'] ?? [];

        return [
            'programme' => $config['programme'] ?? 'Programme evaluation',
            'programme_slug' => \Illuminate\Support\Str::slug($config['programme'] ?? 'Programme evaluation'),
            'noun' => $config['site_noun'] ?? 'site',
            'site' => [
                'name' => $target['name'] ?? ($payload['title'] ?? 'Site'),
                'type' => $target['type'] ?? null,
                'area_council' => $target['sub_region'] ?? null,
                'state' => $target['region'] ?? null,
                'project' => $payload['project']['name'] ?? null,
                'assessor' => $payload['assessor_name'] ?? null,
                'assessed_on' => $payload['completed_at'] ?? null,
            ],
            'provision' => [
                'label' => $config['role_labels']['PROVISION'] ?? 'Provision',
                'score' => $provision,
                'band' => $this->band($provision),
            ],
            'readiness' => [
                'label' => $config['role_labels']['READINESS'] ?? 'Readiness',
                'score' => $readiness,
                'band' => $this->band($readiness),
            ],
            'classification' => $classification,
            'flags' => $flags,
            'sections' => $sections->all(),
            'gaps' => $gaps,
            'readiness_gaps' => $readinessGaps,
            'strengths' => $strengths,
            'all_gaps' => $allGaps,
            'context' => $pooled['context'] ?? $this->context($payload),
            'thresholds' => ['provision_good_at_or_above' => $goodAt, 'readiness_ready_at_or_above' => $readyAt],
        ];
    }

    /** Every scored answer with its section role, worst first within each role. */
    private function scoredItems(array $payload, array $roles, array $sectionByDomain)
    {
        return collect($payload['domain_scores'] ?? [])
            ->filter(fn ($domain) => isset($roles[$domain['domain_code'] ?? '']))
            ->flatMap(fn ($domain) => collect($domain['question_breakdown'] ?? [])->map(fn ($item) => [
                'question' => $item['question_text'] ?? 'Question',
                'answer' => $item['answer'] ?? null,
                'score' => (float) ($item['score'] ?? 0),
                'role' => $roles[$domain['domain_code']],
                'section' => $sectionByDomain[$domain['domain_code']] ?? ($domain['domain_name'] ?? 'Section'),
            ]))
            ->values();
    }

    /** Unscored profile/programme answers, grouped by section as they appeared in the report. */
    private function context(array $payload): array
    {
        return collect($payload['measurement_views']['context']['items'] ?? [])
            ->groupBy(fn ($item) => $item['section_name'] ?? 'Context')
            ->map(fn ($group, $section) => [
                'section' => $section,
                'items' => $group->map(fn ($item) => [
                    'question' => $item['question_text'],
                    // Numbers are stored as decimals ("640.0000 students"); show them as people read them.
                    'answer' => preg_replace('/(\d)\.0+(?=\s|$)/', '$1', (string) $item['answer']),
                ])->values()->all(),
            ])->values()->all();
    }

    private function mean($sections): ?float
    {
        $scores = collect($sections)->pluck('score')->filter(fn ($score) => $score !== null);

        return $scores->isEmpty() ? null : round($scores->avg(), 1);
    }

    public function band(?float $score): string
    {
        return match (true) {
            $score === null => 'none',
            $score >= self::STRONG => 'strong',
            $score >= self::WEAK => 'fair',
            default => 'weak',
        };
    }
}
