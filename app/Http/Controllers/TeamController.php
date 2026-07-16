<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    public function index(): View
    {
        $workspace = app('current.workspace');

        $members = WorkspaceMember::where('workspace_id', $workspace->workspace_id)
            ->with('user')
            ->orderByRaw("CASE role WHEN 'OWNER' THEN 0 WHEN 'ADMIN' THEN 1 ELSE 2 END")
            ->get();

        $pendingInvites = WorkspaceInvitation::where('workspace_id', $workspace->workspace_id)
            ->whereNull('accepted_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('invitedBy')
            ->latest()
            ->get();

        $currentMember = $members->firstWhere('user_id', auth()->id());

        return view('team.index', compact('members', 'pendingInvites', 'currentMember', 'workspace'));
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = app('current.workspace');
        $this->requireAdmin($workspace);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:ADMIN,MEMBER'],
        ]);

        // Already a member?
        $existingUser = User::where('email', $validated['email'])->first();
        if ($existingUser) {
            $alreadyMember = WorkspaceMember::where('workspace_id', $workspace->workspace_id)
                ->where('user_id', $existingUser->user_id)
                ->exists();

            if ($alreadyMember) {
                return back()->with('error', $validated['email'].' is already a member of this workspace.');
            }
        }

        // Duplicate pending invite?
        $existingInvite = WorkspaceInvitation::where('workspace_id', $workspace->workspace_id)
            ->where('email', $validated['email'])
            ->whereNull('accepted_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if ($existingInvite) {
            return back()->with('error', 'A pending invite already exists for '.$validated['email'].'.');
        }

        $invite = WorkspaceInvitation::create([
            'workspace_id' => $workspace->workspace_id,
            'email' => $validated['email'],
            'role' => $validated['role'],
            'token' => Str::random(64),
            'invited_by' => auth()->id(),
            'expires_at' => now()->addDays(7),
        ]);

        return back()
            ->with('success', 'Invite created for '.$validated['email'].'.')
            ->with('invite_link', route('invitations.show', $invite->token));
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $workspace = app('current.workspace');
        $this->requireOwner($workspace);

        if ($user->user_id === auth()->id()) {
            return back()->with('error', 'You cannot change your own role.');
        }

        $member = WorkspaceMember::where('workspace_id', $workspace->workspace_id)
            ->where('user_id', $user->user_id)
            ->firstOrFail();

        if ($member->role === 'OWNER') {
            return back()->with('error', 'You cannot change the role of another workspace owner.');
        }

        $validated = $request->validate([
            'role' => ['required', 'in:ADMIN,MEMBER'],
        ]);

        WorkspaceMember::where('workspace_id', $workspace->workspace_id)
            ->where('user_id', $user->user_id)
            ->update(['role' => $validated['role']]);

        return back()->with('success', 'Role updated for '.$user->name.'.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $workspace = app('current.workspace');

        if ($user->user_id === auth()->id()) {
            return back()->with('error', 'You cannot remove yourself from the workspace.');
        }

        $currentMember = WorkspaceMember::where('workspace_id', $workspace->workspace_id)
            ->where('user_id', auth()->id())
            ->first();

        if (! in_array($currentMember?->role, ['OWNER', 'ADMIN'])) {
            abort(403);
        }

        $targetMember = WorkspaceMember::where('workspace_id', $workspace->workspace_id)
            ->where('user_id', $user->user_id)
            ->firstOrFail();

        if ($currentMember->role === 'ADMIN' && in_array($targetMember->role, ['OWNER', 'ADMIN'])) {
            return back()->with('error', 'Admins can only remove regular members.');
        }

        WorkspaceMember::where('workspace_id', $workspace->workspace_id)
            ->where('user_id', $user->user_id)
            ->delete();

        return back()->with('success', $user->name.' has been removed from the workspace.');
    }

    public function cancelInvite(WorkspaceInvitation $invitation): RedirectResponse
    {
        $workspace = app('current.workspace');
        $this->requireAdmin($workspace);

        if ($invitation->workspace_id !== $workspace->workspace_id) {
            abort(404);
        }

        $invitation->delete();

        return back()->with('success', 'Invite cancelled.');
    }

    private function requireAdmin(Workspace $workspace): void
    {
        $role = WorkspaceMember::where('workspace_id', $workspace->workspace_id)
            ->where('user_id', auth()->id())
            ->value('role');

        if (! in_array($role, ['OWNER', 'ADMIN'])) {
            abort(403);
        }
    }

    private function requireOwner(Workspace $workspace): void
    {
        $role = WorkspaceMember::where('workspace_id', $workspace->workspace_id)
            ->where('user_id', auth()->id())
            ->value('role');

        if ($role !== 'OWNER') {
            abort(403);
        }
    }
}
