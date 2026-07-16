<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentModule;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentTier;
use App\Models\Project;
use App\Services\ScoringService;
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

        app(ScoringService::class)->calculate($assessment);

        return redirect()->route('projects.show', $assessment->project_id)
            ->with('success', 'Assessment submitted.');
    }

    public function results(Assessment $assessment): View|RedirectResponse
    {
        $this->authorizeWorkspace($assessment);

        if ($assessment->status !== 'COMPLETE') {
            return redirect()->route('assessments.run', $assessment);
        }

        $assessment->load([
            'project',
            'target.targetType',
            'moduleScope.module',
            'score.maturityLevel',
        ]);

        $scope = $assessment->moduleScope->first();
        $module = $scope?->module;

        $subIndexScores = DB::table('sub_index_scores as sis')
            ->join('sub_indices as si', 'si.sub_index_id', '=', 'sis.sub_index_id')
            ->join('domains as d', 'd.domain_id', '=', 'si.domain_id')
            ->where('sis.assessment_id', $assessment->assessment_id)
            ->where('sis.respondent_type', 'STAFF')
            ->select('sis.*', 'si.acronym', 'si.full_name', 'si.description', 'd.domain_name', 'd.domain_code')
            ->orderBy('d.domain_code')
            ->orderBy('si.acronym')
            ->get();

        $domainScores = DB::table('domain_scores as ds')
            ->join('domains as d', 'd.domain_id', '=', 'ds.domain_id')
            ->where('ds.assessment_id', $assessment->assessment_id)
            ->select('ds.*', 'd.domain_name', 'd.domain_code')
            ->orderBy('d.domain_code')
            ->get();

        $history = collect();
        if ($module) {
            $history = Assessment::where('project_id', $assessment->project_id)
                ->where('status', 'COMPLETE')
                ->whereHas('moduleScope', fn ($q) => $q->where('module_id', $module->module_id))
                ->with('score')
                ->orderBy('completed_at')
                ->get();
        }

        return view('assessments.results', compact('assessment', 'subIndexScores', 'domainScores', 'history'));
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
