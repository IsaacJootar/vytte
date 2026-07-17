<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    public function view(User $user, Project $project): Response
    {
        if (! app()->bound('current.workspace')) {
            return Response::denyAsNotFound();
        }

        $workspaceId = app('current.workspace')->workspace_id;
        $allowed = $project->workspace_id === $workspaceId
            && WorkspaceMember::where('workspace_id', $workspaceId)
                ->where('user_id', $user->user_id)
                ->exists();

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Project $project): Response
    {
        return $this->view($user, $project);
    }
}
