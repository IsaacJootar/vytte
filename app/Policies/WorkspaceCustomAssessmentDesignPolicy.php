<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkspaceCustomAssessmentDesign;

class WorkspaceCustomAssessmentDesignPolicy
{
    public function view(User $user, WorkspaceCustomAssessmentDesign $design): bool
    {
        return $this->belongsToWorkspace($user, $design);
    }

    public function update(User $user, WorkspaceCustomAssessmentDesign $design): bool
    {
        return $design->status === WorkspaceCustomAssessmentDesign::STATUS_DRAFT
            && $this->belongsToWorkspace($user, $design);
    }

    private function belongsToWorkspace(User $user, WorkspaceCustomAssessmentDesign $design): bool
    {
        return $user->active_workspace_id === $design->workspace_id
            || $design->workspace->members()->where('user_id', $user->user_id)->exists();
    }
}
