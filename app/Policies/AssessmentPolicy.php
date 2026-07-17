<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Auth\Access\Response;

class AssessmentPolicy
{
    public function view(User $user, Assessment $assessment): Response
    {
        if (! app()->bound('current.workspace')) {
            return Response::denyAsNotFound();
        }

        $workspaceId = app('current.workspace')->workspace_id;
        $isMember = WorkspaceMember::where('workspace_id', $workspaceId)
            ->where('user_id', $user->user_id)
            ->exists();
        $projectMatches = Project::withoutGlobalScopes()
            ->where('project_id', $assessment->project_id)
            ->where('workspace_id', $workspaceId)
            ->exists();

        return $isMember && $projectMatches
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Assessment $assessment): Response
    {
        return $this->view($user, $assessment);
    }
}
