<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ReportSchedule;
use App\Services\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportScheduleController extends Controller
{
    /**
     * Create a recurring report email for a project.
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        if (! PlanService::workspaceCanAccess(app('current.workspace'), 'shareable_report_links')) {
            return back()->with('error', 'Scheduled reports are not available on your current plan. Upgrade to schedule report delivery.');
        }

        $validated = $request->validate([
            'recipient_email' => ['required', 'email', 'max:255'],
            'frequency' => ['required', 'in:'.implode(',', ReportSchedule::FREQUENCIES)],
        ]);

        $schedule = new ReportSchedule([
            'project_id' => $project->project_id,
            'recipient_email' => $validated['recipient_email'],
            'frequency' => $validated['frequency'],
            'is_active' => true,
            'created_by' => $request->user()->user_id,
        ]);
        // The first send follows the chosen cadence from now.
        $schedule->next_run_at = $schedule->advanceFrom(now());
        $schedule->save();

        return back()->with('success', 'Scheduled report created.');
    }

    public function destroy(Project $project, ReportSchedule $reportSchedule): RedirectResponse
    {
        $this->authorize('update', $project);
        if ($reportSchedule->project_id !== $project->project_id) {
            abort(404);
        }

        $reportSchedule->delete();

        return back()->with('success', 'Scheduled report removed.');
    }
}
