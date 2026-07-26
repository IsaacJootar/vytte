<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;

class ActivityController extends Controller
{
    /**
     * A plain-language activity feed for the current workspace: who did what, and when.
     * It reads the same audit trail the platform admin sees, scoped to this workspace and
     * to the events that matter to a workspace member.
     */
    public function index(): View
    {
        $workspaceId = app('current.workspace')->workspace_id;

        $activities = AuditLog::with('user')
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('activity.index', compact('activities'));
    }
}
