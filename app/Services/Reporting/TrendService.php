<?php

namespace App\Services\Reporting;

use App\Models\Assessment;
use App\Models\AssessmentAction;
use App\Models\PerformanceTarget;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * The "same target over time" view.
 *
 * A single report is a photograph; a trend is the story between photographs. Because a
 * project holds exactly one target, "over time" is unambiguous — every finalised assessment
 * of that target is a point on the same line. Only assessments in the current compatible
 * series — decided once, by ComparisonSeriesService, the same way everywhere else in Vytte
 * decides it — are compared, so like is always measured against like.
 *
 * Trend also reads the action plan (§9): progress is not only whether the score moved, but
 * whether the things the org agreed to do actually got done. That link is what makes this
 * depend on the action domain existing first.
 */
class TrendService
{
    public function __construct(
        private readonly ComparisonSeriesService $series,
        private readonly IssueTrackingService $issueTracking,
    ) {}

    /**
     * Longitudinal summary for a project: the score trajectory and where each domain moved.
     *
     * @return array{comparable: bool, runs: int, latest_score: ?float, previous_score: ?float, overall_delta: ?float, direction: string, first_score: ?float, since_first_delta: ?float, domain_movements: array<int, array<string, mixed>>}
     */
    public function summary(Project $project): array
    {
        // Compare only within the current compatible series, so a change in methodology never
        // masquerades as a change in performance.
        $comparable = $this->series->seriesFor($project);

        if ($comparable->count() < 2) {
            return [
                'comparable' => false,
                'runs' => $comparable->count(),
                'latest_score' => $this->overall($comparable->last()),
                'previous_score' => null,
                'overall_delta' => null,
                'direction' => 'FLAT',
                'first_score' => null,
                'since_first_delta' => null,
                'domain_movements' => [],
            ];
        }

        $latestRun = $comparable->last();
        $previousRun = $comparable->get($comparable->count() - 2);
        $firstRun = $comparable->first();

        $latestScore = $this->overall($latestRun);
        $previousScore = $this->overall($previousRun);
        $firstScore = $this->overall($firstRun);
        $delta = ($latestScore !== null && $previousScore !== null) ? round($latestScore - $previousScore, 1) : null;

        return [
            'comparable' => true,
            'runs' => $comparable->count(),
            'latest_score' => $latestScore,
            'previous_score' => $previousScore,
            'overall_delta' => $delta,
            'direction' => $this->direction($delta),
            'first_score' => $firstScore,
            'since_first_delta' => ($latestScore !== null && $firstScore !== null) ? round($latestScore - $firstScore, 1) : null,
            'domain_movements' => $this->domainMovements($latestRun, $previousRun),
        ];
    }

    /**
     * Did the agreed actions get done? Progress read against the action plan, not the score.
     *
     * @return array{total: int, open: int, in_progress: int, done: int, verified: int, completed: int, completion_rate: ?float, overdue: int}
     */
    public function actionFollowThrough(Project $project): array
    {
        $actions = AssessmentAction::where('project_id', $project->project_id)->get();
        $total = $actions->count();
        $completed = $actions->whereIn('status', [AssessmentAction::STATUS_DONE, AssessmentAction::STATUS_VERIFIED])->count();

        return [
            'total' => $total,
            'open' => $actions->where('status', AssessmentAction::STATUS_OPEN)->count(),
            'in_progress' => $actions->where('status', AssessmentAction::STATUS_IN_PROGRESS)->count(),
            'done' => $actions->where('status', AssessmentAction::STATUS_DONE)->count(),
            'verified' => $actions->where('status', AssessmentAction::STATUS_VERIFIED)->count(),
            'completed' => $completed,
            'completion_rate' => $total > 0 ? round($completed / $total * 100, 0) : null,
            'overdue' => $actions->filter->isOverdue()->count(),
        ];
    }

    /**
     * Exact assessed issues, classified by stable issue_key across the latest two comparable
     * runs — not domain score bands. "This problem was fixed, that one is still with us, and a
     * new one has appeared" is only true when it is the *same question* fixed, persisting, or
     * new; a domain average moving is a symptom of this, not the fact itself.
     *
     * @return array{comparable: bool, new: array<int, mixed>, persistent: array<int, mixed>, improving: array<int, mixed>, resolved: array<int, mixed>, not_comparable: array<int, mixed>}
     */
    public function issues(Project $project): array
    {
        $empty = ['comparable' => false, 'new' => [], 'persistent' => [], 'improving' => [], 'resolved' => [], 'not_comparable' => []];

        [$latest, $previous] = $this->latestComparablePair($project, requireTwo: false);
        if ($latest === null) {
            return $empty;
        }

        // A chronologically earlier run exists but fell outside the current series: real
        // history, just not one Vytte will silently compare against. Distinct from a true
        // baseline, where nothing exists to compare against yet.
        $incompatibleHistory = $previous === null && $this->series->hasIncompatiblePriorHistory($project);
        if ($previous === null && ! $incompatibleHistory) {
            return $empty;
        }

        $currentViews = $latest->reportSnapshot?->payload['measurement_views'] ?? [];
        $previousViews = $previous?->reportSnapshot?->payload['measurement_views'] ?? null;
        $result = $this->issueTracking->compare($currentViews, $previousViews, ! $incompatibleHistory);

        $buckets = ['new' => [], 'persistent' => [], 'improving' => [], 'resolved' => [], 'not_comparable' => []];
        foreach ($result['open'] as $issue) {
            $buckets[strtolower($issue['progress_status'])][] = $this->issueRow($issue);
        }
        foreach ($result['resolved'] as $issue) {
            $buckets['resolved'][] = $this->issueRow($issue);
        }

        return array_merge(['comparable' => true], $buckets);
    }

    /**
     * @param  array<string, mixed>  $issue
     * @return array<string, mixed>
     */
    private function issueRow(array $issue): array
    {
        $code = $issue['measurement_domain'] ?? null;

        return [
            'issue_key' => $issue['issue_key'] ?? null,
            'domain_code' => $code,
            'domain_name' => $code ? ($this->domainNames()['code_to_name'][$code] ?? $code) : 'General',
            'question_text' => $issue['question_text'] ?? null,
            'item_score' => $issue['item_score'] ?? null,
            'critical_failure' => (bool) ($issue['critical_failure'] ?? false),
        ];
    }

    /**
     * Current performance against the goals set for this project — overall and per domain.
     *
     * A target is measured only against the series it was set under. A target with no recorded
     * signature (set before this was tracked, or before any assessment existed) is treated as
     * bound to whichever series is current; a target whose signature no longer matches the
     * current series is silently excluded here rather than measured against a different
     * methodology — it remains visible in target management for the user to remove or reset.
     *
     * @return array<int, array{scope: string, target: float, current: ?float, gap: ?float, met: bool}>
     */
    public function targetProgress(Project $project): array
    {
        $targets = PerformanceTarget::where('project_id', $project->project_id)->get();
        if ($targets->isEmpty()) {
            return [];
        }

        [$latest] = $this->latestComparablePair($project, requireTwo: false);
        if ($latest === null) {
            return [];
        }

        $currentSignature = $this->series->signatureFor($latest);
        $targets = $targets->filter(fn ($target) => $target->comparison_signature === null
            || ($currentSignature !== null && hash_equals($target->comparison_signature, $currentSignature)));

        $overall = $this->overall($latest);
        $domainScores = $this->domainScores($latest->assessment_id);
        $domainNames = $this->domainNames();

        return $targets->map(function ($target) use ($overall, $domainScores, $domainNames) {
            if ($target->domain_code === null) {
                $current = $overall;
                $scope = 'Overall';
            } else {
                $domainId = array_search($target->domain_code, $domainNames['code_to_id'], true);
                $current = $domainId !== false ? ($domainScores[$domainId] ?? null) : null;
                $scope = $domainNames['code_to_name'][$target->domain_code] ?? $target->domain_code;
            }

            $gap = $current !== null ? round($current - (float) $target->target_score, 1) : null;

            return [
                'scope' => $scope,
                'target' => (float) $target->target_score,
                'current' => $current,
                'gap' => $gap,
                'met' => $gap !== null && $gap >= 0,
            ];
        })->all();
    }

    /**
     * Trend-only insights — the categories that only exist across time: emerging issues (a
     * new weakness), deterioration (a domain that slipped), and no change (a weakness that
     * persists). Produced from the issue matching, using the governed insight categories.
     *
     * @return array<int, array<string, mixed>>
     */
    public function trendInsights(Project $project): array
    {
        $issues = $this->issues($project);
        if (! $issues['comparable']) {
            return [];
        }

        $insights = [];
        foreach ($issues['new'] as $issue) {
            $insights[] = $this->trendInsight('EMERGING_ISSUE', $issue, ($issue['question_text'] ?? 'A new issue').' has appeared as a new finding since the last comparable assessment.');
        }
        foreach ($issues['persistent'] as $issue) {
            $insights[] = $this->trendInsight('NO_CHANGE', $issue, ($issue['question_text'] ?? 'This finding').' remains an open issue — it has not moved since the last comparable assessment.');
        }

        return $insights;
    }

    /**
     * @param  array<string, mixed>  $issue
     * @return array<string, mixed>
     */
    private function trendInsight(string $code, array $issue, string $statement): array
    {
        return [
            'category_code' => $code,
            'category_name' => InsightCatalog::name($code),
            'polarity' => InsightCatalog::polarity($code),
            'subject' => $issue['domain_name'],
            'measurement_domain' => $issue['domain_code'],
            'statement' => $statement,
        ];
    }

    /**
     * The latest two series-matched complete runs, newest last.
     *
     * @return array{0: ?Assessment, 1: ?Assessment}
     */
    private function latestComparablePair(Project $project, bool $requireTwo = true): array
    {
        $comparable = $this->series->seriesFor($project);
        if ($comparable->isEmpty()) {
            return [null, null];
        }

        if ($requireTwo && $comparable->count() < 2) {
            return [null, null];
        }

        return [$comparable->last(), $comparable->count() >= 2 ? $comparable->get($comparable->count() - 2) : null];
    }

    /**
     * @return array{code_to_id: array<int, string>, code_to_name: array<string, string>}
     */
    private function domainNames(): array
    {
        $rows = DB::table('domains')->get(['domain_id', 'domain_code', 'domain_name']);

        return [
            'code_to_id' => $rows->pluck('domain_code', 'domain_id')->all(),
            'code_to_name' => $rows->pluck('domain_name', 'domain_code')->all(),
        ];
    }

    /**
     * Per-domain movement between two runs.
     *
     * @return array<int, array<string, mixed>>
     */
    private function domainMovements(Assessment $latest, Assessment $previous): array
    {
        $latestScores = $this->domainScores($latest->assessment_id);
        $previousScores = $this->domainScores($previous->assessment_id);

        // Show every domain that either run scored, in taxonomy order. Basing this on the
        // scores that exist — rather than an is_operational flag that is not set on any
        // domain in the current taxonomy — is what makes the movement list actually populate.
        $scoredDomainIds = array_keys($latestScores + $previousScores);
        if ($scoredDomainIds === []) {
            return [];
        }

        return DB::table('domains')
            ->whereIn('domain_id', $scoredDomainIds)
            ->orderBy('display_order')
            ->get()
            ->map(function ($domain) use ($latestScores, $previousScores) {
                $now = $latestScores[$domain->domain_id] ?? null;
                $then = $previousScores[$domain->domain_id] ?? null;
                $delta = ($now !== null && $then !== null) ? round($now - $then, 1) : null;

                return [
                    'domain_code' => $domain->domain_code,
                    'domain_name' => $domain->domain_name,
                    'latest' => $now,
                    'previous' => $then,
                    'delta' => $delta,
                    'direction' => $this->direction($delta),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, float>
     */
    private function domainScores(string $assessmentId): array
    {
        return DB::table('domain_scores')
            ->where('assessment_id', $assessmentId)
            ->whereNotNull('score')
            ->pluck('score', 'domain_id')
            ->map(fn ($score) => (float) $score)
            ->all();
    }

    private function overall(?Assessment $assessment): ?float
    {
        $score = $assessment?->score?->overall_score;

        return $score !== null ? (float) $score : null;
    }

    private function direction(?float $delta): string
    {
        if ($delta === null || abs($delta) < 0.05) {
            return 'FLAT';
        }

        return $delta > 0 ? 'UP' : 'DOWN';
    }
}
