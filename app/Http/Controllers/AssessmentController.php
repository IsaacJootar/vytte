<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentModule;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentTier;
use App\Models\Project;
use App\Models\WorkspaceMember;
use App\Notifications\AssessmentCompletedNotification;
use App\Services\PlanService;
use App\Services\ScoringService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AssessmentController extends Controller
{
    public function index(): View
    {
        $workspace = app('current.workspace');
        $workspaceProjectIds = Project::select('project_id');

        $assessments = Assessment::whereIn('project_id', $workspaceProjectIds)
            ->with(['project', 'target', 'score', 'moduleScope.module'])
            ->latest('updated_at')
            ->paginate(20);

        return view('assessments.index', compact('assessments'));
    }

    public function create(Project $project): View
    {
        $target = $project->targets->first();

        $defaultModuleIds = $target
            ? DB::table('target_category_default_modules')
                ->where('category_id', $target->category_id)
                ->pluck('module_id')
                ->toArray()
            : [];

        $allModules = $target
            ? AssessmentModule::where('target_type_code', $target->target_type_code)
                ->where('is_active', true)
                ->orderBy('module_code')
                ->get()
            : collect();

        $defaultModules = $allModules->whereIn('module_id', $defaultModuleIds)->values();
        $extraModules = $allModules->whereNotIn('module_id', $defaultModuleIds)->values();

        return view('assessments.create', compact('project', 'defaultModules', 'extraModules', 'defaultModuleIds'));
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $workspace = app('current.workspace');

        if (PlanService::hasReachedAssessmentLimit($workspace, $project)) {
            return redirect()->route('billing.index')
                ->with('limit_error', 'You have reached the assessment limit for this project on your current plan. Upgrade to run more assessments.');
        }

        $target = $project->targets->first();

        if (! $target) {
            return back()->with('error', 'This project has no target. Please add a target first.');
        }

        $request->validate([
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['integer', 'exists:assessment_modules,module_id'],
            'exclusion_reasons' => ['nullable', 'array'],
            'exclusion_reasons.*' => ['nullable', 'string', 'max:500'],
        ]);

        $selectedIds = collect($request->input('modules', []))->map(fn ($id) => (int) $id);
        $exclusionReasons = $request->input('exclusion_reasons', []);

        $allowedModuleIds = AssessmentModule::where('target_type_code', $target->target_type_code)
            ->where('is_active', true)
            ->whereIn('module_id', $selectedIds->all())
            ->pluck('module_id')
            ->map(fn ($id) => (int) $id);

        if ($allowedModuleIds->count() !== $selectedIds->unique()->count()) {
            return back()
                ->withErrors(['modules' => 'One or more selected assessment areas are not available for this setting.'])
                ->withInput();
        }

        // Community module gate
        $hasHivaw = AssessmentModule::whereIn('module_id', $selectedIds->toArray())
            ->where('module_code', 'LIKE', 'HIVAW%')
            ->exists();
        if ($hasHivaw && ! PlanService::workspaceCanAccess($workspace, 'patient_community_voice_module')) {
            return back()->with('error', 'The Patient & Community Voice module is not available on your current plan. Upgrade to run community voice assessments.');
        }

        $defaultModuleIds = DB::table('target_category_default_modules')
            ->where('category_id', $target->category_id)
            ->pluck('module_id')
            ->map(fn ($id) => (int) $id);

        // Deselected defaults must have an exclusion reason
        $deselectedIds = $defaultModuleIds->diff($selectedIds);
        foreach ($deselectedIds as $moduleId) {
            if (empty(trim($exclusionReasons[$moduleId] ?? ''))) {
                return back()
                    ->withErrors(['exclusion_reasons' => 'Please explain why each excluded module is not being assessed.'])
                    ->withInput();
            }
        }

        // scope_type: FULL_TARGET only when selected set exactly matches category defaults
        $selectedSorted = $selectedIds->sort()->values()->toArray();
        $defaultSorted = $defaultModuleIds->sort()->values()->toArray();
        $scopeType = ($selectedSorted === $defaultSorted) ? 'FULL_TARGET' : 'MODULE_PICKER';

        $tier = AssessmentTier::where('tier_code', 'TIER_1')->first();

        $assessment = DB::transaction(function () use ($project, $target, $tier, $scopeType, $selectedIds, $deselectedIds, $defaultModuleIds, $exclusionReasons) {
            $assessment = Assessment::create([
                'target_id' => $target->target_id,
                'project_id' => $project->project_id,
                'assessment_tier_id' => $tier->assessment_tier_id,
                'scope_type' => $scopeType,
                'status' => 'IN_PROGRESS',
                'publish_status' => 'DRAFT',
                'assessor_name' => auth()->user()->name,
                'started_at' => now(),
            ]);

            // In-scope modules (selected)
            foreach ($selectedIds as $moduleId) {
                AssessmentModuleScope::create([
                    'assessment_id' => $assessment->assessment_id,
                    'module_id' => $moduleId,
                    'in_scope' => true,
                    'is_category_default' => $defaultModuleIds->contains($moduleId),
                    'status' => 'PENDING',
                ]);
            }

            // Out-of-scope defaults (deselected with reason)
            foreach ($deselectedIds as $moduleId) {
                AssessmentModuleScope::create([
                    'assessment_id' => $assessment->assessment_id,
                    'module_id' => $moduleId,
                    'in_scope' => false,
                    'is_category_default' => true,
                    'exclusion_reason' => trim($exclusionReasons[$moduleId]),
                    'status' => 'EXCLUDED',
                ]);
            }

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
            ->where('in_scope', true)
            ->update(['status' => 'COMPLETED', 'completed_at' => now()]);

        app(ScoringService::class)->calculate($assessment);

        $admins = WorkspaceMember::where('workspace_id', app('current.workspace')->workspace_id)
            ->whereIn('role', ['OWNER', 'ADMIN'])
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        Notification::send($admins, new AssessmentCompletedNotification($assessment));

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

        $inScopeScopes = $assessment->moduleScope->where('in_scope', true);
        $inScopeCount = $inScopeScopes->count();
        $firstModule = $inScopeScopes->first()?->module;

        $assessmentTitle = $inScopeCount === 1
            ? ($firstModule?->module_name ?? 'Assessment')
            : match ($assessment->scope_type) {
                'FULL_TARGET' => 'Full Assessment',
                'MODULE_PICKER' => 'Custom Scope',
                default => 'Assessment',
            };

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

        // History: same scope_type on same project
        $history = Assessment::where('project_id', $assessment->project_id)
            ->where('status', 'COMPLETE')
            ->where('scope_type', $assessment->scope_type)
            ->with('score.maturityLevel')
            ->orderBy('completed_at')
            ->get();

        return view('assessments.results', compact('assessment', 'assessmentTitle', 'subIndexScores', 'domainScores', 'history'));
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
