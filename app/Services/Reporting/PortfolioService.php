<?php

namespace App\Services\Reporting;

use App\Models\Assessment;
use App\Models\AssessmentAction;
use App\Models\Project;
use App\Models\ReportSchedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The workspace's long-term management view: coverage, progress, domain trends, issue
 * movement, safe comparisons, and action follow-through, across every setting at once.
 *
 * This is a rollup that links into Project Progress for detail — it must not reimplement the
 * comparison or issue-tracking rules Project Progress already enforces. Every calculation here
 * reuses ComparisonSeriesService, TrendService, and IssueTrackingService, the same services
 * Project Progress itself calls, so a portfolio claim is always consistent with what a user
 * sees after drilling into one setting.
 */
class PortfolioService
{
    public function __construct(
        private readonly ComparisonSeriesService $series,
        private readonly TrendService $trends,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $projects = Project::with([
            'assessments' => fn ($query) => $query
                ->where('status', Assessment::STATUS_COMPLETE)
                ->with(['score.maturityLevel', 'snapshot', 'reportSnapshot', 'catalogueRelease', 'target'])
                ->orderByDesc('completed_at'),
        ])->get();

        $latest = $projects->map(function (Project $project): ?array {
            $assessment = $project->assessments->first();
            $score = $assessment?->score?->overall_score;

            if ($assessment === null || $score === null) {
                return null;
            }

            return [
                'project_id' => $project->project_id,
                'setting_name' => $assessment->target?->name ?? $project->name,
                'assessment_id' => $assessment->assessment_id,
                'assessment_name' => $assessment->catalogueRelease?->release_name ?? 'Assessment',
                'assessment_version' => $assessment->catalogueRelease?->release_code,
                'comparison_signature' => $this->series->signatureFor($assessment),
                'score' => (float) $score,
                'performance_stage' => $assessment->score?->maturityLevel?->level_name,
                'completed_at' => $assessment->completed_at,
            ];
        })->filter()->values();

        $groups = $latest
            ->groupBy(fn (array $row) => filled($row['comparison_signature'])
                ? 'comparable:'.$row['comparison_signature']
                : 'standalone:'.$row['assessment_id'])
            ->map(function (Collection $rows): array {
                $comparable = filled($rows->first()['comparison_signature']) && $rows->count() > 1;
                $average = $comparable ? round($rows->avg('score'), 1) : null;
                $ordered = $comparable ? $rows->sortByDesc('score') : $rows->sortByDesc('completed_at');

                return [
                    'name' => $rows->first()['assessment_name'],
                    'comparable' => $comparable,
                    'average' => $average,
                    'why' => $comparable
                        ? 'These settings used the same assessment and scoring rules, so their results can be compared.'
                        : 'Shown on its own. No other completed setting currently shares this exact published content and scoring model.',
                    'domain_comparison' => $comparable ? $this->domainComparison($rows) : [],
                    'rows' => $ordered->map(fn (array $row) => array_merge($row, [
                        'difference_from_average' => $average === null ? null : round($row['score'] - $average, 1),
                    ]))->values()->all(),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();

        // Reuse TrendService per project — the exact same series/signature rule Project
        // Progress uses — rather than a second implementation of "is this improving."
        $progress = $this->progressOverTime($projects);
        $domainTrends = $this->domainTrends($projects);
        $issueMovement = $this->issueMovement($projects);

        return [
            'headline' => [
                'assessed_settings' => $latest->count(),
                'total_settings' => $projects->count(),
                'completed_assessments' => $projects->sum(fn (Project $project) => $project->assessments->count()),
                'repeated_compatible_assessments' => $progress['tracked']->count(),
                'improving_settings' => $progress['improving'],
                'unchanged_settings' => $progress['unchanged'],
                'declining_settings' => $progress['declining'],
                'open_actions' => AssessmentAction::whereIn('status', [AssessmentAction::STATUS_OPEN, AssessmentAction::STATUS_IN_PROGRESS])->count(),
                'overdue_actions' => AssessmentAction::whereNotNull('due_date')
                    ->whereDate('due_date', '<', today())
                    ->whereNotIn('status', [AssessmentAction::STATUS_DONE, AssessmentAction::STATUS_VERIFIED])
                    ->count(),
            ],
            'comparison_groups' => $groups,
            'progress' => $progress['tracked']->values()->all(),
            'domain_trends' => $domainTrends,
            'issue_movement' => $issueMovement,
            'action_follow_through' => $this->workspaceActionFollowThrough(),
            'coverage' => $this->coverage($projects),
        ];
    }

    /**
     * Direction of change for every project with a ≥2-run compatible series. Links into
     * Project Progress for the detail rather than re-rendering a trend chart per setting here.
     *
     * @return array{tracked: Collection<int, array<string, mixed>>, improving: int, unchanged: int, declining: int}
     */
    private function progressOverTime(Collection $projects): array
    {
        $tracked = $projects->map(function (Project $project) {
            $summary = $this->trends->summary($project);
            if (! $summary['comparable']) {
                return null;
            }

            return [
                'project_id' => $project->project_id,
                'setting_name' => $project->name,
                'direction' => $summary['direction'],
                'latest_score' => $summary['latest_score'],
                'overall_delta' => $summary['overall_delta'],
                'runs' => $summary['runs'],
            ];
        })->filter()->values();

        return [
            'tracked' => $tracked,
            'improving' => $tracked->where('direction', 'UP')->count(),
            'unchanged' => $tracked->where('direction', 'FLAT')->count(),
            'declining' => $tracked->where('direction', 'DOWN')->count(),
        ];
    }

    /**
     * Per governed domain, how many settings' compatible series are improving, stable, or
     * declining — sourced from the same domain_movements TrendService already computes.
     *
     * @return array<int, array{domain_code: string, domain_name: string, improving: int, stable: int, declining: int}>
     */
    private function domainTrends(Collection $projects): array
    {
        $byDomain = [];

        foreach ($projects as $project) {
            $summary = $this->trends->summary($project);
            if (! $summary['comparable']) {
                continue;
            }

            foreach ($summary['domain_movements'] as $movement) {
                $code = $movement['domain_code'];
                $byDomain[$code] ??= ['domain_code' => $code, 'domain_name' => $movement['domain_name'], 'improving' => 0, 'stable' => 0, 'declining' => 0];

                $byDomain[$code][match ($movement['direction']) {
                    'UP' => 'improving',
                    'DOWN' => 'declining',
                    default => 'stable',
                }]++;
            }
        }

        return collect($byDomain)->sortBy('domain_name')->values()->all();
    }

    /**
     * Workspace-wide rollup of exact issue movement — sourced from the same IssueTrackingService
     * result TrendService already produces per project, not a re-derived approximation.
     *
     * @return array{new: int, persistent: int, improving: int, resolved: int, not_comparable: int, tracked_settings: int}
     */
    private function issueMovement(Collection $projects): array
    {
        $totals = ['new' => 0, 'persistent' => 0, 'improving' => 0, 'resolved' => 0, 'not_comparable' => 0];
        $tracked = 0;

        foreach ($projects as $project) {
            $issues = $this->trends->issues($project);
            if (! $issues['comparable']) {
                continue;
            }

            $tracked++;
            foreach (array_keys($totals) as $key) {
                $totals[$key] += count($issues[$key]);
            }
        }

        return array_merge($totals, ['tracked_settings' => $tracked]);
    }

    /**
     * Average domain score across a comparable group's settings — the "domain-level comparisons
     * where those domain contracts also match" the group's own comparison_signature already
     * guarantees.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array{domain_code: string, domain_name: string, average: float}>
     */
    private function domainComparison(Collection $rows): array
    {
        $assessmentIds = $rows->pluck('assessment_id')->all();

        return DB::table('domain_scores as ds')
            ->join('domains as d', 'd.domain_id', '=', 'ds.domain_id')
            ->whereIn('ds.assessment_id', $assessmentIds)
            ->whereNotNull('ds.score')
            ->select('d.domain_code', 'd.domain_name', DB::raw('AVG(ds.score) as average'))
            ->groupBy('d.domain_code', 'd.domain_name')
            ->orderBy('d.domain_name')
            ->get()
            ->map(fn ($row) => ['domain_code' => $row->domain_code, 'domain_name' => $row->domain_name, 'average' => round((float) $row->average, 1)])
            ->all();
    }

    /**
     * @return array{total: int, open: int, in_progress: int, done: int, verified: int, overdue: int}
     */
    private function workspaceActionFollowThrough(): array
    {
        $actions = AssessmentAction::all();

        return [
            'total' => $actions->count(),
            'open' => $actions->where('status', AssessmentAction::STATUS_OPEN)->count(),
            'in_progress' => $actions->where('status', AssessmentAction::STATUS_IN_PROGRESS)->count(),
            'done' => $actions->where('status', AssessmentAction::STATUS_DONE)->count(),
            'verified' => $actions->where('status', AssessmentAction::STATUS_VERIFIED)->count(),
            'overdue' => $actions->filter->isOverdue()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function coverage(Collection $projects): array
    {
        $projectIds = $projects->pluck('project_id');
        if ($projectIds->isEmpty()) {
            return ['by_type' => [], 'by_country' => [], 'not_yet_assessed' => [], 'insufficient_history' => 0];
        }

        $settings = DB::table('project_targets as pt')
            ->join('targets as t', 't.target_id', '=', 'pt.target_id')
            ->whereIn('pt.project_id', $projectIds->all())
            ->get(['t.target_type_code', 't.country']);

        $notYetAssessed = $projects->filter(fn (Project $project) => $project->assessments->isEmpty())
            ->map(fn (Project $project) => ['project_id' => $project->project_id, 'name' => $project->name])
            ->values()->all();

        // A setting with exactly one completed run has a baseline but not yet enough history
        // for any trend claim — distinct from having no result at all.
        $insufficientHistory = $projects->filter(fn (Project $project) => $project->assessments->count() === 1)->count();

        $overdueReassessments = ReportSchedule::whereIn('project_id', $projectIds->all())
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<', now())
            ->count();

        return [
            'by_type' => $settings->groupBy('target_type_code')->map->count()->sortDesc()->all(),
            'by_country' => $settings->whereNotNull('country')->groupBy('country')->map->count()->sortDesc()->all(),
            'not_yet_assessed' => $notYetAssessed,
            'insufficient_history' => $insufficientHistory,
            'overdue_reassessments' => $overdueReassessments,
        ];
    }
}
