<?php

namespace App\Services\Reporting;

use App\Models\Assessment;
use App\Services\ReportSnapshotService;
use App\Support\ReportType;
use Illuminate\Support\Str;

/**
 * The report for a girls' and women's survey at one school or PHC: how many took part, and for each
 * programme outcome the share who gave the answer that matters, with the earlier survey beside it
 * when there is one. No lenses and no maturity levels: one figure per indicator, the number of
 * respondents behind it, and which way is better.
 */
class ProgrammeSurveyReportService
{
    public function forAssessment(Assessment $assessment): array
    {
        $assessment->loadMissing(['snapshot', 'reportSnapshot', 'project', 'target.targetType']);
        $payload = app(ReportSnapshotService::class)->payloadFor($assessment);
        $config = ReportType::config($assessment);
        $results = app(ProgrammeIndicatorService::class)->withComparison($assessment);
        $target = $payload['target'] ?? [];

        $outcomes = collect($results['indicators'])
            ->groupBy('outcome')
            ->map(fn ($rows, $name) => ['name' => $name ?: 'Other', 'indicators' => $rows->values()->all()])
            ->values()
            ->all();

        return [
            'programme' => $config['programme'] ?? 'Programme survey',
            'programme_slug' => Str::slug($config['programme'] ?? 'Programme survey'),
            'noun' => $config['site_noun'] ?? 'site',
            'site' => [
                'name' => $target['name'] ?? ($payload['title'] ?? 'Site'),
                'type' => $target['type'] ?? null,
                'area_council' => $target['sub_region'] ?? null,
                'state' => $target['region'] ?? null,
                'project' => $payload['project']['name'] ?? null,
                'surveyed_on' => $payload['completed_at'] ?? null,
            ],
            'respondents' => $results['respondents'],
            'enough' => $results['enough'],
            'minimum' => $results['minimum'],
            'previous' => $results['previous'] ?? null,
            'outcomes' => $outcomes,
            'sections' => collect($payload['sub_index_scores'] ?? [])
                ->map(fn ($section) => ['name' => $section['full_name'] ?? 'Section', 'score' => isset($section['score']) ? (float) $section['score'] : null])
                ->filter(fn ($section) => $section['score'] !== null)
                ->values()
                ->all(),
        ];
    }
}
