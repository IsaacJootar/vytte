<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FrameworkQuestionPlacement;
use App\Models\FrameworkSection;
use App\Models\QuestionVersion;
use App\Services\AssessmentBuilderService;
use App\Services\AuditService;
use App\Support\AnswerFormat;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Adding questions to a section, either from the official question library or by writing a
 * new one. Governed work is delegated to AssessmentBuilderService.
 */
class AssessmentQuestionController extends Controller
{
    public function __construct(private readonly AssessmentBuilderService $builder) {}

    /**
     * The question library. Shows published, reusable questions with the information an
     * author needs to choose one: wording, answer format, and whether it is already used.
     */
    public function library(Request $request, DepartmentFrameworkVersion $assessment, FrameworkSection $section): View
    {
        $this->assertBelongs($assessment, $section);

        $used = FrameworkQuestionPlacement::where('framework_version_id', $assessment->framework_version_id)
            ->pluck('question_id');

        $query = QuestionVersion::query()
            ->with(['question.module', 'questionType'])
            ->where('status', QuestionVersion::STATUS_PUBLISHED)
            ->orderByDesc('published_at');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->lower().'%';
            $query->where(fn ($inner) => $inner
                ->whereRaw('LOWER(question_text) LIKE ?', [$search])
                ->orWhereHas('question', fn ($q) => $q->whereRaw('LOWER(question_code) LIKE ?', [$search])));
        }

        if ($request->filled('department')) {
            $query->whereHas('question', fn ($q) => $q->where('module_id', $request->integer('department')));
        }

        if ($request->boolean('unused_only')) {
            $query->whereNotIn('question_id', $used);
        }

        return view('admin.assessment-builder.library', [
            'assessment' => $assessment,
            'section' => $section,
            'versions' => $query->paginate(15)->withQueryString(),
            'usedQuestionIds' => $used->all(),
            'departments' => AssessmentModule::where('is_active', true)->orderBy('module_name')->get(['module_id', 'module_name']),
        ]);
    }

    public function addFromLibrary(Request $request, DepartmentFrameworkVersion $assessment, FrameworkSection $section, AuditService $audit): RedirectResponse
    {
        $this->assertBelongs($assessment, $section);

        $validated = $request->validate([
            'question_version_id' => ['required', 'uuid', Rule::exists('question_versions', 'question_version_id')->where('status', QuestionVersion::STATUS_PUBLISHED)],
        ]);

        $version = QuestionVersion::findOrFail($validated['question_version_id']);

        try {
            $placement = $this->builder->addLibraryQuestion($section, $version);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        $audit->record('assessment.question.added', $assessment, newValues: [
            'source' => 'LIBRARY',
            'question_version_id' => $version->question_version_id,
            'framework_question_placement_id' => $placement->framework_question_placement_id,
        ]);

        return redirect()->route('admin.assessments.build', $assessment)
            ->with('success', 'Question added to '.$section->section_name.'.');
    }

    public function create(DepartmentFrameworkVersion $assessment, FrameworkSection $section): View
    {
        $this->assertBelongs($assessment, $section);

        return view('admin.assessment-builder.question-create', [
            'assessment' => $assessment,
            'section' => $section,
            'formats' => AnswerFormat::all(),
        ]);
    }

    public function store(Request $request, DepartmentFrameworkVersion $assessment, FrameworkSection $section, AuditService $audit): RedirectResponse
    {
        $this->assertBelongs($assessment, $section);

        $validated = $request->validate([
            'question_text' => ['required', 'string', 'max:5000'],
            'format' => ['required', Rule::in(AnswerFormat::keys())],
            'choices' => ['array'],
            'choices.*' => ['nullable', 'string', 'max:180'],
            'numeric_min' => ['nullable', 'numeric'],
            'numeric_max' => ['nullable', 'numeric'],
            'numeric_unit' => ['nullable', 'string', 'max:40'],
        ], [], [
            'question_text' => 'question',
            'format' => 'answer format',
        ]);

        try {
            $placement = $this->builder->createQuestion($section, [
                'question_text' => $validated['question_text'],
                'format' => $validated['format'],
                'choices' => $validated['choices'] ?? [],
                'numeric' => [
                    'min' => $validated['numeric_min'] ?? null,
                    'max' => $validated['numeric_max'] ?? null,
                    'unit' => $validated['numeric_unit'] ?? null,
                ],
            ]);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        $audit->record('assessment.question.added', $assessment, newValues: [
            'source' => 'NEW',
            'question_version_id' => $placement->question_version_id,
            'framework_question_placement_id' => $placement->framework_question_placement_id,
        ]);

        return redirect()->route('admin.assessments.build', $assessment)
            ->with('success', 'Question added to '.$section->section_name.'.');
    }

    /**
     * Scoring and evidence for one question.
     */
    public function settings(DepartmentFrameworkVersion $assessment, FrameworkQuestionPlacement $placement): View
    {
        $this->assertPlacementBelongs($assessment, $placement);
        $placement->load(['questionVersion.questionType', 'section']);

        return view('admin.assessment-builder.question-settings', [
            'assessment' => $assessment,
            'placement' => $placement,
            'version' => $placement->questionVersion,
            'typeCode' => $placement->questionVersion?->questionType?->type_code,
            'scoringGroups' => $this->builder->scoringGroupsFor($assessment),
            'domains' => Domain::orderBy('display_order')->get(['domain_id', 'domain_name']),
            'isEditable' => $assessment->status === DepartmentFrameworkVersion::STATUS_DRAFT,
            'answerIsLocked' => $placement->questionVersion?->status === QuestionVersion::STATUS_PUBLISHED,
        ]);
    }

    public function saveSettings(Request $request, DepartmentFrameworkVersion $assessment, FrameworkQuestionPlacement $placement, AuditService $audit): RedirectResponse
    {
        $this->assertPlacementBelongs($assessment, $placement);

        $validated = $request->validate([
            'is_scored' => ['required', 'boolean'],
            'scoring_group_id' => ['nullable', 'integer'],
            'importance' => ['nullable', 'in:normal,high'],
            'evidence_mode' => ['required', 'in:none,note'],
            'evidence_prompt' => ['nullable', 'string', 'max:500'],
            'points' => ['array'],
            'critical' => ['array'],
            'bands' => ['array'],
        ]);

        try {
            $this->builder->applyScoringAndEvidence($placement, [
                'is_scored' => (bool) $validated['is_scored'],
                'scoring_group_id' => $validated['scoring_group_id'] ?? null,
                'importance' => $validated['importance'] ?? 'normal',
                'evidence_mode' => $validated['evidence_mode'],
                'evidence_prompt' => $validated['evidence_prompt'] ?? null,
                'points' => $validated['points'] ?? [],
                'critical' => $validated['critical'] ?? [],
                'bands' => $validated['bands'] ?? [],
            ]);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        $audit->record('assessment.question.settings_updated', $assessment, newValues: [
            'framework_question_placement_id' => $placement->framework_question_placement_id,
            'scoring_contribution' => (bool) $validated['is_scored'],
        ]);

        return redirect()->route('admin.assessments.build', $assessment)
            ->with('success', 'Question settings saved.');
    }

    public function storeScoringGroup(Request $request, DepartmentFrameworkVersion $assessment, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'domain_id' => ['required', 'integer', Rule::exists('domains', 'domain_id')],
        ], [], ['name' => 'score name']);

        try {
            $group = $this->builder->createScoringGroup($assessment, $validated['name'], $validated['domain_id']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        $audit->record('assessment.scoring_group.created', $assessment, newValues: [
            'sub_index_id' => $group->sub_index_id,
            'full_name' => $group->full_name,
        ]);

        return back()->with('success', 'Score group created.');
    }

    /**
     * Explicitly approves and freezes a question. Never happens automatically.
     */
    public function approve(DepartmentFrameworkVersion $assessment, FrameworkQuestionPlacement $placement, AuditService $audit): RedirectResponse
    {
        $this->assertPlacementBelongs($assessment, $placement);

        try {
            $version = $this->builder->approveQuestion($placement->questionVersion, auth()->id());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        } catch (\Throwable $exception) {
            return back()->withErrors(['question' => $exception->getMessage()]);
        }

        $audit->record('assessment.question.approved', $assessment, newValues: [
            'question_version_id' => $version->question_version_id,
            'approved_by' => auth()->id(),
        ]);

        return back()->with('success', 'Question approved and locked.');
    }

    public function move(Request $request, DepartmentFrameworkVersion $assessment, FrameworkQuestionPlacement $placement): RedirectResponse
    {
        $this->assertPlacementBelongs($assessment, $placement);
        $validated = $request->validate(['direction' => ['required', 'in:up,down']]);

        try {
            $this->builder->moveQuestion($placement, $validated['direction']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Question order updated.');
    }

    public function destroy(DepartmentFrameworkVersion $assessment, FrameworkQuestionPlacement $placement, AuditService $audit): RedirectResponse
    {
        $this->assertPlacementBelongs($assessment, $placement);
        $questionId = $placement->question_id;

        try {
            $this->builder->removeQuestion($placement);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        $audit->record('assessment.question.removed', $assessment, ['question_id' => $questionId]);

        return back()->with('success', 'Question removed.');
    }

    private function assertBelongs(DepartmentFrameworkVersion $assessment, FrameworkSection $section): void
    {
        abort_unless($section->framework_version_id === $assessment->framework_version_id, 404);
    }

    private function assertPlacementBelongs(DepartmentFrameworkVersion $assessment, FrameworkQuestionPlacement $placement): void
    {
        abort_unless($placement->framework_version_id === $assessment->framework_version_id, 404);
    }
}
