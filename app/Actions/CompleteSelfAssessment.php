<?php

namespace App\Actions;

use App\Models\Assessment;
use App\Models\AssessmentModuleScope;
use App\Models\WorkspaceMember;
use App\Notifications\AssessmentCompletedNotification;
use App\Services\AuditService;
use App\Services\ReportSnapshotService;
use App\Services\ScoringService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Finishes a self-assessment: freezes it complete, scores it, snapshots the report and
 * notifies the workspace admins.
 *
 * Extracted so both the normal submit and the "finish after answering local questions"
 * path complete an assessment identically. Only the published governed questions drive the
 * published score here; the local questions section is scored as an optional local score
 * before this runs.
 */
class CompleteSelfAssessment
{
    public function handle(Assessment $assessment): void
    {
        DB::transaction(function () use ($assessment): void {
            $completedAt = now();
            $assessment->update(['status' => Assessment::STATUS_COMPLETE, 'completed_at' => $completedAt]);
            AssessmentModuleScope::where('assessment_id', $assessment->assessment_id)
                ->where('in_scope', true)
                ->update(['status' => AssessmentModuleScope::STATUS_COMPLETED, 'completed_at' => $completedAt]);
            app(ScoringService::class)->calculate($assessment);
            app(ReportSnapshotService::class)->createFor($assessment->fresh());
            app(AuditService::class)->record(
                'assessment.completed',
                $assessment,
                ['status' => Assessment::STATUS_IN_PROGRESS],
                ['status' => Assessment::STATUS_COMPLETE, 'completed_at' => $completedAt->toIso8601String()],
            );
        });

        $admins = WorkspaceMember::where('workspace_id', app('current.workspace')->workspace_id)
            ->whereIn('role', ['OWNER', 'ADMIN'])
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        Notification::send($admins, new AssessmentCompletedNotification($assessment));
    }
}
