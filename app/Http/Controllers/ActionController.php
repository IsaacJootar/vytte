<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentAction;
use App\Models\Project;
use App\Models\WorkspaceMember;
use App\Services\ActionService;
use App\Services\ReportSnapshotService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActionController extends Controller
{
    public function __construct(private readonly ActionService $actions) {}

    /**
     * The action plan for a project — every action drawn from its assessments, grouped by
     * how finished it is so the open work leads.
     */
    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        $actions = AssessmentAction::where('project_id', $project->project_id)
            ->with(['owner', 'assessment.target', 'updates.author'])
            ->orderByRaw("array_position(ARRAY['OPEN','IN_PROGRESS','DONE','VERIFIED']::text[], status)")
            ->orderByRaw('due_date is null, due_date')
            ->get();

        $members = $this->workspaceMembers();

        return view('actions.index', compact('project', 'actions', 'members'));
    }

    /**
     * Turn one of an assessment's frozen recommendations into a living action.
     */
    public function store(Request $request, Assessment $assessment, ReportSnapshotService $reports): RedirectResponse
    {
        $this->authorize('update', $assessment);

        $validated = $request->validate([
            'recommendation_index' => ['required', 'integer', 'min:0'],
            'owner_user_id' => ['nullable', 'uuid'],
            'priority' => ['nullable', 'in:HIGH,MEDIUM,LOW'],
            'due_date' => ['nullable', 'date'],
        ]);

        $payload = $reports->payloadFor($assessment);
        $recommendations = $payload['intelligence']['recommendations'] ?? [];
        $recommendation = $recommendations[$validated['recommendation_index']] ?? null;

        if (! $recommendation) {
            return back()->with('error', 'That recommendation is no longer part of this report.');
        }

        $this->actions->createFromRecommendation($assessment, $recommendation, (string) $request->user()->user_id, [
            'owner_user_id' => $this->validMemberId($validated['owner_user_id'] ?? null),
            'priority' => $validated['priority'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
        ]);

        return back()->with('success', 'Added to the action plan.');
    }

    /**
     * Move an action along its lifecycle, reassign it, or log progress against it.
     */
    public function update(Request $request, AssessmentAction $action): RedirectResponse
    {
        $this->authorize('view', $action->project);

        $validated = $request->validate([
            'status' => ['nullable', 'in:OPEN,IN_PROGRESS,DONE,VERIFIED'],
            'owner_user_id' => ['nullable', 'uuid'],
            'priority' => ['nullable', 'in:HIGH,MEDIUM,LOW'],
            'due_date' => ['nullable', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'evidence_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if (array_key_exists('owner_user_id', $validated)) {
            $validated['owner_user_id'] = $this->validMemberId($validated['owner_user_id']);
        }

        $this->actions->update($action, $validated, (string) $request->user()->user_id);

        return back()->with('success', 'Action updated.');
    }

    public function destroy(AssessmentAction $action): RedirectResponse
    {
        $this->authorize('view', $action->project);

        $action->delete();

        return back()->with('success', 'Action removed from the plan.');
    }

    /**
     * Members of the current workspace, for the owner picker.
     */
    private function workspaceMembers()
    {
        $workspaceId = app('current.workspace')->workspace_id;

        return WorkspaceMember::where('workspace_id', $workspaceId)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->values();
    }

    /**
     * Guard the owner assignment: only a real member of this workspace may own an action.
     */
    private function validMemberId(?string $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        $isMember = WorkspaceMember::where('workspace_id', app('current.workspace')->workspace_id)
            ->where('user_id', $userId)
            ->exists();

        return $isMember ? $userId : null;
    }
}
