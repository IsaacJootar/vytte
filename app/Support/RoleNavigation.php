<?php

namespace App\Support;

use App\Services\PlanService;

/**
 * Navigation for each role.
 *
 * Every role shares one application shell. Only this configuration differs, so spacing,
 * mobile behaviour, theme handling and focus states are defined once and cannot drift
 * apart between roles.
 *
 * Groups are ordered by how often the work is done rather than by the objects behind it.
 * Setup and Advanced Tools are collapsed by default: an administrator who never opens them
 * never meets governance vocabulary.
 *
 * @phpstan-type NavItem array{route: string, label: string, icon: string, active: string, badge?: int|null}
 * @phpstan-type NavGroup array{label: string|null, collapsible: bool, items: list<NavItem>}
 */
final class RoleNavigation
{
    /**
     * @return array{home: array{route: string, title: string, subtitle: string}, groups: list<NavGroup>, mobile: list<array{route: string, label: string, icon: string, active: string}>, footer: array{label: string, value: string, accent: bool, link: array{route: string, label: string}|null}}
     */
    public static function platform(): array
    {
        return [
            'home' => [
                'route' => 'admin.dashboard',
                'title' => 'Vytte',
                'subtitle' => 'Platform Admin',
            ],
            'groups' => [
                [
                    'label' => null,
                    'collapsible' => false,
                    'items' => [
                        self::item('admin.dashboard', 'Dashboard', 'home', 'admin.dashboard'),
                        self::item('admin.assessments.index', 'Assessments', 'clipboard-document-list', 'admin.assessments.*'),
                        self::item('admin.question-identities.index', 'Question Library', 'question-mark-circle', 'admin.question-identities.*'),
                        self::item('admin.catalogue-releases.index', 'Publishing', 'arrow-up-tray', 'admin.catalogue-releases.*'),
                    ],
                ],
                [
                    'label' => 'Setup',
                    'collapsible' => true,
                    'items' => [
                        self::item('admin.modules.index', 'Departments', 'squares-2x2', 'admin.modules.*'),
                        self::item('admin.facility-profiles.index', 'Facility Types', 'building-office', 'admin.facility-profiles.*'),
                        self::item('admin.scores.index', 'Scores', 'chart-bar', 'admin.scores.*'),
                    ],
                ],
                [
                    'label' => 'Oversight',
                    'collapsible' => true,
                    'items' => [
                        self::item('admin.workspaces.index', 'Workspaces', 'building-office', 'admin.workspaces.*'),
                        self::item('admin.platform-users.index', 'People', 'user-group', 'admin.platform-users.*'),
                        self::item('admin.assessment-oversight.index', 'Assessments in Use', 'inbox-stack', 'admin.assessment-oversight.*'),
                        self::item('admin.audit-logs.index', 'Activity', 'clock', 'admin.audit-logs.*'),
                        self::item('admin.report-shares.index', 'Shared Reports', 'share', 'admin.report-shares.*'),
                        self::item('admin.plan-features.index', 'Plans', 'credit-card', 'admin.plan-features.*'),
                        self::item('admin.geographic-usage.index', 'Usage', 'globe-alt', 'admin.geographic-usage.*'),
                        self::item('admin.monitoring.index', 'Platform Health', 'heart', 'admin.monitoring.*'),
                        self::item('admin.settings.index', 'Settings', 'cog-6-tooth', 'admin.settings.*'),
                    ],
                ],
                [
                    'label' => 'Advanced Tools',
                    'collapsible' => true,
                    'items' => [
                        self::item('admin.official-content.index', 'Official Content', 'shield-check', 'admin.official-content.*'),
                        self::item('admin.framework-versions.index', 'Frameworks', 'document-text', 'admin.framework-versions.*'),
                        self::item('admin.question-versions.index', 'Question Versions', 'document-text', 'admin.question-versions.*'),
                        self::item('admin.question-groups.index', 'Question Groups', 'folder', 'admin.question-groups.*'),
                        self::item('admin.domain-taxonomies.index', 'Measurement Areas', 'adjustments-horizontal', 'admin.domain-taxonomies.*'),
                    ],
                ],
            ],
            'mobile' => [
                self::mobile('admin.dashboard', 'Home', 'home', 'admin.dashboard'),
                self::mobile('admin.assessments.index', 'Assess', 'clipboard-document-list', 'admin.assessments.*'),
                self::mobile('admin.question-identities.index', 'Library', 'question-mark-circle', 'admin.question-identities.*'),
                self::mobile('admin.catalogue-releases.index', 'Publish', 'arrow-up-tray', 'admin.catalogue-releases.*'),
                self::mobile('admin.settings.index', 'More', 'cog-6-tooth', 'admin.settings.*'),
            ],
            // No footer card. A platform administrator has no plan and no workspace to
            // return to, so an "access level" badge and a way back were noise. The role is
            // already stated under the logo.
            'footer' => null,
        ];
    }

    /**
     * @return array{home: array{route: string, title: string, subtitle: string}, groups: list<NavGroup>, mobile: list<array{route: string, label: string, icon: string, active: string}>, footer: array{label: string, value: string, accent: bool, link: array{route: string, label: string}|null}}
     */
    public static function workspace(): array
    {
        $user = auth()->user();
        $plan = PlanService::normalizePlan($user?->activeWorkspace?->plan ?? 'STARTER');

        return [
            'home' => [
                'route' => 'dashboard',
                'title' => 'Vytte',
                'subtitle' => $user?->activeWorkspace?->name ?? 'Workspace',
            ],
            'groups' => [
                [
                    'label' => null,
                    'collapsible' => false,
                    'items' => [
                        self::item('dashboard', 'Dashboard', 'home', 'dashboard'),
                        self::item('projects.index', 'Projects', 'folder', 'projects.*'),
                        self::item('assessments.index', 'Assessments', 'clipboard-document-list', 'assessments.*'),
                        self::item('reports.index', 'Reports', 'chart-bar', 'reports.*'),
                    ],
                ],
                [
                    'label' => 'Library',
                    'collapsible' => false,
                    'items' => [
                        self::item('modules.index', 'Modules', 'squares-2x2', 'modules.*'),
                        self::item('custom-assessments.index', 'Custom Assessments', 'document-text', 'custom-assessments.*'),
                    ],
                ],
                // A platform administrator holds both a workspace and platform authority,
                // and logging in lands them in the workspace. The way across used to be a
                // ten-pixel link in the footer card, which was effectively hidden.
                ...($user?->isPlatformAdmin() ? [[
                    'label' => 'Platform',
                    'collapsible' => false,
                    'items' => [
                        self::item('admin.dashboard', 'Platform Admin', 'shield-check', 'admin.*'),
                    ],
                ]] : []),
                [
                    'label' => 'Workspace',
                    'collapsible' => false,
                    'items' => [
                        self::item('team.index', 'Team', 'users', 'team.*'),
                        self::item('notifications.index', 'Notifications', 'inbox-stack', 'notifications.*', $user?->unreadNotifications()->count() ?: null),
                        self::item('profile.edit', 'Settings', 'cog-6-tooth', 'profile.*'),
                        self::item('billing.index', 'Plans', 'credit-card', 'billing.*'),
                    ],
                ],
            ],
            'mobile' => [
                self::mobile('dashboard', 'Home', 'home', 'dashboard'),
                self::mobile('projects.index', 'Projects', 'folder', 'projects.*'),
                self::mobile('assessments.index', 'Assess', 'clipboard-document-list', 'assessments.*'),
                self::mobile('reports.index', 'Reports', 'chart-bar', 'reports.*'),
                self::mobile('profile.edit', 'More', 'cog-6-tooth', 'profile.*'),
            ],
            'footer' => [
                'label' => 'Current plan',
                'value' => PlanService::planLabel($plan),
                'accent' => false,
                // The crossing to Platform Admin is a navigation item now, not a footnote.
                'link' => null,
            ],
        ];
    }

    /**
     * @return NavItem
     */
    private static function item(string $route, string $label, string $icon, string $active, ?int $badge = null): array
    {
        return compact('route', 'label', 'icon', 'active', 'badge');
    }

    /**
     * @return array{route: string, label: string, icon: string, active: string}
     */
    private static function mobile(string $route, string $label, string $icon, string $active): array
    {
        return compact('route', 'label', 'icon', 'active');
    }
}
