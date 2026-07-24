<?php

namespace App\Services\Reporting;

use App\Models\Assessment;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * In-tenant benchmarking — comparing a workspace's own facilities with each other.
 *
 * This is the safe half of benchmarking: every project compared here belongs to the same
 * workspace, so nothing crosses the tenant boundary (the cross-tenant, anonymised national
 * average is the deferred exception — see REPORTING_INTELLIGENCE_BLUEPRINT.md §8). It answers
 * "how does this facility compare with our others?" using each project's latest complete run.
 *
 * Reads the workspace-scoped Project model, so tenancy isolation is enforced by the global
 * scope, not by this service.
 */
class BenchmarkService
{
    /**
     * Every project's latest overall score, ranked — a facility league table for the
     * workspace.
     *
     * @return array<int, array{project_id: string, project_name: string, score: ?float, completed_at: ?string, rank: int}>
     */
    public function facilityComparison(): array
    {
        $rows = Project::with(['assessments' => function ($q) {
            $q->where('status', Assessment::STATUS_COMPLETE)->with('score')->orderByDesc('completed_at');
        }])->get()
            ->map(function ($project) {
                $latest = $project->assessments->first();
                $score = $latest?->score?->overall_score;

                return [
                    'project_id' => $project->project_id,
                    'project_name' => $project->name,
                    'score' => $score !== null ? (float) $score : null,
                    'completed_at' => $latest?->completed_at?->toIso8601String(),
                ];
            })
            ->filter(fn ($row) => $row['score'] !== null)
            ->sortByDesc('score')
            ->values();

        $average = $rows->isNotEmpty() ? round($rows->avg('score'), 1) : null;

        return $rows->map(fn ($row, $i) => array_merge($row, [
            'rank' => $i + 1,
            'vs_average' => $average !== null ? round($row['score'] - $average, 1) : null,
        ]))->all();
    }

    /**
     * The workspace average overall score across facilities, for context on any single one.
     */
    public function workspaceAverage(): ?float
    {
        $scores = collect($this->facilityComparison())->pluck('score')->filter();

        return $scores->isNotEmpty() ? round($scores->avg(), 1) : null;
    }

    /**
     * Domain-by-domain comparison across all of a workspace's facilities' latest runs — where
     * this workspace is collectively strong and weak.
     *
     * @return array<int, array{domain_code: string, domain_name: string, average: float, facility_count: int}>
     */
    public function domainComparison(): array
    {
        $latestIds = Project::with(['assessments' => fn ($q) => $q->where('status', Assessment::STATUS_COMPLETE)->orderByDesc('completed_at')])
            ->get()
            ->map(fn ($p) => $p->assessments->first()?->assessment_id)
            ->filter()
            ->values();

        if ($latestIds->isEmpty()) {
            return [];
        }

        return DB::table('domain_scores as ds')
            ->join('domains as d', 'd.domain_id', '=', 'ds.domain_id')
            ->whereIn('ds.assessment_id', $latestIds->all())
            ->whereNotNull('ds.score')
            ->groupBy('d.domain_code', 'd.domain_name', 'd.display_order')
            ->orderBy('d.display_order')
            ->get(['d.domain_code', 'd.domain_name', DB::raw('avg(ds.score) as average'), DB::raw('count(*) as facility_count')])
            ->map(fn ($row) => [
                'domain_code' => $row->domain_code,
                'domain_name' => $row->domain_name,
                'average' => round((float) $row->average, 1),
                'facility_count' => (int) $row->facility_count,
            ])
            ->all();
    }
}
