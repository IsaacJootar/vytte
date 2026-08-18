<?php

namespace App\Services\Reporting;

use App\Models\Assessment;
use App\Models\AssessmentAction;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A read-only management view across the workspace's completed assessments.
 *
 * Scores are grouped only when their frozen comparison signatures match. Different assessment
 * types remain visible but are never averaged, ranked, or described as directly comparable.
 */
class PortfolioService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $projects = Project::with([
            'assessments' => fn ($query) => $query
                ->where('status', Assessment::STATUS_COMPLETE)
                ->with(['score.maturityLevel', 'snapshot', 'catalogueRelease', 'target'])
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
                'comparison_signature' => $assessment->snapshot?->comparison_signature,
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
                    'rows' => $ordered->map(fn (array $row) => array_merge($row, [
                        'difference_from_average' => $average === null ? null : round($row['score'] - $average, 1),
                    ]))->values()->all(),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();

        $openActions = AssessmentAction::whereIn('status', [AssessmentAction::STATUS_OPEN, AssessmentAction::STATUS_IN_PROGRESS])->count();
        $overdueActions = AssessmentAction::whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->whereNotIn('status', [AssessmentAction::STATUS_DONE, AssessmentAction::STATUS_VERIFIED])
            ->count();

        return [
            'headline' => [
                'assessed_settings' => $latest->count(),
                'total_settings' => $projects->count(),
                'completed_assessments' => $projects->sum(fn (Project $project) => $project->assessments->count()),
                'open_actions' => $openActions,
                'overdue_actions' => $overdueActions,
            ],
            'comparison_groups' => $groups,
            'coverage' => $this->coverage($projects->pluck('project_id')),
        ];
    }

    /**
     * @param  Collection<int, string>  $projectIds
     * @return array<string, mixed>
     */
    private function coverage(Collection $projectIds): array
    {
        if ($projectIds->isEmpty()) {
            return ['by_type' => [], 'by_country' => []];
        }

        $settings = DB::table('project_targets as pt')
            ->join('targets as t', 't.target_id', '=', 'pt.target_id')
            ->whereIn('pt.project_id', $projectIds->all())
            ->get(['t.target_type_code', 't.country']);

        return [
            'by_type' => $settings->groupBy('target_type_code')->map->count()->sortDesc()->all(),
            'by_country' => $settings->whereNotNull('country')->groupBy('country')->map->count()->sortDesc()->all(),
        ];
    }
}
