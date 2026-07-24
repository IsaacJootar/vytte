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
 * of that target is a point on the same line. Only assessments that share the latest one's
 * composition hash are compared, so like is always measured against like.
 *
 * Trend also reads the action plan (§9): progress is not only whether the score moved, but
 * whether the things the org agreed to do actually got done. That link is what makes this
 * depend on the action domain existing first.
 */
class TrendService
{
    /**
     * Longitudinal summary for a project: the score trajectory and where each domain moved.
     *
     * @return array{comparable: bool, runs: int, latest_score: ?float, previous_score: ?float, overall_delta: ?float, direction: string, first_score: ?float, since_first_delta: ?float, domain_movements: array<int, array<string, mixed>>}
     */
    public function summary(Project $project): array
    {
        $history = Assessment::where('project_id', $project->project_id)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->with('score')
            ->orderBy('completed_at')
            ->get();

        // Compare only within the latest run's composition, so a change in content never
        // masquerades as a change in performance.
        $latest = $history->last();
        $comparable = $latest
            ? $history->filter(fn ($a) => $a->composition_hash === $latest->composition_hash)->values()
            : collect();

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
     * How each domain has moved between the latest two comparable runs, classified as the
     * organisational-learning story: resolved, persistent, new, regressed, improved.
     *
     * This is the heart of progress tracking — not just "the score changed" but "this problem
     * was fixed, that one is still with us, and a new one has appeared."
     *
     * @return array{comparable: bool, resolved: array<int, mixed>, persistent: array<int, mixed>, new: array<int, mixed>, regressed: array<int, mixed>, improved: array<int, mixed>}
     */
    public function issues(Project $project): array
    {
        [$latest, $previous] = $this->latestComparablePair($project);
        $empty = ['comparable' => false, 'resolved' => [], 'persistent' => [], 'new' => [], 'regressed' => [], 'improved' => []];
        if ($latest === null || $previous === null) {
            return $empty;
        }

        $buckets = ['resolved' => [], 'persistent' => [], 'new' => [], 'regressed' => [], 'improved' => []];
        foreach ($this->domainMovements($latest, $previous) as $move) {
            if ($move['latest'] === null || $move['previous'] === null) {
                continue;
            }
            $status = $this->issueStatus((float) $move['previous'], (float) $move['latest']);
            if ($status !== null) {
                $buckets[$status][] = $move;
            }
        }

        return array_merge(['comparable' => true], $buckets);
    }

    /**
     * Current performance against the goals set for this project — overall and per domain.
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
        foreach ($issues['new'] as $move) {
            $insights[] = $this->trendInsight('EMERGING_ISSUE', $move, $move['domain_name'].' has emerged as a new weak area since the last assessment.');
        }
        foreach ($issues['regressed'] as $move) {
            $insights[] = $this->trendInsight('DECLINE', $move, $move['domain_name'].' has slipped since the last assessment ('.$this->signed($move['delta']).').');
        }
        foreach ($issues['persistent'] as $move) {
            $insights[] = $this->trendInsight('NO_CHANGE', $move, $move['domain_name'].' remains weak — it has not moved since the last assessment.');
        }

        return $insights;
    }

    /**
     * @param  array<string, mixed>  $move
     * @return array<string, mixed>
     */
    private function trendInsight(string $code, array $move, string $statement): array
    {
        return [
            'category_code' => $code,
            'category_name' => InsightCatalog::name($code),
            'polarity' => InsightCatalog::polarity($code),
            'subject' => $move['domain_name'],
            'measurement_domain' => $move['domain_code'],
            'statement' => $statement,
        ];
    }

    private function issueStatus(float $previous, float $latest): ?string
    {
        $prevWeak = $previous < 45.0;
        $nowWeak = $latest < 45.0;
        $prevBand = $this->band($previous);
        $nowBand = $this->band($latest);

        return match (true) {
            $prevWeak && ! $nowWeak => 'resolved',
            ! $prevWeak && $nowWeak => 'new',
            $prevWeak && $nowWeak => 'persistent',
            $nowBand > $prevBand => 'improved',
            $nowBand < $prevBand => 'regressed',
            default => null, // stable — no story to tell
        };
    }

    /** 0 weak, 1 moderate, 2 strong. */
    private function band(float $score): int
    {
        return $score >= 70 ? 2 : ($score >= 45 ? 1 : 0);
    }

    private function signed(?float $delta): string
    {
        if ($delta === null) {
            return 'no change';
        }

        return ($delta >= 0 ? '+' : '').round($delta, 1);
    }

    /**
     * The latest two composition-matched complete runs, newest last.
     *
     * @return array{0: ?Assessment, 1: ?Assessment}
     */
    private function latestComparablePair(Project $project, bool $requireTwo = true): array
    {
        $history = Assessment::where('project_id', $project->project_id)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->with('score')
            ->orderBy('completed_at')
            ->get();

        $latest = $history->last();
        if ($latest === null) {
            return [null, null];
        }

        $comparable = $history->filter(fn ($a) => $a->composition_hash === $latest->composition_hash)->values();
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
