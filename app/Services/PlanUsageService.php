<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Project;
use App\Models\SubscriptionPlan;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Collection;

/**
 * What each plan is actually carrying, and who is close to its limits.
 *
 * The plan screen could set limits but never showed usage against them, so there was no
 * way to see who was about to hit a ceiling or who had outgrown their plan. These are
 * the two questions the screen exists to answer.
 *
 * A null limit means unlimited, which is the current beta position for every plan. That
 * is reported as unlimited rather than as "0 used of 0", because the second reads as a
 * problem when it is a deliberate setting.
 */
class PlanUsageService
{
    /**
     * A workspace at or above this share of a limit is worth flagging.
     */
    private const NEAR_LIMIT_RATIO = 0.8;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function byPlan(): Collection
    {
        $workspaces = Workspace::withCount(['members', 'projects'])->get();

        return SubscriptionPlan::orderBy('display_order')->get()->map(function (SubscriptionPlan $plan) use ($workspaces): array {
            $onPlan = $workspaces->filter(
                fn (Workspace $w) => PlanService::normalizePlan($w->plan ?? 'STARTER') === $plan->plan_code
            );

            return [
                'plan' => $plan,
                'workspace_count' => $onPlan->count(),
                'active_count' => $onPlan->where('status', 'ACTIVE')->count(),
                'people_count' => (int) $onPlan->sum('members_count'),
                'project_count' => (int) $onPlan->sum('projects_count'),
                'limits' => $this->describeLimits($plan),
            ];
        });
    }

    /**
     * Workspaces close to, or past, a limit on their plan.
     *
     * @return array<int, array{workspace: Workspace, limit: string, used: int, allowed: int, over: bool}>
     */
    public function workspacesNearLimit(): array
    {
        $plans = SubscriptionPlan::all()->keyBy('plan_code');
        $flagged = [];

        $workspaces = Workspace::withCount(['members', 'projects'])
            ->where('status', 'ACTIVE')
            ->get();

        foreach ($workspaces as $workspace) {
            $plan = $plans->get(PlanService::normalizePlan($workspace->plan ?? 'STARTER'));
            $limits = $plan?->limits ?? [];

            foreach ([
                'projects' => ['Projects', (int) $workspace->projects_count],
                'seats' => ['People', (int) $workspace->members_count],
            ] as $key => [$label, $used]) {
                $allowed = $limits[$key] ?? null;

                if ($allowed === null || $allowed <= 0) {
                    continue;
                }

                if ($used >= $allowed * self::NEAR_LIMIT_RATIO) {
                    $flagged[] = [
                        'workspace' => $workspace,
                        'limit' => $label,
                        'used' => $used,
                        'allowed' => (int) $allowed,
                        'over' => $used > $allowed,
                    ];
                }
            }
        }

        return $flagged;
    }

    /**
     * @return array<string, int|null>
     */
    public function totals(): array
    {
        return [
            'workspaces' => Workspace::count(),
            'people' => WorkspaceMember::count(),
            'projects' => Project::withoutGlobalScopes()->count(),
            'assessments' => Assessment::withoutGlobalScopes()->count(),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function describeLimits(SubscriptionPlan $plan): array
    {
        $limits = $plan->limits ?? [];

        return collect([
            'projects' => 'Projects',
            'assessments_per_project' => 'Assessments per project',
            'respondents_per_assessment' => 'Respondents per assessment',
            'seats' => 'People',
            'reports' => 'Reports',
            'storage_mb' => 'Storage',
        ])->map(fn (string $label, string $key) => [
            'label' => $label,
            'value' => $this->describeLimit($key, $limits[$key] ?? null),
        ])->values()->all();
    }

    private function describeLimit(string $key, int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return 'Unlimited';
        }

        return $key === 'storage_mb' ? $value.' MB' : (string) $value;
    }
}
