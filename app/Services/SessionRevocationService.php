<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;

/**
 * Ends the sessions of people who have just lost access.
 *
 * Blocking sign-in is not the same as removing access. Somebody already signed in when
 * they are suspended keeps working until their session happens to expire, which can be
 * days. For a control whose whole purpose is to stop someone using the product, that
 * gap makes the control untrue.
 *
 * Only valid while sessions live in the database. If the session driver moves to cookies,
 * there is no server-side record to delete and this stops working silently — hence the
 * explicit guard rather than a quiet no-op.
 */
class SessionRevocationService
{
    public function forUser(User $user): int
    {
        if (! $this->canRevoke()) {
            return 0;
        }

        return DB::table('sessions')->where('user_id', $user->user_id)->delete();
    }

    /**
     * Ends the sessions of everyone in a workspace.
     *
     * Members of more than one workspace are signed out too. That is the correct
     * trade: they can sign straight back in and use their other workspaces, whereas
     * leaving the session alive would let them keep working inside the suspended one.
     */
    public function forWorkspace(Workspace $workspace): int
    {
        if (! $this->canRevoke()) {
            return 0;
        }

        $userIds = WorkspaceMember::where('workspace_id', $workspace->workspace_id)
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            return 0;
        }

        return DB::table('sessions')->whereIn('user_id', $userIds)->delete();
    }

    /**
     * Whether sessions are stored somewhere this service can reach.
     */
    public function canRevoke(): bool
    {
        return config('session.driver') === 'database';
    }
}
