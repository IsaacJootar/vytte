<?php

namespace App\Services\Reporting;

use App\Models\Assessment;
use App\Models\PublicResponseSession;
use App\Services\ReportSnapshotService;
use App\Support\ReportType;
use Illuminate\Support\Carbon;

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

    public function forAssessment(Assessment $assessment, bool $withOutcomes = true): array
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

        $codeById = collect((array) $assessment->snapshot?->payload)
            ->flatMap(fn ($module) => $module['questions'] ?? [])
            ->pluck('question_code', 'question_id')
            ->all();
        $report = $this->build($payload, $config, $pooled, $codeById);
        $report['profile'] = $this->profile($report['context'], $config['profile'] ?? [], $assessment);
        // What the girls and women themselves reported, when the template declares indicators for it.
        $report['outcomes'] = $withOutcomes && ! empty($config['indicators'])
            ? app(ProgrammeOutcomeService::class)->forAssessment($assessment)
            : null;
        $report['must_haves'] = $this->mustHaves($assessment, $report['flags'], (array) ($config['findings'] ?? []), $codeById);
        $report['next_steps'] = (array) ($config['next_steps'][$report['classification']['key'] ?? ''] ?? []);
        $report['summary'] = $this->summary($report);
        $report['fieldwork'] = $this->fieldwork($assessment);
        $this->useCurrentPlaceDetails($report, $assessment);
        $report['actions'] = app(ProgrammeActionPlan::class)->forFindings(
            $report['weak_items'],
            collect($report['must_haves'])->where('missing', true)->pluck('code')->filter()->values()->all(),
            $report['noun'],
        );

        return $report;
    }

    /**
     * The place's name and area council as they are now, not as they were typed when the report was made.
     * A misspelt name or a missing area council can then be corrected on the place without redoing anything:
     * these are descriptive details, not results, so no score or finding is affected.
     *
     * @param  array<string, mixed>  $report
     */
    private function useCurrentPlaceDetails(array &$report, Assessment $assessment): void
    {
        $place = $assessment->target;
        if (! $place) {
            return;
        }
        if (filled($place->name)) {
            $report['site']['name'] = $place->name;
        }
        if (filled($place->sub_region)) {
            $report['site']['area_council'] = $place->sub_region;
        }
    }

    /**
     * When the answers were collected: the first and last day an eligible person finished. A report is
     * often created days after the fieldwork, so its own completion date would say the wrong thing.
     *
     * @return array{from: ?string, to: ?string}
     */
    private function fieldwork(Assessment $assessment): array
    {
        $sessionIds = collect((array) $assessment->aggregationResult?->payload['eligible_session_references'] ?? [])->pluck('session_id')->filter()->all();

        $query = PublicResponseSession::where('assessment_id', $assessment->assessment_id)->whereNotNull('submitted_at');
        if ($sessionIds !== []) {
            $query->whereIn('session_id', $sessionIds);
        }

        $from = $query->min('submitted_at');
        $to = $query->max('submitted_at');

        return [
            'from' => $from ? Carbon::parse($from)->toDateString() : null,
            'to' => $to ? Carbon::parse($to)->toDateString() : null,
        ];
    }

    /**
     * Every must-have the template checks, each marked missing or in place, so the report can show the
     * whole checklist rather than only the problems.
     *
     * @param  array<int, string>  $flags
     * @param  array<string, array{gap: string, good: string}>  $findings
     * @param  array<string, string>  $codeById
     * @return array<int, array{label: string, missing: bool, ok: string}>
     */
    private function mustHaves(Assessment $assessment, array $flags, array $findings, array $codeById): array
    {
        return collect((array) ($assessment->snapshot?->aggregation_policy['compound_critical_rules'] ?? []))
            ->filter(fn (array $rule) => ! empty($rule['label']))
            ->map(function (array $rule) use ($flags, $findings, $codeById): array {
                $code = $codeById[$rule['conditions'][0]['source_question_id'] ?? ''] ?? '';

                return [
                    'code' => $this->codeSuffix($code),
                    'label' => $rule['label'],
                    'missing' => in_array($rule['label'], $flags, true),
                    // How the same must-have reads when it is in place.
                    'ok' => $findings[$code]['good'] ?? $rule['label'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The few plain sentences that open the report: where this place stands, whether it could host a
     * Dignity Bank, and (when girls and women answered) the things they reported that worry most.
     *
     * @param  array<string, mixed>  $report
     * @return array<int, string>
     */
    private function summary(array $report): array
    {
        $lines = [];
        if ($report['classification']) {
            $lines[] = $report['classification']['detail'];
        }

        $provision = $report['provision']['score'];
        if ($provision !== null) {
            $state = ['strong' => 'well met', 'fair' => 'partly met'][$report['provision']['band']] ?? 'not well met';
            $lines[] = "Girls' needs are $state today (".round($provision).' out of 100).';
        }

        $flags = $report['flags'];
        if ($flags !== []) {
            $lines[] = count($flags) === 1
                ? 'One must-have is missing before a Dignity Bank can start.'
                : count($flags).' must-haves are missing before a Dignity Bank can start.';
        } elseif ($report['readiness']['score'] !== null) {
            $lines[] = 'Every must-have for a Dignity Bank is in place (readiness '.round($report['readiness']['score']).' out of 100).';
        }

        $outcomes = $report['outcomes'] ?? null;
        if ($outcomes) {
            if (! $outcomes['enough']) {
                $lines[] = 'Fewer than '.$outcomes['minimum'].' girls and women answered, so what they reported is a rough guide.';
            } else {
                $rows = collect($outcomes['outcomes'])->flatMap(fn ($group) => $group['indicators'])->filter(fn ($row) => $row['value'] !== null);
                $worst = $rows
                    ->map(fn ($row) => $row + ['concern' => $row['direction'] === 'DOWN' ? $row['value'] : 100 - $row['value']])
                    ->filter(fn ($row) => $row['concern'] >= 40)
                    ->sortByDesc('concern')
                    ->take(2);
                foreach ($worst as $row) {
                    $figure = round($row['value']).'%';
                    $lines[] = $row['kind'] === 'KNOWLEDGE'
                        ? 'The average knowledge score was '.$figure.'.'
                        : ($row['direction'] === 'DOWN' ? '' : 'Only ').$figure.' of the girls and women '.lcfirst($row['label']).'.';
                }
            }
        }

        return $lines;
    }

    /**
     * The profile answers as short lines ("Public junior secondary school", "640 students · 310 girls"),
     * never as the question and its answer. Each line comes from a template in the frozen report
     * policy and is left out when any answer it needs is missing.
     *
     * @param  array<int, array<string, mixed>>  $context
     * @param  array<int, string>  $templates
     * @return array<int, string>
     */
    private function profile(array $context, array $templates, Assessment $assessment): array
    {
        $codeByText = collect((array) $assessment->snapshot?->payload)
            ->flatMap(fn ($module) => $module['questions'] ?? [])
            ->mapWithKeys(fn ($question) => [$question['question_text'] => $question['question_code']])
            ->all();
        $answers = [];
        foreach ($context as $group) {
            foreach ($group['items'] as $item) {
                if (isset($codeByText[$item['question']])) {
                    $answers[$codeByText[$item['question']]] = $item['answer'];
                }
            }
        }

        $lines = [];
        foreach ($templates as $template) {
            $complete = true;
            $line = preg_replace_callback('/\{([\w.]+)\}/', function ($match) use ($answers, &$complete) {
                $value = $answers[$match[1]] ?? null;
                if ($value === null || $value === '' || $value === "Don't know") {
                    $complete = false;

                    return '';
                }

                return $this->tidyList((string) $value);
            }, $template);
            if ($complete) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $payload  the frozen report payload
     * @param  array<string, mixed>  $config  the `report` policy frozen with the template
     * @param  array<string, mixed>|null  $pooled  findings pooled across several respondents, when more than one answered
     * @param  array<string, string>  $codeById  question code by question id, to find each question's written finding
     * @return array<string, mixed>
     */
    public function build(array $payload, array $config, ?array $pooled = null, array $codeById = []): array
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
        $findings = $config['findings'] ?? [];
        // Each item carries the template's own written sentence for it; the question's wording is only a
        // fallback for a template that wrote none.
        $items = ($pooled['items'] ?? $this->scoredItems($payload, $roles, $sectionByDomain))
            ->map(function (array $item) use ($findings, $codeById) {
                $written = $findings[$codeById[$item['question_id'] ?? ''] ?? ''] ?? null;

                return $item + ['gap' => $written['gap'] ?? $item['question'], 'good' => $written['good'] ?? $item['question']];
            });

        $gapCount = (int) ($config['gap_count'] ?? 5);
        // A problem already named under "must fix" is not repeated in the lists below it.
        $notAMustHave = fn (array $item) => ! in_array($item['gap'], $flags, true);
        $gaps = $items->where('role', 'PROVISION')->filter(fn ($item) => $item['score'] < self::STRONG && $notAMustHave($item))
            ->sortBy('score')->take($gapCount)->values()->all();
        $readinessGaps = $items->where('role', 'READINESS')->filter(fn ($item) => $item['score'] < self::STRONG && $notAMustHave($item))
            ->sortBy('score')->take(4)->values()->all();
        $strengths = $items->filter(fn ($item) => $item['score'] >= 100)
            ->groupBy('section')->map(fn ($group) => $group->first())->take(4)->values()->all();
        $allGaps = $items->filter(fn ($item) => $item['score'] < self::STRONG)->pluck('gap')->values()->all();
        // Every weak answer with its question code's suffix (B1, D6, F3 ...), for the action plan and the portfolio.
        $weakItems = $items->filter(fn ($item) => $item['score'] < self::STRONG)
            ->map(fn ($item) => [
                'code' => $this->codeSuffix($codeById[$item['question_id'] ?? ''] ?? ''),
                'score' => (float) $item['score'],
                'role' => $item['role'] ?? null,
                'gap' => $item['gap'],
            ])
            ->filter(fn ($item) => $item['code'] !== '')
            ->values()->all();

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
            'weak_items' => $weakItems,
            'all_gap_codes' => collect($weakItems)->pluck('code')->unique()->values()->all(),
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
                'question_id' => $item['question_id'] ?? null,
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

    /**
     * When several people tick boxes their answers are combined, so a list can say "Antenatal care, Family
     * planning, None of these". "None of these" and "Don't know" mean nothing next to a real answer, so they
     * are dropped unless they are all there is.
     */
    private function tidyList(string $value): string
    {
        $parts = array_map('trim', explode(', ', $value));
        $real = array_values(array_filter($parts, fn (string $part) => ! in_array($part, ['None of these', "Don't know"], true)));

        return ($real !== [] && count($real) < count($parts)) ? implode(', ', $real) : $value;
    }

    /** "MHP.D6" becomes "D6": the part of a question code that is the same in the School and PHC templates. */
    private function codeSuffix(string $code): string
    {
        $dot = strrpos($code, '.');

        return $dot === false ? $code : substr($code, $dot + 1);
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
