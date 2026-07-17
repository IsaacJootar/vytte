<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentTemplate;
use App\Models\AssessmentTemplateVersion;
use App\Models\Project;
use App\Models\Question;
use App\Models\Response;
use App\Models\WorkspaceMember;
use App\Notifications\AssessmentCompletedNotification;
use App\Services\AssessmentCreationService;
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
        $settingTypeCode = $target
            ? DB::table('target_type_setting_map')->where('target_type_code', $target->target_type_code)->value('setting_type_code')
            : null;
        $usesDepartments = $target?->uses_departments ?? (bool) DB::table('setting_types')
            ->where('setting_type_code', $settingTypeCode)
            ->value('uses_departments');

        $templateQuery = fn ($query) => $query->where('status', 'PUBLISHED')
            ->whereNotNull('published_payload')
            ->orderByDesc('version_number');
        $comprehensiveTemplates = AssessmentTemplate::where('status', 'PUBLISHED')
            ->where('creation_path', 'COMPREHENSIVE')
            ->where('setting_type_code', $settingTypeCode)
            ->with(['versions' => $templateQuery])
            ->orderBy('template_name')
            ->get();
        $focusedTemplates = AssessmentTemplate::where('status', 'PUBLISHED')
            ->where('creation_path', 'FOCUSED')
            ->with(['healthDomain', 'versions' => $templateQuery])
            ->orderBy('template_name')
            ->get();

        return view('assessments.create', compact('project', 'target', 'usesDepartments', 'comprehensiveTemplates', 'focusedTemplates'));
    }

    public function store(Request $request, Project $project, AssessmentCreationService $creator): RedirectResponse
    {
        $workspace = app('current.workspace');

        if (PlanService::hasReachedAssessmentLimit($workspace, $project)) {
            return redirect()->route('billing.index')
                ->with('limit_error', 'You have reached the assessment limit for this project on your current plan. Upgrade to run more assessments.');
        }

        $validated = $request->validate([
            'creation_path' => ['required', 'in:COMPREHENSIVE,FOCUSED'],
            'template_version_id' => ['required', 'uuid', 'exists:assessment_template_versions,template_version_id'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['integer'],
            'exclusion_reasons' => ['nullable', 'array'],
            'exclusion_reasons.*' => ['nullable', 'string', 'max:500'],
        ]);

        $version = AssessmentTemplateVersion::with('template')->findOrFail($validated['template_version_id']);
        if ($version->template->creation_path !== $validated['creation_path']) {
            return back()->withErrors(['template_version_id' => 'The selected template does not match this assessment path.'])->withInput();
        }

        $assessment = $creator->create(
            $project,
            $version,
            $validated['modules'] ?? [],
            $validated['exclusion_reasons'] ?? [],
            auth()->id(),
        );

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

        $moduleIds = AssessmentModuleScope::where('assessment_id', $assessment->assessment_id)
            ->where('in_scope', true)
            ->pluck('module_id');

        $requiredQuestions = Question::whereIn('module_id', $moduleIds)
            ->where('is_active', true)
            ->where('is_scored', true)
            ->with(['options', 'questionType'])
            ->get();

        $responses = Response::where('assessment_id', $assessment->assessment_id)
            ->whereNull('respondent_id')
            ->whereIn('question_id', $requiredQuestions->pluck('question_id'))
            ->with('selectedOption')
            ->get()
            ->keyBy('question_id');

        $hasMissingResponse = $requiredQuestions->contains(function (Question $question) use ($responses) {
            $response = $responses->get($question->question_id);

            if (! $response) {
                return true;
            }

            if ($question->options->isNotEmpty()) {
                return ! $response->selectedOption
                    || $response->selectedOption->question_id !== $question->question_id;
            }

            return $question->questionType?->type_code !== 'OPEN_ENDED'
                || blank($response->value_text);
        });

        if ($hasMissingResponse) {
            return redirect()->route('assessments.run', $assessment)
                ->with('error', 'Please answer every required scored question before submitting.');
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
