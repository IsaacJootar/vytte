<?php

namespace App\Services\Reporting;

use App\Models\Assessment;
use App\Models\AssessmentAction;
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
