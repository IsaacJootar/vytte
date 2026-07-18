<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Project;
use App\Models\SubscriptionPlan;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class PlanService
{
    public const PLANS = ['STARTER', 'PROFESSIONAL', 'ORGANIZATION'];

    public const LEGACY_PLAN_ALIASES = [
        'FREE' => 'STARTER',
        'PRO' => 'PROFESSIONAL',
        'AGENCY' => 'ORGANIZATION',
    ];

    public const FEATURES = [
        'projects' => 'Projects',
        'assessments' => 'Assessments',
        'respondent_collection' => 'Respondent Collection',
        'shareable_public_links' => 'Shareable Public Links',
        'shareable_report_links' => 'Shareable Report Links',
        'reports' => 'Reports',
        'pdf_export' => 'PDF Export',
        'pdf_export_no_watermark' => 'PDF Export Without Watermark',
        'csv_export' => 'CSV Export',
        'progress_maturity_tracking' => 'Progress & Maturity Tracking',
        'team_members' => 'Team Members',
        'workspace_custom_assessments' => 'Workspace Custom Assessments',
        'local_sections' => 'Local Sections',
        'localization' => 'Localization (Multi-language)',
        'module_library' => 'Module Library',
        'notifications' => 'Notifications',
        'audit_logs' => 'Audit Logs',
    ];

    public const PLAN_DEFINITIONS = [
        'STARTER' => [
            'name' => 'Starter',
            'label' => 'Starter',
            'description' => 'For a single team beginning structured health assessments.',
            'display_order' => 1,
            'limits' => [
                'projects' => null,
                'assessments_per_project' => null,
                'respondents_per_assessment' => null,
                'storage_mb' => null,
                'reports' => null,
                'seats' => null,
            ],
            'pricing_metadata' => [
                'billing_status' => 'future',
                'monthly_price' => null,
                'currency' => null,
            ],
        ],
        'PROFESSIONAL' => [
            'name' => 'Professional',
            'label' => 'Professional',
            'description' => 'For growing teams running repeated assessments and sharing reports.',
            'display_order' => 2,
            'limits' => [
                'projects' => null,
                'assessments_per_project' => null,
                'respondents_per_assessment' => null,
                'storage_mb' => null,
                'reports' => null,
                'seats' => null,
            ],
            'pricing_metadata' => [
                'billing_status' => 'future',
                'monthly_price' => null,
                'currency' => null,
            ],
        ],
        'ORGANIZATION' => [
            'name' => 'Organization',
            'label' => 'Organization',
            'description' => 'For agencies and organizations managing multiple projects and teams.',
            'display_order' => 3,
            'limits' => [
                'projects' => null,
                'assessments_per_project' => null,
                'respondents_per_assessment' => null,
                'storage_mb' => null,
                'reports' => null,
                'seats' => null,
            ],
            'pricing_metadata' => [
                'billing_status' => 'future',
                'monthly_price' => null,
                'currency' => null,
            ],
        ],
    ];

    public static function normalizePlan(?string $plan): string
    {
        $plan = strtoupper((string) ($plan ?: 'STARTER'));

        return self::LEGACY_PLAN_ALIASES[$plan] ?? $plan;
    }

    public static function activePlans()
    {
        if (! DB::getSchemaBuilder()->hasTable('subscription_plans')) {
            return collect(self::PLAN_DEFINITIONS)->map(function (array $definition, string $code) {
                return (object) [
                    'plan_code' => $code,
                    'plan_name' => $definition['name'],
                    'public_label' => $definition['label'],
                    'description' => $definition['description'],
                    'display_order' => $definition['display_order'],
                    'is_active' => true,
                    'is_beta_unlocked' => true,
                    'pricing_metadata' => $definition['pricing_metadata'],
                    'limits' => $definition['limits'],
                ];
            })->values();
        }

        return SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
    }

    public static function plan(?string $plan): ?SubscriptionPlan
    {
        if (! DB::getSchemaBuilder()->hasTable('subscription_plans')) {
            return null;
        }

        return SubscriptionPlan::find(self::normalizePlan($plan));
    }

    public static function limitsFor(Workspace $workspace): array
    {
        $plan = self::plan($workspace->plan);

        if ($plan) {
            return $plan->limits ?? [];
        }

        return self::PLAN_DEFINITIONS[self::normalizePlan($workspace->plan)]['limits']
            ?? self::PLAN_DEFINITIONS['STARTER']['limits'];
    }

    public static function projectLimit(Workspace $workspace): ?int
    {
        $limit = self::limitsFor($workspace)['projects'] ?? null;

        return $limit === null ? null : (int) $limit;
    }

    public static function assessmentLimit(Workspace $workspace): ?int
    {
        $limit = self::limitsFor($workspace)['assessments_per_project'] ?? null;

        return $limit === null ? null : (int) $limit;
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
        $plan = self::normalizePlan($plan);

        return self::PLAN_DEFINITIONS[$plan]['label'] ?? ucfirst(strtolower($plan));
    }

    public static function featureLabel(string $featureKey): string
    {
        return self::FEATURES[$featureKey] ?? ucwords(str_replace('_', ' ', $featureKey));
    }

    public static function workspaceCanAccess(Workspace $workspace, string $featureKey): bool
    {
        if (! array_key_exists($featureKey, self::FEATURES)) {
            return false;
        }

        $enabled = DB::table('plan_features')
            ->where('plan', self::normalizePlan($workspace->plan ?? 'STARTER'))
            ->where('feature_key', $featureKey)
            ->value('enabled');

        return (bool) $enabled;
    }

    public static function requiredPlanForFeature(string $featureKey): ?string
    {
        foreach (self::PLANS as $plan) {
            $enabled = DB::table('plan_features')
                ->where('plan', $plan)
                ->where('feature_key', $featureKey)
                ->value('enabled');

            if ($enabled) {
                return $plan;
            }
        }

        return null;
    }
}
