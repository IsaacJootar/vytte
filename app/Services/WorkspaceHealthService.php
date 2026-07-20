<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

/**
 * Answers one question about a workspace: is this customer actually getting value?
 *
 * A support conversation starts with "are they using it, and is anything stuck?" — not
 * with row counts. Each signal here is phrased as a plain statement an administrator can
 * act on, with a tone that says whether it needs attention.
 *
 * This service decides nothing. It reports; the administrator judges.
 */
class WorkspaceHealthService
{
    /**
     * A workspace with no activity for this long is treated as having gone quiet.
     */
    private const QUIET_AFTER_DAYS = 30;

    /**
     * An assessment left open for longer than this is treated as stalled.
     */
    private const STALLED_AFTER_DAYS = 21;

    /**
     * @return array{summary: array{label: string, tone: string, detail: string}, signals: array<int, array{label: string, detail: string, tone: string}>, stats: array<string, int|string|null>}
     */
    public function for(Workspace $workspace): array
    {
        $projectIds = Project::withoutGlobalScopes()
            ->where('workspace_id', $workspace->workspace_id)
            ->pluck('project_id');

        $assessments = Assessment::withoutGlobalScopes()
            ->whereIn('project_id', $projectIds)
            ->get(['assessment_id', 'status', 'created_at', 'completed_at']);

        $memberCount = $workspace->members()->count();
        $completed = $assessments->whereNotNull('completed_at')->count();
        $inProgress = $assessments->whereNull('completed_at')->count();

        $lastActivity = $assessments->max('created_at');
        $lastActivity = $lastActivity ? Carbon::parse($lastActivity) : null;

        $stalled = $assessments
            ->whereNull('completed_at')
            ->filter(fn ($a) => $a->created_at && Carbon::parse($a->created_at)->lt(now()->subDays(self::STALLED_AFTER_DAYS)))
            ->count();

        $signals = [];

        if ($memberCount <= 1) {
            $signals[] = [
                'label' => 'Only one person has joined',
                'detail' => 'Workspaces with a single member often stall. This may be a good candidate for onboarding help.',
                'tone' => 'warning',
            ];
        }

        if ($projectIds->isEmpty()) {
            $signals[] = [
                'label' => 'No projects created yet',
                'detail' => 'The workspace exists but nothing has been set up in it.',
                'tone' => 'warning',
            ];
        }

        if ($assessments->isEmpty() && $projectIds->isNotEmpty()) {
            $signals[] = [
                'label' => 'Projects exist but no assessment has been run',
                'detail' => 'They have set up but not started measuring.',
                'tone' => 'warning',
            ];
        }

        if ($stalled > 0) {
            $signals[] = [
                'label' => $stalled.' '.str('assessment')->plural($stalled).' left unfinished',
                'detail' => 'Started more than '.self::STALLED_AFTER_DAYS.' days ago and never completed.',
                'tone' => 'warning',
            ];
        }

        if ($lastActivity && $lastActivity->lt(now()->subDays(self::QUIET_AFTER_DAYS))) {
            $signals[] = [
                'label' => 'Quiet for '.$lastActivity->diffForHumans(null, true),
                'detail' => 'No new assessment has been started recently.',
                'tone' => 'warning',
            ];
        }

        if ($completed > 0) {
            $signals[] = [
                'label' => $completed.' '.str('assessment')->plural($completed).' completed',
                'detail' => 'This workspace is producing results.',
                'tone' => 'success',
            ];
        }

        return [
            'summary' => $this->summarise($workspace, $signals, $completed),
            'signals' => $signals,
            'stats' => [
                'members' => $memberCount,
                'projects' => $projectIds->count(),
                'assessments' => $assessments->count(),
                'completed' => $completed,
                'in_progress' => $inProgress,
                'last_activity' => $lastActivity?->diffForHumans(),
            ],
        ];
    }

    /**
     * @param  array<int, array{label: string, detail: string, tone: string}>  $signals
     * @return array{label: string, tone: string, detail: string}
     */
    private function summarise(Workspace $workspace, array $signals, int $completed): array
    {
        if ($workspace->status === 'ARCHIVED') {
            return ['label' => 'Closed', 'tone' => 'slate', 'detail' => 'This workspace has been closed and cannot be used.'];
        }

        if ($workspace->status === 'SUSPENDED') {
            return ['label' => 'On hold', 'tone' => 'moderate', 'detail' => 'Members cannot use this workspace until it is reactivated.'];
        }

        $warnings = count(array_filter($signals, fn ($s) => $s['tone'] === 'warning'));

        if ($warnings === 0 && $completed > 0) {
            return ['label' => 'Healthy', 'tone' => 'strong', 'detail' => 'Active, staffed, and producing results.'];
        }

        if ($warnings >= 2) {
            return ['label' => 'Needs attention', 'tone' => 'weak', 'detail' => $warnings.' things are worth looking at.'];
        }

        if ($warnings === 1) {
            return ['label' => 'Watch', 'tone' => 'moderate', 'detail' => 'One thing is worth looking at.'];
        }

        return ['label' => 'Getting started', 'tone' => 'blue', 'detail' => 'Set up, but no results produced yet.'];
    }
}
