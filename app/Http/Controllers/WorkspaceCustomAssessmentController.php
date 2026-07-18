<?php

namespace App\Http\Controllers;

use App\Models\WorkspaceCustomAssessmentDesign;
use App\Services\PlanService;
use App\Services\WorkspaceCustomAssessmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceCustomAssessmentController extends Controller
{
    public function index(): View
    {
        $workspace = app('current.workspace');
        abort_unless(PlanService::workspaceCanAccess($workspace, 'workspace_custom_assessments'), 403);

        $designs = WorkspaceCustomAssessmentDesign::where('workspace_id', $workspace->workspace_id)
            ->latest()
            ->paginate(20);

        return view('custom-assessments.index', compact('designs'));
    }

    public function create(): View
    {
        abort_unless(PlanService::workspaceCanAccess(app('current.workspace'), 'workspace_custom_assessments'), 403);

        return view('custom-assessments.create');
    }

    public function store(Request $request, WorkspaceCustomAssessmentService $service): RedirectResponse
    {
        $workspace = app('current.workspace');
        abort_unless(PlanService::workspaceCanAccess($workspace, 'workspace_custom_assessments'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'purpose' => ['required', 'string', 'max:2000'],
            'scope' => ['nullable', 'string', 'max:180'],
            'setting' => ['nullable', 'string', 'max:180'],
            'target_population' => ['nullable', 'string', 'max:180'],
            'respondent_type' => ['nullable', 'string', 'max:180'],
            'sections_text' => ['nullable', 'string'],
            'questions_text' => ['nullable', 'string'],
            'descriptive_outputs_text' => ['nullable', 'string'],
        ]);

        $design = $service->createDraft($workspace, $request->user(), [
            ...$validated,
            'sections' => $this->lines($validated['sections_text'] ?? ''),
            'questions' => $this->lines($validated['questions_text'] ?? ''),
            'descriptive_outputs' => $this->lines($validated['descriptive_outputs_text'] ?? ''),
            'private_scoring_config' => ['claims_official_vytte_score' => false],
        ]);

        return redirect()->route('custom-assessments.show', $design)
            ->with('success', 'Custom assessment draft created.');
    }

    public function show(WorkspaceCustomAssessmentDesign $customAssessment): View
    {
        $this->authorizeWorkspace($customAssessment);

        return view('custom-assessments.show', ['design' => $customAssessment]);
    }

    public function updateStatus(Request $request, WorkspaceCustomAssessmentDesign $customAssessment): RedirectResponse
    {
        $this->authorizeWorkspace($customAssessment);

        $validated = $request->validate([
            'status' => ['required', 'in:DRAFT,ACTIVE,ARCHIVED'],
        ]);

        $customAssessment->update(['status' => $validated['status']]);

        return back()->with('success', 'Custom assessment status updated.');
    }

    private function authorizeWorkspace(WorkspaceCustomAssessmentDesign $design): void
    {
        $workspace = app('current.workspace');
        abort_unless($workspace && $design->workspace_id === $workspace->workspace_id, 403);
        abort_unless(PlanService::workspaceCanAccess($workspace, 'workspace_custom_assessments'), 403);
    }

    private function lines(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}
