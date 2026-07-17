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
use App\Services\AuditService;
use App\Services\PlanService;
use App\Services\ReportSnapshotService;
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
            ->with(['project', 'target', 'score', 'moduleScope.module', 'snapshot'])
            ->latest('updated_at')
            ->paginate(20);

        return view('assessments.index', compact('assessments'));
    }

    public function create(Project $project): View
    {
        $this->authorize('view', $project);
        $target = $project->targets->first();
        $settingTypeCode = $target
            ? DB::table('target_type_setting_map')->where('target_type_code', $target->target_type_code)->value('setting_type_code')
            : null;
        $usesDepartments = $target?->uses_departments ?? (bool) DB::table('setting_types')
            ->where('setting_type_code', $settingTypeCode)
            ->value('uses_departments');

        $templateQuery = fn ($query) => $query->where('status', AssessmentTemplateVersion::STATUS_PUBLISHED)
            ->whereNotNull('published_payload')
            ->orderByDesc('version_number');
        $comprehensiveTemplates = AssessmentTemplate::where('status', AssessmentTemplate::STATUS_PUBLISHED)
            ->where('creation_path', 'COMPREHENSIVE')
            ->where('setting_type_code', $settingTypeCode)
            ->with(['versions' => $templateQuery])
            ->orderBy('template_name')
            ->get();
        $focusedTemplates = AssessmentTemplate::where('status', AssessmentTemplate::STATUS_PUBLISHED)
            ->where('creation_path', 'FOCUSED')
            ->with(['healthDomain', 'versions' => $templateQuery])
            ->orderBy('template_name')
            ->get();

        return view('assessments.create', compact('project', 'target', 'usesDepartments', 'comprehensiveTemplates', 'focusedTemplates'));
    }

    public function store(Request $request, Project $project, AssessmentCreationService $creator): RedirectResponse
    {
        $this->authorize('update', $project);
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

        if ($assessment->snapshot?->collection_config['allows_multi_respondent'] ?? false) {
            return redirect()->route('assessments.respondent-collection', $assessment);
        }

        return redirect()->route('assessments.run', $assessment);
    }

    public function run(Assessment $assessment): View
    {
        $this->authorizeWorkspace($assessment);

        if ($assessment->snapshot?->collection_config['allows_multi_respondent'] ?? false) {
            return redirect()->route('assessments.respondent-collection', $assessment)
                ->with('error', 'Multi-respondent assessments must be finalized from the respondent collection review.');
        }

        $assessment->load(['project', 'target', 'moduleScope.module']);

        return view('assessments.run', compact('assessment'));
    }

    public function submit(Assessment $assessment): RedirectResponse
    {
        $this->authorizeWorkspace($assessment);

        if ($assessment->status === Assessment::STATUS_COMPLETE) {
            return redirect()->route('projects.show', $assessment->project_id)
                ->with('success', 'Assessment already submitted.');
        }

        $snapshot = $assessment->snapshot()->first();
        if ($snapshot?->collection_config['allows_multi_respondent'] ?? false) {
            return redirect()->route('assessments.respondent-collection', $assessment)
                ->with('error', 'Multi-respondent collections require authorized manual finalization.');
        }

        if ($snapshot) {
            $requiredQuestions = collect($snapshot->payload)
                ->flatMap(fn ($module) => $module['questions'] ?? [])
                ->where('is_scored', true)
                ->values();
            $responses = Response::where('assessment_id', $assessment->assessment_id)
                ->whereNull('respondent_id')
                ->whereNull('public_response_session_id')
                ->whereIn('question_id', $requiredQuestions->pluck('question_id'))
                ->get()->keyBy('question_id');
            $hasMissingResponse = $requiredQuestions->contains(function ($question) use ($responses) {
                $response = $responses->get($question['question_id']);
                if (! $response) {
                    return true;
                }

                return match ($question['response_type']) {
                    'OPEN_ENDED' => blank($response->value_text),
                    'NUMERIC' => $response->value_numeric === null,
                    default => ! collect($question['options'] ?? [])
                        ->contains('option_id', (int) $response->value_option_id),
                };
            });
        } else {
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
                ->whereNull('public_response_session_id')
                ->whereIn('question_id', $requiredQuestions->pluck('question_id'))
                ->with('selectedOption')
                ->get()->keyBy('question_id');
            $hasMissingResponse = $requiredQuestions->contains(function (Question $question) use ($responses) {
                $response = $responses->get($question->question_id);
                if (! $response) {
                    return true;
                }

                return match ($question->questionType?->type_code) {
                    'OPEN_ENDED' => blank($response->value_text),
                    'NUMERIC' => $response->value_numeric === null,
                    default => ! $response->selectedOption || $response->selectedOption->question_id !== $question->question_id,
                };
            });
        }

        if ($hasMissingResponse) {
            return redirect()->route('assessments.run', $assessment)
                ->with('error', 'Please answer every required scored question before submitting.');
        }

        DB::transaction(function () use ($assessment): void {
            $completedAt = now();
            $assessment->update(['status' => Assessment::STATUS_COMPLETE, 'completed_at' => $completedAt]);
            AssessmentModuleScope::where('assessment_id', $assessment->assessment_id)
                ->where('in_scope', true)
                ->update(['status' => AssessmentModuleScope::STATUS_COMPLETED, 'completed_at' => $completedAt]);
            app(ScoringService::class)->calculate($assessment);
            app(ReportSnapshotService::class)->createFor($assessment->fresh());
            app(AuditService::class)->record(
                'assessment.completed',
                $assessment,
                ['status' => Assessment::STATUS_IN_PROGRESS],
                ['status' => Assessment::STATUS_COMPLETE, 'completed_at' => $completedAt->toIso8601String()],
            );
        });

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

    public function results(Assessment $assessment, ReportSnapshotService $reports): View|RedirectResponse
    {
        $this->authorizeWorkspace($assessment);

        if ($assessment->status !== Assessment::STATUS_COMPLETE) {
            return redirect()->route('assessments.run', $assessment);
        }

        $assessment->load([
            'project',
            'target.targetType',
            'moduleScope.module',
            'score.maturityLevel',
        ]);

        $report = $reports->payloadFor($assessment);
        $assessmentTitle = $report['title'];
        $subIndexScores = collect($report['sub_index_scores'])->map(fn ($row) => (object) $row);
        $domainScores = collect($report['domain_scores'])->map(fn ($row) => (object) $row);
        if ($assessment->score) {
            $assessment->score->overall_score = $report['score']['overall_score'];
            $assessment->score->calibration_status = $report['score']['calibration_status'];
            $assessment->score->scoring_version = $report['score']['scoring_version'];
            if ($assessment->score->maturityLevel && $report['score']['maturity_level']) {
                $assessment->score->maturityLevel->level_name = $report['score']['maturity_level']['name'];
                $assessment->score->maturityLevel->level_number = $report['score']['maturity_level']['number'];
            }
        }

        // History is comparable only when the exact template composition matches.
        $history = Assessment::where('project_id', $assessment->project_id)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->when(
                $assessment->composition_hash,
                fn ($query, $hash) => $query->where('composition_hash', $hash),
                fn ($query) => $query->where('assessment_id', $assessment->assessment_id)
            )
            ->with(['score.maturityLevel', 'reportSnapshot'])
            ->orderBy('completed_at')
            ->get();
        foreach ($history as $historicalAssessment) {
            $historicalScore = $historicalAssessment->reportSnapshot?->payload['score'] ?? null;
            if ($historicalScore && $historicalAssessment->score) {
                $historicalAssessment->score->overall_score = $historicalScore['overall_score'];
                $historicalAssessment->score->calibration_status = $historicalScore['calibration_status'];
            }
        }

        return view('assessments.results', compact('assessment', 'assessmentTitle', 'subIndexScores', 'domainScores', 'history'));
    }

    private function authorizeWorkspace(Assessment $assessment): void
    {
        $this->authorize('view', $assessment);
    }
}
