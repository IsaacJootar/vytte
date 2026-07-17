<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->forgetInstance('current.workspace');
        $user = $request->user();

        if ($user && $user->active_workspace_id) {
            $workspace = Workspace::where('workspace_id', $user->active_workspace_id)->first();

            $isMember = WorkspaceMember::where('workspace_id', $user->active_workspace_id)
                ->where('user_id', $user->user_id)
                ->exists();

            if ($workspace && $isMember) {
                app()->instance('current.workspace', $workspace);
                view()->share('currentWorkspace', $workspace);
            }
        }

        return $next($request);
    }
}
