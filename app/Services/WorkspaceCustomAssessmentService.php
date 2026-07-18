<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceCustomAssessmentDesign;
use Illuminate\Validation\ValidationException;

class WorkspaceCustomAssessmentService
{
    public function createDraft(Workspace $workspace, User $user, array $data): WorkspaceCustomAssessmentDesign
    {
        if ($user->active_workspace_id !== $workspace->workspace_id && ! $workspace->members()->where('user_id', $user->user_id)->exists()) {
            throw ValidationException::withMessages(['workspace' => 'You can only create custom assessments in your workspace.']);
        }

        $privateScoring = $data['private_scoring_config'] ?? null;
        if (is_array($privateScoring) && ($privateScoring['claims_official_vytte_score'] ?? false)) {
            throw ValidationException::withMessages(['private_scoring_config' => 'Workspace custom scoring cannot claim to be an official Vytte score.']);
        }

        return WorkspaceCustomAssessmentDesign::create([
            'workspace_id' => $workspace->workspace_id,
            'title' => $data['title'],
            'purpose' => $data['purpose'],
            'scope' => $data['scope'] ?? null,
            'setting' => $data['setting'] ?? null,
            'target_population' => $data['target_population'] ?? null,
            'respondent_type' => $data['respondent_type'] ?? null,
            'status' => WorkspaceCustomAssessmentDesign::STATUS_DRAFT,
            'sections' => $data['sections'] ?? [],
            'indicators' => $data['indicators'] ?? [],
            'questions' => $data['questions'] ?? [],
            'evidence_requests' => $data['evidence_requests'] ?? [],
            'descriptive_outputs' => $data['descriptive_outputs'] ?? [],
            'private_scoring_config' => $privateScoring,
            'ai_drafting_context' => $data['ai_drafting_context'] ?? null,
            'created_by' => $user->user_id,
        ]);
    }
}
