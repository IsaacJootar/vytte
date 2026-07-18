<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentModule;
use App\Models\QuestionGroup;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuestionGroupController extends Controller
{
    public function index(Request $request): View
    {
        $query = QuestionGroup::with('module')
            ->withCount('questions')
            ->orderBy('module_id')
            ->orderBy('group_number');

        if ($request->filled('module_id')) {
            $query->where('module_id', $request->integer('module_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $groups = $query->paginate(30)->withQueryString();
        $modules = AssessmentModule::orderBy('module_name')->get();

        return view('admin.question-groups.index', compact('groups', 'modules'));
    }

    public function create(): View
    {
        return view('admin.question-groups.create', [
            'modules' => AssessmentModule::orderBy('module_name')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'module_id' => ['required', 'integer', Rule::exists('assessment_modules', 'module_id')],
            'group_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('question_groups', 'group_number')
                    ->where(fn ($query) => $query->where('module_id', $request->integer('module_id'))),
            ],
            'group_label' => ['required', 'string', 'max:150'],
        ]);

        $group = QuestionGroup::create($validated + ['status' => QuestionGroup::STATUS_ACTIVE]);
        $audit->record('question_group.created', $group, newValues: $group->only(['module_id', 'group_number', 'group_label', 'status']));

        return redirect()->route('admin.question-groups.show', $group)
            ->with('success', 'Question group created.');
    }

    public function show(QuestionGroup $group): View
    {
        $group->load(['module', 'questions.questionType', 'questions.versions']);

        return view('admin.question-groups.show', compact('group'));
    }

    public function edit(QuestionGroup $group): View
    {
        $group->load('module');

        return view('admin.question-groups.edit', [
            'group' => $group,
            'modules' => AssessmentModule::orderBy('module_name')->get(),
        ]);
    }

    public function update(Request $request, QuestionGroup $group, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'group_number' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                Rule::unique('question_groups', 'group_number')
                    ->where(fn ($query) => $query->where('module_id', $request->integer('module_id', $group->module_id)))
                    ->ignore($group->question_group_id, 'question_group_id'),
            ],
            'group_label' => ['required', 'string', 'max:150'],
            'status' => ['sometimes', Rule::in([QuestionGroup::STATUS_ACTIVE, QuestionGroup::STATUS_ARCHIVED])],
        ]);

        $oldValues = $group->only(['group_number', 'group_label', 'status']);
        $group->update($validated);
        $audit->record('question_group.updated', $group, $oldValues, $group->only(['group_number', 'group_label', 'status']));

        return back()->with('success', 'Question group updated.');
    }

    public function archive(QuestionGroup $group, AuditService $audit): RedirectResponse
    {
        $oldValues = $group->only(['status']);
        $group->update(['status' => QuestionGroup::STATUS_ARCHIVED]);
        $audit->record('question_group.archived', $group, $oldValues, ['status' => QuestionGroup::STATUS_ARCHIVED]);

        return back()->with('success', 'Question group archived.');
    }
}
