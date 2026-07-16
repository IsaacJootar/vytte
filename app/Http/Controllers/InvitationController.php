<?php

namespace App\Http\Controllers;

use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class InvitationController extends Controller
{
    public function show(string $token): View|RedirectResponse
    {
        $invite = WorkspaceInvitation::where('token', $token)
            ->with(['workspace', 'invitedBy'])
            ->firstOrFail();

        if ($invite->isAccepted()) {
            return redirect()->route('dashboard')
                ->with('info', 'This invite has already been used.');
        }

        if ($invite->isExpired()) {
            return view('invitations.expired', compact('invite'));
        }

        $user = auth()->user();

        if ($user) {
            $isMember = WorkspaceMember::where('workspace_id', $invite->workspace_id)
                ->where('user_id', $user->user_id)
                ->exists();

            if ($isMember) {
                return redirect()->route('dashboard')
                    ->with('info', 'You are already a member of '.$invite->workspace->name.'.');
            }
        }

        return view('invitations.show', compact('invite', 'user'));
    }

    // GET — protected by auth middleware so intended() redirect works for unauthenticated users
    public function accept(string $token): RedirectResponse
    {
        $invite = WorkspaceInvitation::where('token', $token)
            ->with('workspace')
            ->firstOrFail();

        if ($invite->isAccepted()) {
            return redirect()->route('dashboard')
                ->with('info', 'This invite has already been used.');
        }

        if ($invite->isExpired()) {
            return redirect()->route('dashboard')
                ->with('error', 'This invite has expired. Ask the workspace owner to send a new one.');
        }

        $user = auth()->user();

        WorkspaceMember::firstOrCreate(
            ['workspace_id' => $invite->workspace_id, 'user_id' => $user->user_id],
            ['role' => $invite->role]
        );

        $invite->update(['accepted_at' => now()]);

        $user->update(['active_workspace_id' => $invite->workspace_id]);
        app()->instance('current.workspace', $invite->workspace);

        return redirect()->route('dashboard')
            ->with('success', 'Welcome to '.$invite->workspace->name.'!');
    }
}
