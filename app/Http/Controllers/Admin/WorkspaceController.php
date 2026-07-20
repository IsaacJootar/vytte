<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Workspace;
use App\Services\AuditService;
use App\Services\SessionRevocationService;
use App\Services\WorkspaceHealthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    /**
     * Statuses a workspace can be moved between.
     *
     * ARCHIVED is deliberately terminal in the UI: reopening a closed workspace is a
     * support decision, not a one-click action.
     */
    public const STATUSES = ['ACTIVE', 'SUSPENDED', 'ARCHIVED'];

    public function index(Request $request): View
    {
        $query = Workspace::withCount(['members', 'projects'])
            ->with('ownerMember.user');

        if ($request->filled('search')) {
            $query->whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.$request->search.'%']);
        }

        if ($request->filled('plan')) {
            $query->where('plan', $request->plan);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return view('admin.workspaces.index', [
            'workspaces' => $query->latest()->paginate(25)->withQueryString(),
            'counts' => [
                'total' => Workspace::count(),
                'active' => Workspace::where('status', 'ACTIVE')->count(),
                'suspended' => Workspace::where('status', 'SUSPENDED')->count(),
                'archived' => Workspace::where('status', 'ARCHIVED')->count(),
            ],
            'plans' => Workspace::query()->select('plan')->distinct()->orderBy('plan')->pluck('plan'),
        ]);
    }

    public function show(Workspace $workspace, WorkspaceHealthService $health): View
    {
        $workspace->load(['members.user', 'projects']);

        $projectIds = Project::withoutGlobalScopes()
            ->where('workspace_id', $workspace->workspace_id)
            ->pluck('project_id');

        return view('admin.workspaces.show', [
            'workspace' => $workspace,
            'health' => $health->for($workspace),
            'recentAssessments' => Assessment::withoutGlobalScopes()
                ->whereIn('project_id', $projectIds)
                ->with(['target', 'project'])
                ->orderByDesc('created_at')
                ->limit(15)
                ->get(),
            'recentActivity' => AuditLog::where('workspace_id', $workspace->workspace_id)
                ->with('user')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function updateStatus(
        Request $request,
        Workspace $workspace,
        AuditService $audit,
        SessionRevocationService $sessions,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        if ($workspace->status === $validated['status']) {
            return back()->with('success', 'No change — this workspace is already '.strtolower($validated['status']).'.');
        }

        $oldValues = $workspace->only(['status']);
        $workspace->update(['status' => $validated['status']]);

        // Losing access has to take effect now, not whenever the member's session
        // happens to expire.
        if ($validated['status'] !== 'ACTIVE') {
            $sessions->forWorkspace($workspace);
        }

        $audit->record(
            'workspace.status_updated',
            $workspace,
            $oldValues,
            ['status' => $workspace->status],
            workspaceId: $workspace->workspace_id
        );

        return back()->with('success', match ($validated['status']) {
            'ACTIVE' => 'Workspace reactivated. Its members can use it again.',
            'SUSPENDED' => 'Workspace put on hold. Its members cannot use it until you reactivate it. Nothing was deleted.',
            'ARCHIVED' => 'Workspace closed. Its data is kept but the workspace can no longer be used.',
        });
    }
}
