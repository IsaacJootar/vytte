<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkspaceSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $workspace = app('current.workspace');
        $this->requireAdmin($workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['nullable', 'timezone'],
        ]);

        $settings = $workspace->settings ?? [];
        if (! empty($validated['timezone'])) {
            $settings['timezone'] = $validated['timezone'];
        }

        $workspace->update([
            'name' => $validated['name'],
            'settings' => $settings,
        ]);

        return back()->with('status', 'workspace-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $workspace = app('current.workspace');
        $this->requireOwner($workspace);

        $request->validateWithBag('workspaceDeletion', [
            'confirm_name' => ['required', 'string'],
            'password' => ['required', 'current_password'],
        ]);

        if ($request->confirm_name !== $workspace->name) {
            return back()->withErrors(
                ['confirm_name' => 'The workspace name you entered does not match.'],
                'workspaceDeletion'
            );
        }

        $user = auth()->user();

        $otherWorkspaceId = WorkspaceMember::where('user_id', $user->user_id)
            ->where('workspace_id', '!=', $workspace->workspace_id)
            ->value('workspace_id');

        $workspace->delete();

        if ($otherWorkspaceId) {
            $user->update(['active_workspace_id' => $otherWorkspaceId]);
            app()->forgetInstance('current.workspace');

            return redirect()->route('dashboard')
                ->with('success', 'Workspace deleted.');
        }

        $user->update(['active_workspace_id' => null]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Your workspace has been deleted.');
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
