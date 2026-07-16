<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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

        $workspaces = $query->latest()->paginate(25)->withQueryString();

        return view('admin.workspaces.index', compact('workspaces'));
    }

    public function show(Workspace $workspace): View
    {
        $workspace->load(['members.user', 'projects.assessments.score']);

        return view('admin.workspaces.show', compact('workspace'));
    }
}
