<?php

namespace App\Services\Reporting;

use App\Models\Assessment;
use App\Models\Workspace;
use App\Support\ReportType;
use Illuminate\Support\Str;

/**
 * The programme-wide overview: every site of one programme, read together.
 *
 * Each school or PHC is its own project (a project holds exactly one target), so a programme is
 * the set of programme-type assessments across projects whose template declares the same
 * programme name. This is the programme-scoped counterpart of the workspace Portfolio. It only
 * gathers the already-frozen per-site reports (ProgrammeReportService), so a number here always
 * matches the site report it came from. Where a site has been assessed more than once, its most
 * recent completed assessment is shown. Schools and PHCs are listed and counted together for
 * prioritising, and summarised separately by site type.
 */
class ProgrammeOverviewService
{
    /** Order sites are listed in: start with the highest need that is ready to go. */
    private const PRIORITY = [
        'HIGH_NEED_READY' => 0,
        'HIGH_NEED_NOT_READY' => 1,
        'LOWER_NEED_READY' => 2,
        'LOWER_NEED_NOT_READY' => 3,
    ];

    public function __construct(
        private readonly ProgrammeReportService $reports,
        private readonly ProgrammeIndicatorService $indicators,
    ) {}

    /**
     * The programmes that have at least one assessment in the workspace.
     *
     * @return array<int, array{name: string, slug: string, sites: int}>
     */
    public function programmesFor(Workspace $workspace): array
    {
        return $this->programmeAssessments($workspace)
            ->groupBy(fn (Assessment $assessment) => (string) data_get(ReportType::config($assessment), 'programme'))
            ->map(fn ($group, $name) => [
                'name' => $name,
                'slug' => Str::slug($name),
                'sites' => $group->pluck('project_id')->unique()->count(),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null null when the workspace has no such programme */
    public function forProgramme(Workspace $workspace, string $slug): ?array
    {
        $all = $this->programmeAssessments($workspace)
            ->filter(fn (Assessment $assessment) => Str::slug((string) data_get(ReportType::config($assessment), 'programme')) === $slug)
            ->values();

        if ($all->isEmpty()) {
            return null;
        }

        $name = (string) data_get(ReportType::config($all->first()), 'programme');
        // Girls' surveys are summarised on their own; the school and PHC rows come from site assessments.
        $assessments = $all->reject(fn (Assessment $assessment) => ReportType::isSurvey($assessment))->values();
        $surveys = $all->filter(fn (Assessment $assessment) => ReportType::isSurvey($assessment) && $assessment->status === Assessment::STATUS_COMPLETE);
        $inProgress = $assessments->where('status', '!=', Assessment::STATUS_COMPLETE)->pluck('project_id')->unique()->count();

        // One row per site: its most recent completed assessment.
        $latest = $assessments->where('status', Assessment::STATUS_COMPLETE)
            ->sortByDesc('completed_at')
            ->unique('project_id')
            ->values();

        $rows = $latest->map(function (Assessment $assessment) {
            $report = $this->reports->forAssessment($assessment);

            return [
                'assessment' => $assessment,
                'name' => $report['site']['name'],
                'area_council' => $report['site']['area_council'] ?: 'Area council not set',
                'type' => $report['noun'],
                'provision' => $report['provision']['score'],
                'readiness' => $report['readiness']['score'],
                'provision_band' => $report['provision']['band'],
                'readiness_band' => $report['readiness']['band'],
                'classification' => $report['classification'],
                'flag_count' => count($report['flags']),
                'all_gaps' => $report['all_gaps'],
            ];
        })->values();

        $ordered = $rows->sortBy([
            fn ($a, $b) => (self::PRIORITY[$a['classification']['key'] ?? ''] ?? 9) <=> (self::PRIORITY[$b['classification']['key'] ?? ''] ?? 9),
            fn ($a, $b) => ($a['provision'] ?? 101) <=> ($b['provision'] ?? 101),
        ])->values();

        $labels = [
            'HIGH_NEED_READY' => 'Start here',
            'HIGH_NEED_NOT_READY' => 'Prepare first',
            'LOWER_NEED_READY' => 'Later',
            'LOWER_NEED_NOT_READY' => 'Not a priority now',
        ];
        foreach ($rows as $row) {
            if ($row['classification']) {
                $labels[$row['classification']['key']] = $row['classification']['label'];
            }
        }

        $counts = [];
        foreach (array_keys(self::PRIORITY) as $key) {
            $counts[$key] = $rows->filter(fn ($row) => ($row['classification']['key'] ?? null) === $key)->count();
        }

        $commonGaps = $rows->flatMap(fn ($row) => collect($row['all_gaps'])->unique())
            ->countBy()->sortDesc()->take(8)
            ->map(fn ($count, $question) => ['question' => $question, 'sites' => $count])
            ->values()->all();

        return [
            'programme' => $name,
            'slug' => $slug,
            'assessed' => $rows->count(),
            'in_progress' => $inProgress,
            'counts' => $counts,
            'labels' => $labels,
            'rows' => $ordered->all(),
            'by_area_council' => $rows->groupBy('area_council')->map(fn ($group, $area) => $this->summarise($area, $group))->sortKeys()->values()->all(),
            'by_site_type' => $rows->groupBy('type')->map(fn ($group, $type) => $this->summarise($type, $group))->sortKeys()->values()->all(),
            'common_gaps' => $commonGaps,
            'survey' => $this->surveySummary($surveys),
        ];
    }

    /**
     * Girls' and women's survey results across every school and PHC surveyed, pooled by respondent.
     * Each school or PHC counts once, through its most recent completed survey.
     *
     * @return array<string, mixed>|null null when no survey has been completed
     */
    private function surveySummary($surveys): ?array
    {
        $latest = $surveys->sortByDesc('completed_at')->unique('project_id')->values();
        if ($latest->isEmpty()) {
            return null;
        }

        $results = $latest->map(fn (Assessment $assessment) => [
            'assessment' => $assessment,
            'name' => $assessment->target?->name ?? $assessment->project?->name ?? 'School or PHC',
            'area_council' => $assessment->target?->sub_region ?: 'Area council not set',
        ] + $this->indicators->forAssessment($assessment));

        return [
            'schools' => $results->count(),
            'respondents' => $results->sum('respondents'),
            'indicators' => $this->indicators->pool($results->all()),
            'rows' => $results->map(fn (array $row) => [
                'assessment' => $row['assessment'],
                'name' => $row['name'],
                'area_council' => $row['area_council'],
                'respondents' => $row['respondents'],
                'enough' => $row['enough'],
            ])->sortBy('name')->values()->all(),
        ];
    }

    /** All programme-type assessments in the workspace, with what the report needs eager-loaded. */
    private function programmeAssessments(Workspace $workspace)
    {
        return Assessment::whereHas('project', fn ($query) => $query->where('workspace_id', $workspace->workspace_id))
            ->with(['snapshot', 'reportSnapshot', 'project', 'target.targetType'])
            ->get()
            ->filter(fn (Assessment $assessment) => ReportType::isProgramme($assessment))
            ->values();
    }

    /** @return array<string, mixed> */
    private function summarise(string $name, $group): array
    {
        $count = fn (string $key) => $group->filter(fn ($row) => ($row['classification']['key'] ?? null) === $key)->count();
        $avg = fn (string $field) => ($values = $group->pluck($field)->filter(fn ($v) => $v !== null)) && $values->isNotEmpty()
            ? round($values->avg(), 0) : null;

        return [
            'name' => $name,
            'sites' => $group->count(),
            'start_here' => $count('HIGH_NEED_READY'),
            'prepare_first' => $count('HIGH_NEED_NOT_READY'),
            'later' => $count('LOWER_NEED_READY') + $count('LOWER_NEED_NOT_READY'),
            'provision' => $avg('provision'),
            'readiness' => $avg('readiness'),
        ];
    }
}
