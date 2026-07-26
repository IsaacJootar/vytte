<?php

namespace App\Services\Reporting;

use App\Models\Assessment;
use App\Models\AssessmentAction;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Harmonises a whole workspace's assessments into one program-level picture.
 *
 * This is portfolio harmonisation: it reads only completed assessments and their frozen
 * scores — never recomputing them — and rolls them up into program averages, rankings, a
 * domain heatmap, a trajectory and coverage. The official, comparable score always lives at
 * the assessment level; everything here is the management indicator that sits on top.
 *
 * Read-only and tenant-scoped: it reads the workspace-scoped Project and AssessmentAction
 * models, so isolation is enforced by their global scopes, not by this service.
 */
class PortfolioService
{
    /** Maturity bands, mirroring the maturity_levels table (0-20 … 80-100). */
    private const MATURITY = [
        1 => 'Data Collection',
        2 => 'Data Reporting',
        3 => 'Data Analysis',
        4 => 'Data-Driven Management',
        5 => 'Learning Health System',
    ];

    public function __construct(private readonly BenchmarkService $benchmarks) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $league = $this->benchmarks->facilityComparison();
        $average = $this->benchmarks->workspaceAverage();
        $domainProgram = collect($this->benchmarks->domainComparison())
            ->sortBy('average')->values()->all();

        return [
            'headline' => $this->headline($league, $average),
            'league' => $league,
            'domain_program' => $domainProgram,
            'where_to_act' => $this->whereToAct($domainProgram),
            'trend' => $this->trend(),
            'heatmap' => $this->heatmap(),
            'coverage' => $this->coverage(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $league
     * @return array<string, mixed>
     */
    private function headline(array $league, ?float $average): array
    {
        $scores = collect($league)->pluck('score')->filter();
        $maturity = [];
        foreach (self::MATURITY as $level => $name) {
            $maturity[$level] = ['name' => $name, 'count' => 0];
        }
        foreach ($scores as $s) {
            $level = min(5, (int) floor($s / 20) + 1);
            $maturity[$level]['count']++;
        }

        return [
            'program_average' => $average,
            'assessed_targets' => $scores->count(),
            'total_targets' => Project::count(),
            'completed_assessments' => Assessment::where('status', Assessment::STATUS_COMPLETE)->count(),
            'maturity' => $maturity,
        ];
    }

    /**
     * The decision section: the areas weakest across the whole program, and how much
     * follow-through is outstanding.
     *
     * @param  array<int, array<string, mixed>>  $domainProgram
     * @return array<string, mixed>
     */
    private function whereToAct(array $domainProgram): array
    {
        $weakest = collect($domainProgram)
            ->filter(fn ($d) => $d['average'] < 60)
            ->take(4)
            ->values()
            ->all();

        $openActions = AssessmentAction::whereIn('status', [AssessmentAction::STATUS_OPEN, AssessmentAction::STATUS_IN_PROGRESS])->count();
        $overdueActions = AssessmentAction::whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->whereNotIn('status', [AssessmentAction::STATUS_DONE, AssessmentAction::STATUS_VERIFIED])
            ->count();

        return [
            'weakest_areas' => $weakest,
            'open_actions' => $openActions,
            'overdue_actions' => $overdueActions,
        ];
    }

    /**
     * The program's trajectory: the average overall score of every completed assessment,
     * grouped by the month it was completed.
     *
     * @return array<int, array{label: string, value: float}>
     */
    private function trend(): array
    {
        $completedIds = Assessment::where('status', Assessment::STATUS_COMPLETE)
            ->whereNotNull('completed_at')
            ->pluck('assessment_id');

        if ($completedIds->isEmpty()) {
            return [];
        }

        return DB::table('assessments as a')
            ->join('assessment_scores as s', 's.assessment_id', '=', 'a.assessment_id')
            ->whereIn('a.assessment_id', $completedIds->all())
            ->whereNotNull('s.overall_score')
            ->get(['a.completed_at', 's.overall_score'])
            ->groupBy(fn ($row) => Carbon::parse($row->completed_at)->format('Y-m'))
            ->map(fn ($rows, $key) => [
                'label' => Carbon::createFromFormat('Y-m', $key)->format('M Y'),
                'value' => round($rows->avg('overall_score'), 1),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    /**
     * A targets x areas grid: each target's latest domain scores, so it is instantly clear
     * which areas are strong or weak across which targets.
     *
     * @return array{domains: array<int, array<string, mixed>>, rows: array<int, array<string, mixed>>}
     */
    private function heatmap(): array
    {
        $projects = Project::with(['assessments' => fn ($q) => $q->where('status', Assessment::STATUS_COMPLETE)->orderByDesc('completed_at')])->get();
        $latest = $projects->mapWithKeys(fn ($p) => [$p->project_id => $p->assessments->first()])->filter();

        if ($latest->isEmpty()) {
            return ['domains' => [], 'rows' => []];
        }

        $scores = DB::table('domain_scores as ds')
            ->join('domains as d', 'd.domain_id', '=', 'ds.domain_id')
            ->whereIn('ds.assessment_id', $latest->pluck('assessment_id')->all())
            ->whereNotNull('ds.score')
            ->orderBy('d.display_order')
            ->get(['ds.assessment_id', 'd.domain_code', 'd.domain_name', 'ds.score']);

        $domains = $scores->unique('domain_code')
            ->map(fn ($r) => ['code' => $r->domain_code, 'name' => $r->domain_name])
            ->values()->all();

        $byAssessment = $scores->groupBy('assessment_id');

        $rows = $latest->map(function ($assessment, $projectId) use ($projects, $byAssessment) {
            $project = $projects->firstWhere('project_id', $projectId);
            $cells = collect($byAssessment->get($assessment->assessment_id, collect()))
                ->mapWithKeys(fn ($r) => [$r->domain_code => round((float) $r->score)]);

            return [
                'target' => $project?->name,
                'cells' => $cells->all(),
            ];
        })->values()->all();

        return ['domains' => $domains, 'rows' => $rows];
    }

    /**
     * What the portfolio covers — target types and countries assessed.
     *
     * @return array<string, mixed>
     */
    private function coverage(): array
    {
        $targets = DB::table('projects as p')
            ->join('project_targets as pt', 'pt.project_id', '=', 'p.project_id')
            ->join('targets as t', 't.target_id', '=', 'pt.target_id')
            ->get(['t.target_type_code', 't.country']);

        return [
            'by_type' => $targets->groupBy('target_type_code')->map->count()->sortDesc()->all(),
            'by_country' => $targets->whereNotNull('country')->groupBy('country')->map->count()->sortDesc()->all(),
        ];
    }
}
