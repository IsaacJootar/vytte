<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Project;
use Illuminate\Contracts\View\View;

class MonitorController extends Controller
{
    /**
     * A hub of every assessment currently out collecting responses, so monitoring is
     * discoverable in its own right rather than buried behind a single assessment.
     */
    public function index(): View
    {
        $workspaceProjectIds = Project::select('project_id');

        $assessments = Assessment::whereIn('project_id', $workspaceProjectIds)
            ->where('publish_status', Assessment::PUBLISH_PUBLISHED)
            ->where('status', Assessment::STATUS_IN_PROGRESS)
            ->with(['project', 'target'])
            ->withCount([
                'publicResponseSessions',
                'publicResponseSessions as completed_sessions_count' => fn ($q) => $q->whereNotNull('submitted_at'),
            ])
            ->latest('updated_at')
            ->paginate(20);

        return view('monitor.index', compact('assessments'));
    }
}
