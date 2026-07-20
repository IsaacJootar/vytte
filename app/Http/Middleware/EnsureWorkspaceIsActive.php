<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspended or archived workspace cannot be worked in.
 *
 * Before this, Platform Admin could set a workspace to SUSPENDED and nothing changed
 * for its members — the control recorded a decision it never enforced. Suspension now
 * stops the workspace being used while leaving every row intact, so the state is fully
 * reversible and nothing is lost.
 *
 * Read-only account routes stay reachable so a suspended customer can still sign out,
 * read the explanation, and reach billing to resolve it.
 */
class EnsureWorkspaceIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->bound('current.workspace')) {
            return $next($request);
        }

        $workspace = app('current.workspace');

        if ($workspace->isActive()) {
            return $next($request);
        }

        // A platform administrator has to be able to look into a suspended workspace,
        // otherwise suspension would hide the very thing they need to review.
        if ($request->user()?->isPlatformAdmin()) {
            return $next($request);
        }

        return response()->view('workspace-suspended', [
            'workspace' => $workspace,
        ], 403);
    }
}
