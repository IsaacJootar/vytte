<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentModule;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\QuestionType;
use App\Models\QuestionVersion;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuestionIdentityController extends Controller
{
    public function index(Request $request): View
    {
        $query = Question::with(['module', 'questionGroup', 'questionType'])
            ->withCount('versions')
            ->orderBy('question_code');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->lower().'%';
            $query->where(function ($inner) use ($search): void {
                $inner->whereRaw('LOWER(question_code) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(question_text) LIKE ?', [$search]);
            });
        }

        if ($request->filled('module_id')) {
            $query->where('module_id', $request->integer('module_id'));
        }

        if ($request->filled('status')) {
            $query->where('question_status', $request->string('status'));
        }

        $questions = $query->paginate(30)->withQueryString();
        $modules = AssessmentModule::orderBy('module_name')->get();

        return view('admin.question-identities.index', compact('questions', 'modules'));
    }

    public function create(): View
    {
        return view('admin.question-identities.create', [
            'modules' => AssessmentModule::with('questionGroups')->orderBy('module_name')->get(),
            'questionTypes' => QuestionType::orderBy('type_code')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'module_id' => ['required', 'integer', Rule::exists('assessment_modules', 'module_id')],
            'question_group_id' => ['nullable', 'integer', Rule::exists('question_groups', 'question_group_id')],
            'question_code' => ['required', 'string', 'max:60', 'unique:questions,question_code'],
            'question_text' => ['required', 'string'],
            'type_id' => ['required', 'integer', Rule::exists('question_types', 'type_id')],
            'respondent_role_hint' => ['nullable', 'string', 'max:150'],
            'is_scored' => ['nullable', 'boolean'],
            'numeric_unit' => ['nullable', 'string', 'max:30'],
            'numeric_min' => ['nullable', 'numeric'],
            'numeric_max' => ['nullable', 'numeric'],
            'numeric_step' => ['nullable', 'numeric'],
            'methodology_notes' => ['nullable', 'string'],
            'source_summary' => ['nullable', 'string'],
        ]);

        if ($validated['question_group_id'] ?? null) {
            $belongsToModule = QuestionGroup::where('question_group_id', $validated['question_group_id'])
                ->where('module_id', $validated['module_id'])
                ->exists();
            abort_unless($belongsToModule, 422, 'Selected question group does not belong to the selected department.');
        }

        $nextOrder = ((int) Question::where('module_id', $validated['module_id'])->max('display_order')) + 1;
        $nextNumber = ((int) Question::where('module_id', $validated['module_id'])->max('question_number')) + 1;

        $question = Question::create([
            'module_id' => $validated['module_id'],
            'question_group_id' => $validated['question_group_id'] ?? null,
            'question_number' => $nextNumber,
            'question_code' => strtoupper($validated['question_code']),
            'question_text' => $validated['question_text'],
            'type_id' => $validated['type_id'],
            'respondent_role_hint' => $validated['respondent_role_hint'] ?? null,
            'display_order' => $nextOrder,
            'is_active' => true,
            'is_scored' => (bool) ($validated['is_scored'] ?? true),
            'source' => 'PLATFORM_ADMIN',
            'question_status' => 'DRAFT',
            'standard_alignment_status' => 'PENDING_REVIEW',
            'numeric_unit' => $validated['numeric_unit'] ?? null,
            'numeric_min' => $validated['numeric_min'] ?? null,
            'numeric_max' => $validated['numeric_max'] ?? null,
            'numeric_step' => $validated['numeric_step'] ?? null,
        ]);

        QuestionVersion::create([
            'question_id' => $question->question_id,
            'version_number' => 1,
            'status' => QuestionVersion::STATUS_DRAFT,
            'question_text' => $question->question_text,
            'type_id' => $question->type_id,
            'numeric_config' => $question->numeric_unit || $question->numeric_min !== null || $question->numeric_max !== null ? [
                'unit' => $question->numeric_unit,
                'min' => $question->numeric_min !== null ? (float) $question->numeric_min : null,
                'max' => $question->numeric_max !== null ? (float) $question->numeric_max : null,
                'step' => $question->numeric_step !== null ? (float) $question->numeric_step : null,
            ] : null,
            'requires_observation' => false,
            'respondent_role_hint' => $question->respondent_role_hint,
            'methodology_notes' => $validated['methodology_notes'] ?? 'Initial Vytte Platform Admin draft.',
            'source_summary' => $validated['source_summary'] ?? null,
        ]);

        $audit->record('question.identity.created', $question, newValues: [
            'question_code' => $question->question_code,
            'module_id' => $question->module_id,
        ]);

        return redirect()->route('admin.question-identities.show', $question)
            ->with('success', 'Question identity and first draft version created.');
    }

    public function show(Question $question): View
    {
        $question->load([
            'module',
            'questionGroup',
            'questionType',
            'options',
            'numericBands',
            'versions.questionType',
            'placements.frameworkVersion.module',
            'placements.section',
            'placements.indicator',
        ]);

        return view('admin.question-identities.show', compact('question'));
    }
}
