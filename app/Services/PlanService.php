<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Project;
use App\Models\Workspace;

class PlanService
{
    const PLANS = ['FREE', 'PRO', 'AGENCY'];

    const LIMITS = [
        'FREE' => ['projects' => 1, 'assessments_per_project' => 3],
        'PRO' => ['projects' => 10, 'assessments_per_project' => null],
        'AGENCY' => ['projects' => null, 'assessments_per_project' => null],
    ];

    const PRICES_KOBO = [
        'PRO' => 500000,
        'AGENCY' => 1500000,
    ];

    public static function projectLimit(Workspace $workspace): ?int
    {
        $plan = $workspace->plan ?? 'FREE';

        return array_key_exists($plan, self::LIMITS) ? self::LIMITS[$plan]['projects'] : 1;
    }

    public static function assessmentLimit(Workspace $workspace): ?int
    {
        $plan = $workspace->plan ?? 'FREE';

        return array_key_exists($plan, self::LIMITS) ? self::LIMITS[$plan]['assessments_per_project'] : 3;
    }

    public static function hasReachedProjectLimit(Workspace $workspace): bool
    {
        $limit = self::projectLimit($workspace);

        if ($limit === null) {
            return false;
        }

        return Project::where('workspace_id', $workspace->workspace_id)
            ->where('status', 'ACTIVE')
            ->count() >= $limit;
    }

    public static function hasReachedAssessmentLimit(Workspace $workspace, Project $project): bool
    {
        $limit = self::assessmentLimit($workspace);

        if ($limit === null) {
            return false;
        }

        return Assessment::where('project_id', $project->project_id)
            ->count() >= $limit;
    }

    public static function planLabel(string $plan): string
    {
        return match ($plan) {
            'PRO' => 'Pro',
            'AGENCY' => 'Agency',
            default => 'Free',
        };
    }

    public static function priceKobo(string $plan): int
    {
        return self::PRICES_KOBO[$plan] ?? 0;
    }

    public static function priceNgn(string $plan): string
    {
        $kobo = self::priceKobo($plan);

        return number_format($kobo / 100, 0).' NGN/month';
    }
}
