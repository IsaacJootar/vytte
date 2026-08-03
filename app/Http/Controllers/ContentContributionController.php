<?php

namespace App\Http\Controllers;

use App\Models\AssessmentModule;
use App\Models\ContentContribution;
use App\Models\ContentPublisher;
use App\Services\AuditService;
use App\Support\AnswerFormat;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContentContributionController extends Controller
{
    public function index(): View
    {
        return view('contributions.index', [
            'contributions' => ContentContribution::with('module')->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('contributions.create', [
            'departments' => AssessmentModule::where('is_active', true)->orderBy('module_name')->get(),
            'formats' => AnswerFormat::all(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        abort_unless(app()->bound('current.workspace'), 403);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'module_id' => ['nullable', 'integer', Rule::exists('assessment_modules', 'module_id')->where('is_active', true)],
            'question_text' => ['required', 'string', 'max:5000'],
            'response_format' => ['required', Rule::in(AnswerFormat::keys())],
            'answer_options' => ['array'],
            'answer_options.*' => ['nullable', 'string', 'max:180'],
            'numeric_min' => ['nullable', 'numeric'],
            'numeric_max' => ['nullable', 'numeric'],
            'numeric_unit' => ['nullable', 'string', 'max:40'],
            'intended_use' => ['required', 'string', 'max:3000'],
            'source_authority' => ['nullable', 'string', 'max:180'],
            'source_url' => ['nullable', 'url', 'max:2000'],
            'license_code' => ['nullable', 'string', 'max:80'],
            'methodology_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $format = AnswerFormat::require($validated['response_format']);
        try {
            $choices = AnswerFormat::choicesFor($format, $validated['answer_options'] ?? []);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }
        if (isset($validated['numeric_min'], $validated['numeric_max']) && (float) $validated['numeric_min'] > (float) $validated['numeric_max']) {
            return back()->withErrors(['numeric_min' => 'The minimum cannot be greater than the maximum.'])->withInput();
        }

        $workspace = app('current.workspace');
        $publisherId = ContentPublisher::where('workspace_id', $workspace->workspace_id)
            ->where('verification_status', '!=', ContentPublisher::STATUS_SUSPENDED)
            ->value('content_publisher_id');
        $contribution = ContentContribution::create([
            'workspace_id' => $workspace->workspace_id,
            'submitted_by' => auth()->id(),
            'proposed_publisher_id' => $publisherId,
            'module_id' => $validated['module_id'] ?? null,
            'title' => $validated['title'],
            'question_text' => $validated['question_text'],
            'response_format' => $validated['response_format'],
            'answer_options' => $choices,
            'numeric_config' => $validated['response_format'] === AnswerFormat::NUMBER ? [
                'min' => isset($validated['numeric_min']) ? (float) $validated['numeric_min'] : null,
                'max' => isset($validated['numeric_max']) ? (float) $validated['numeric_max'] : null,
                'unit' => $validated['numeric_unit'] ?? null,
            ] : null,
            'intended_use' => $validated['intended_use'],
            'source_authority' => $validated['source_authority'] ?? null,
            'source_url' => $validated['source_url'] ?? null,
            'license_code' => $validated['license_code'] ?? null,
            'methodology_notes' => $validated['methodology_notes'] ?? null,
            'status' => 'SUBMITTED',
        ]);
        $audit->record('content.contribution.submitted', $contribution, newValues: [
            'title' => $contribution->title,
            'response_format' => $contribution->response_format,
        ]);

        return redirect()->route('contributions.index')->with('success', 'Question submitted for review. It will not enter the shared library unless reviewers accept and promote it.');
    }
}
