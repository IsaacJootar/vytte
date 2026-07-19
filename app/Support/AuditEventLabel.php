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
    ];

    public static function for(?string $event): string
    {
        if (! $event) {
            return 'Activity';
        }

        return self::LABELS[$event] ?? Str::of($event)->replace(['.', '_'], ' ')->ucfirst()->value();
    }
}
