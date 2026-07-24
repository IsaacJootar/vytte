<?php

namespace App\Services;

use App\Models\ActionUpdate;
use App\Models\Assessment;
use App\Models\AssessmentAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns frozen recommendations into living actions, and records every change to them.
 *
 * This service is the one place the deterministic report crosses into mutable state. It
 * guards that crossing: an action can only be created from a real recommendation (so the
 * citation rule holds), and every edit to a live action leaves an audit entry and, when the
 * status moves, a history record a later Progress assessment can read.
 */
class ActionService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Create an action from one of an assessment's frozen recommendations.
     *
     * The recommendation is passed by its position in the frozen intelligence, and read
     * back from the snapshot here — never trusted from the request — so the action's
     * provenance is always authentic.
     *
     * @param  array<string, mixed>  $recommendation  a recommendation from the frozen payload
     * @param  array<string, mixed>  $attributes  optional owner/priority/due/title overrides
     */
    public function createFromRecommendation(Assessment $assessment, array $recommendation, string $creatorId, array $attributes = []): AssessmentAction
    {
        $finding = $recommendation['from_finding'] ?? [];
        if (empty($finding['statement'] ?? null)) {
            throw ValidationException::withMessages([
                'recommendation' => 'An action can only be created from a recommendation that cites a finding.',
            ]);
        }

        $action = AssessmentAction::create([
            'assessment_id' => $assessment->assessment_id,
            'project_id' => $assessment->project_id,
            'source_finding_category' => $finding['category'] ?? 'UNKNOWN',
            'source_finding_subject' => $finding['subject'] ?? 'Assessment',
            'source_finding_statement' => $finding['statement'],
            'source_measurement_domain' => $recommendation['measurement_domain'] ?? null,
            'recommendation_statement' => $recommendation['statement'] ?? $finding['statement'],
            'title' => $attributes['title'] ?? ($recommendation['statement'] ?? $finding['statement']),
            'owner_user_id' => $attributes['owner_user_id'] ?? null,
            'priority' => $attributes['priority'] ?? ($recommendation['priority'] ?? 'MEDIUM'),
            'due_date' => $attributes['due_date'] ?? null,
            'status' => AssessmentAction::STATUS_OPEN,
            'created_by' => $creatorId,
        ]);

        $this->audit->record('assessment.action.created', $action, newValues: [
            'assessment_id' => $assessment->assessment_id,
            'source_finding' => $finding['statement'],
            'priority' => $action->priority,
        ]);

        return $action;
    }

    /**
     * Apply changes to a living action. A status move or a note is written to the action's
     * history; a verification stamps who closed it and when.
     *
     * @param  array<string, mixed>  $changes  any of: status, owner_user_id, priority, due_date, title, note, evidence_note
     */
    public function update(AssessmentAction $action, array $changes, string $actorId): AssessmentAction
    {
        return DB::transaction(function () use ($action, $changes, $actorId) {
            $previousStatus = $action->status;
            $newStatus = $changes['status'] ?? $previousStatus;

            if (! in_array($newStatus, AssessmentAction::STATUSES, true)) {
                throw ValidationException::withMessages(['status' => 'Unknown action status.']);
            }

            $fields = array_filter([
                'status' => $newStatus,
                'owner_user_id' => $changes['owner_user_id'] ?? $action->owner_user_id,
                'priority' => $changes['priority'] ?? $action->priority,
                'due_date' => array_key_exists('due_date', $changes) ? $changes['due_date'] : $action->due_date,
                'title' => $changes['title'] ?? $action->title,
            ], fn ($value) => $value !== null);

            // Verification is the terminal state: record who closed it and when. Leaving
            // VERIFIED clears the stamp, because the claim no longer holds.
            if ($newStatus === AssessmentAction::STATUS_VERIFIED && $previousStatus !== AssessmentAction::STATUS_VERIFIED) {
                $fields['verified_by'] = $actorId;
                $fields['verified_at'] = now();
            } elseif ($newStatus !== AssessmentAction::STATUS_VERIFIED && $previousStatus === AssessmentAction::STATUS_VERIFIED) {
                $fields['verified_by'] = null;
                $fields['verified_at'] = null;
            }

            $action->update($fields);

            $note = trim((string) ($changes['note'] ?? ''));
            $evidence = trim((string) ($changes['evidence_note'] ?? ''));
            $statusChanged = $newStatus !== $previousStatus;

            // Only write history when something worth remembering happened.
            if ($statusChanged || $note !== '' || $evidence !== '') {
                ActionUpdate::create([
                    'action_id' => $action->action_id,
                    'workspace_id' => $action->workspace_id,
                    'author_user_id' => $actorId,
                    'note' => $note !== '' ? $note : null,
                    'status_from' => $statusChanged ? $previousStatus : null,
                    'status_to' => $statusChanged ? $newStatus : null,
                    'evidence_note' => $evidence !== '' ? $evidence : null,
                    'created_at' => now(),
                ]);
            }

            $this->audit->record('assessment.action.updated', $action, oldValues: ['status' => $previousStatus], newValues: [
                'status' => $newStatus,
                'note' => $note !== '' ? $note : null,
            ]);

            return $action->refresh();
        });
    }
}
