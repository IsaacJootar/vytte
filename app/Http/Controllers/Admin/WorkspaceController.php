<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
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

        $workspaces = $query->latest()->paginate(25)->withQueryString();

        return view('admin.workspaces.index', compact('workspaces'));
    }

    public function show(Workspace $workspace): View
    {
        $workspace->load(['members.user', 'projects.assessments.score']);

        return view('admin.workspaces.show', compact('workspace'));
    }

    public function updateStatus(Request $request, Workspace $workspace, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['ACTIVE', 'SUSPENDED'])],
        ]);

        $oldValues = $workspace->only(['status']);
        $workspace->update(['status' => $validated['status']]);
        $audit->record('workspace.status_updated', $workspace, $oldValues, ['status' => $workspace->status], workspaceId: $workspace->workspace_id);

        return back()->with('success', 'Workspace status updated.');
    }
}
