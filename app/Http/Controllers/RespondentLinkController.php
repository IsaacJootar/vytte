<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentRespondentToken;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class RespondentLinkController extends Controller
{
    public function store(Assessment $assessment): RedirectResponse
    {
        $workspace = app('current.workspace');
        $project = Project::withoutGlobalScopes()->find($assessment->project_id);

        if (! $project || $project->workspace_id !== $workspace->workspace_id) {
            abort(404);
        }

        if ($assessment->status !== 'IN_PROGRESS') {
            return back()->with('error', 'Respondent links can only be created for in-progress assessments.');
        }

        $token = Str::random(32);

        AssessmentRespondentToken::create([
            'token' => $token,
            'assessment_id' => $assessment->assessment_id,
        ]);

        return back()->with('respondent_link', route('respondent.show', $token));
    }
}
