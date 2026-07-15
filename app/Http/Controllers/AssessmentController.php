<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentModule;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentTier;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentController extends Controller
{
    public function create(Project $project): View
    {
        $target = $project->targets->first();

        $modules = $target
            ? AssessmentModule::where('target_type_code', $target->target_type_code)->get()
            : collect();

        return view('assessments.create', compact('project', 'modules'));
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'module_id' => ['required', 'integer', 'exists:assessment_modules,module_id'],
        ]);

        $target = $project->targets->first();

        if (! $target) {
            return back()->with('error', 'This project has no target. Please add a target first.');
        }

        $tier = AssessmentTier::where('tier_code', 'TIER_1')->first();

        $assessment = DB::transaction(function () use ($validated, $project, $target, $tier) {
            $assessment = Assessment::create([
                'target_id' => $target->target_id,
                'project_id' => $project->project_id,
                'assessment_tier_id' => $tier->assessment_tier_id,
                'scope_type' => 'FULL_TARGET',
                'status' => 'IN_PROGRESS',
                'publish_status' => 'DRAFT',
                'assessor_name' => auth()->user()->name,
                'started_at' => now(),
            ]);

            AssessmentModuleScope::create([
                'assessment_id' => $assessment->assessment_id,
                'module_id' => $validated['module_id'],
                'in_scope' => true,
                'is_category_default' => true,
                'status' => 'PENDING',
            ]);

            return $assessment;
        });

        return redirect()->route('assessments.run', $assessment);
    }

    public function run(Assessment $assessment): View
    {
        $this->authorizeWorkspace($assessment);

        $assessment->load(['project', 'target', 'moduleScope.module']);

        return view('assessments.run', compact('assessment'));
    }

    public function submit(Assessment $assessment): RedirectResponse
    {
        $this->authorizeWorkspace($assessment);

        if ($assessment->status === 'COMPLETE') {
            return redirect()->route('projects.show', $assessment->project_id)
                ->with('success', 'Assessment already submitted.');
        }

        $assessment->update([
            'status' => 'COMPLETE',
            'completed_at' => now(),
        ]);

        AssessmentModuleScope::where('assessment_id', $assessment->assessment_id)
            ->update(['status' => 'COMPLETED', 'completed_at' => now()]);

        return redirect()->route('projects.show', $assessment->project_id)
            ->with('success', 'Assessment submitted.');
    }

    private function authorizeWorkspace(Assessment $assessment): void
    {
        $workspace = app('current.workspace');
        $project = Project::withoutGlobalScopes()->find($assessment->project_id);

        if ($project && $project->workspace_id !== $workspace->workspace_id) {
            abort(404);
        }
    }
}
