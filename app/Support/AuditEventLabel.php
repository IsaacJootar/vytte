<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Turns stored audit event keys into sentences an administrator can read.
 *
 * The stored key is unchanged: it remains the durable record. This only affects how the
 * event is presented, so `assessment.question.approved` reads as "Question approved"
 * rather than exposing the dotted key on a dashboard.
 */
final class AuditEventLabel
{
    private const LABELS = [
        'assessment.draft.created' => 'Assessment started',
        'assessment.draft.updated' => 'Assessment details updated',
        'assessment.draft.discarded' => 'Draft discarded',
        'assessment.section.added' => 'Section added',
        'assessment.section.updated' => 'Section updated',
        'assessment.section.removed' => 'Section removed',
        'assessment.question.added' => 'Question added',
        'assessment.question.removed' => 'Question removed',
        'assessment.question.settings_updated' => 'Scoring or evidence changed',
        'assessment.question.approved' => 'Question approved',
        'assessment.scoring_group.created' => 'Score created',
        'assessment.provenance.recorded' => 'Source details recorded',
        'assessment.published' => 'Assessment published',
        'assessment.version.started' => 'New version started',
        'assessment.version.superseded' => 'Version replaced',
        'assessment.release.superseded' => 'Published assessment replaced',
        'assessment.created' => 'Assessment created in a workspace',
        'assessment.report.finalized' => 'Report finalised',
        'assessment.multi_respondent.finalized' => 'Respondent collection finalised',
        'question.identity.created' => 'Question created',
        'question.version.configured' => 'Question edited',
        'question.version.approved' => 'Question approved',
        'question.version.published' => 'Question published',
        'question.version.superseded' => 'Question replaced',
        'question.version.archived' => 'Question archived',
        'department.framework.published' => 'Framework published',
        'department.framework.superseded' => 'Framework replaced',
        'assessment.catalogue.published' => 'Catalogue release published',
        'domain.taxonomy.published' => 'Analytical domains published',
        'workspace.status_updated' => 'Workspace access changed',
        'platform.user.role_updated' => 'Platform access changed',
        'platform.user.suspended' => 'Account suspended',
        'platform.user.reactivated' => 'Account restored',
        'platform.report_link.revoked' => 'Shared report link revoked',
    ];

    /**
     * Which part of the platform an event belongs to.
     *
     * Grouping is derived from the event key rather than stored, so a new event gets a
     * sensible category without a migration. Anything unrecognised falls into "Other",
     * which is honest — better than filing it under a category it may not belong to.
     */
    private const CATEGORY_PREFIXES = [
        'Publishing' => ['assessment.published', 'assessment.catalogue', 'question.version.published', 'department.framework', 'domain.taxonomy'],
        'Approvals' => ['assessment.question.approved', 'question.version.approved'],
        'Content' => ['assessment.', 'question.'],
        'Workspaces' => ['workspace.'],
        'Security' => ['platform.user', 'platform.report_link'],
    ];

    public const CATEGORIES = ['Publishing', 'Approvals', 'Content', 'Workspaces', 'Security', 'Other'];

    public static function for(?string $event): string
    {
        if (! $event) {
            return 'Activity';
        }

        return self::LABELS[$event] ?? Str::of($event)->replace(['.', '_'], ' ')->ucfirst()->value();
    }

    public static function categoryFor(?string $event): string
    {
        if (! $event) {
            return 'Other';
        }

        foreach (self::CATEGORY_PREFIXES as $category => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($event, $prefix)) {
                    return $category;
                }
            }
        }

        return 'Other';
    }

    /**
     * The stored event keys that belong to a category, for filtering a query.
     *
     * @return array<int, string>
     */
    public static function prefixesForCategory(string $category): array
    {
        return self::CATEGORY_PREFIXES[$category] ?? [];
    }
}
