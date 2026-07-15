<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->active_workspace_id) {
            $workspace = Workspace::where('workspace_id', $user->active_workspace_id)->first();

            if ($workspace) {
                app()->instance('current.workspace', $workspace);
                view()->share('currentWorkspace', $workspace);
            }
        }

        return $next($request);
    }
}
