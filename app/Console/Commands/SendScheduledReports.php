<?php

namespace App\Console\Commands;

use App\Models\Assessment;
use App\Models\Project;
use App\Models\ReportSchedule;
use App\Services\ReportDeliveryService;
use Illuminate\Console\Command;

class SendScheduledReports extends Command
{
    protected $signature = 'reports:send-scheduled';

    protected $description = 'Email the latest report to recipients whose schedule is due';

    public function handle(ReportDeliveryService $delivery): int
    {
        // Read across workspaces without the tenant scope — this is a system job, not a user
        // request. Each schedule carries its own workspace, project and recipient.
        $due = ReportSchedule::withoutGlobalScopes()
            ->where('is_active', true)
            ->where('next_run_at', '<=', now())
            ->get();

        $sent = 0;
        foreach ($due as $schedule) {
            $assessment = $this->latestCompletedAssessment($schedule->project_id);

            if ($assessment !== null) {
                try {
                    $delivery->sendForAssessment(
                        $assessment,
                        $schedule->recipient_email,
                        message: null,
                        scheduled: true,
                        creatorId: $schedule->created_by,
                    );
                    $sent++;
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            // Advance the cadence whether or not a report existed, so a project with no
            // completed assessment yet does not fire every minute.
            $schedule->update([
                'last_run_at' => now(),
                'next_run_at' => $schedule->advanceFrom(now()),
            ]);
        }

        $this->info("Scheduled reports processed: {$due->count()}, sent: {$sent}.");

        return self::SUCCESS;
    }

    private function latestCompletedAssessment(string $projectId): ?Assessment
    {
        return Assessment::withoutGlobalScopes()
            ->where('project_id', $projectId)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->with('target')
            ->orderByDesc('completed_at')
            ->first();
    }
}
