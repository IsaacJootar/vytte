<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionType;
use App\Models\QuestionVersion;
use App\Services\AuditService;
use App\Services\GovernanceDependencyService;
use App\Services\QuestionOptionSyncService;
use App\Services\QuestionVersionPublishingService;
use App\Support\ResponseInputContract;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuestionVersionController extends Controller
{
    public function index(Request $request): View
    {
        $query = QuestionVersion::with(['question.module', 'questionType'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->lower().'%';
            $query->whereHas('question', fn ($inner) => $inner
                ->whereRaw('LOWER(question_code) LIKE ?', [$search])
                ->orWhereRaw('LOWER(question_text) LIKE ?', [$search]));
        }

        return view('admin.question-versions.index', [
            'versions' => $query->paginate(30)->withQueryString(),
            'statuses' => [
                QuestionVersion::STATUS_DRAFT,
                QuestionVersion::STATUS_INTERNAL_REVIEW,
                QuestionVersion::STATUS_APPROVED,
                QuestionVersion::STATUS_PUBLISHED,
                QuestionVersion::STATUS_SUPERSEDED,
                QuestionVersion::STATUS_ARCHIVED,
            ],
        ]);
    }

    public function show(QuestionVersion $version, GovernanceDependencyService $dependencies): View
    {
        $version->load(['question.module', 'question.questionGroup', 'questionType', 'parentVersion']);

        return view('admin.question-versions.show', [
            'version' => $version,
            'questionTypes' => QuestionType::orderBy('type_code')->get(),
            'dependencySummary' => $dependencies->questionVersion($version),
            'isOptionType' => in_array($version->questionType?->type_code, ResponseInputContract::OPTION_TYPES, true),
            'isNumericType' => in_array($version->questionType?->type_code, ResponseInputContract::NUMERIC_TYPES, true),
        ]);
    }

    public function update(Request $request, QuestionVersion $version, AuditService $audit): RedirectResponse
    {
        if ($version->status !== QuestionVersion::STATUS_DRAFT) {
            return back()->withErrors(['status' => 'Only draft question versions can be edited. Create a successor draft for published content.']);
        }

        $validated = $request->validate([
            'question_text' => ['required', 'string', 'max:5000'],
            'type_id' => ['required', 'integer', Rule::exists('question_types', 'type_id')],
            'requires_observation' => ['nullable', 'boolean'],
            'respondent_role_hint' => ['nullable', 'string', 'max:150'],
            'methodology_notes' => ['nullable', 'string'],
            'source_summary' => ['nullable', 'string'],
            'review_notes' => ['nullable', 'string'],
            'effective_date' => ['nullable', 'date'],
            'numeric_min' => ['nullable', 'numeric'],
            'numeric_max' => ['nullable', 'numeric'],
            'numeric_unit' => ['nullable', 'string', 'max:40'],
            'numeric_step' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $type = QuestionType::findOrFail($validated['type_id']);
        $typeCode = $type->type_code;
        $options = [];
        $numericConfig = null;
        $numericBands = [];
        $errors = [];

        if (in_array($typeCode, ResponseInputContract::OPTION_TYPES, true)) {
            $options = $this->normaliseOptions($request->input('options', []), $errors, $version);
        }

        if (in_array($typeCode, ResponseInputContract::NUMERIC_TYPES, true)) {
            [$numericConfig, $numericBands] = $this->normaliseNumeric($request, $errors);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $old = $version->only([
            'question_text',
            'type_id',
            'options',
            'numeric_config',
            'numeric_bands',
            'requires_observation',
            'respondent_role_hint',
            'methodology_notes',
            'source_summary',
            'review_notes',
            'effective_date',
        ]);

        $version->update([
            'question_text' => $validated['question_text'],
            'type_id' => $type->type_id,
            'options' => $options,
            'numeric_config' => $numericConfig,
            'numeric_bands' => $numericBands,
            'requires_observation' => (bool) ($validated['requires_observation'] ?? false),
            'respondent_role_hint' => $validated['respondent_role_hint'] ?? null,
            'methodology_notes' => $validated['methodology_notes'] ?? null,
            'source_summary' => $validated['source_summary'] ?? null,
            'review_notes' => $validated['review_notes'] ?? null,
            'effective_date' => $validated['effective_date'] ?? null,
        ]);

        $audit->record('question.version.configured', $version->fresh(), $old, [
            'question_text' => $version->question_text,
            'type_id' => $version->type_id,
            'options' => $version->options,
            'numeric_config' => $version->numeric_config,
            'numeric_bands' => $version->numeric_bands,
        ]);

        return back()->with('success', 'Draft question version configuration saved.');
    }

    public function markApproved(QuestionVersion $version, AuditService $audit): RedirectResponse
    {
        if (! in_array($version->status, [QuestionVersion::STATUS_DRAFT, QuestionVersion::STATUS_INTERNAL_REVIEW], true)) {
            return back()->withErrors(['status' => 'Only draft or internal-review question versions can be approved.']);
        }

        $old = ['status' => $version->status];
        $version->update([
            'status' => QuestionVersion::STATUS_APPROVED,
            'approved_by' => auth()->id(),
            'review_notes' => $version->review_notes ?: 'Approved by Vytte Platform Admin.',
        ]);
        $audit->record('question.version.approved', $version, $old, ['status' => QuestionVersion::STATUS_APPROVED]);

        return back()->with('success', 'Question version approved.');
    }

    public function publish(QuestionVersion $version, QuestionVersionPublishingService $publisher): RedirectResponse
    {
        try {
            $publisher->publish($version, auth()->id());
        } catch (\Throwable $exception) {
            return back()->withErrors(['publication' => $exception->getMessage()]);
        }

        return back()->with('success', 'Question version published and frozen.');
    }

    public function supersede(QuestionVersion $version, AuditService $audit, GovernanceDependencyService $dependencies): RedirectResponse
    {
        if ($version->status !== QuestionVersion::STATUS_PUBLISHED) {
            return back()->withErrors(['status' => 'Only published question versions can be superseded.']);
        }

        $nextVersion = ((int) QuestionVersion::where('question_id', $version->question_id)->max('version_number')) + 1;

        $successor = QuestionVersion::create([
            'question_id' => $version->question_id,
            'parent_version_id' => $version->question_version_id,
            'version_number' => $nextVersion,
            'status' => QuestionVersion::STATUS_DRAFT,
            'question_text' => $version->question_text,
            'type_id' => $version->type_id,
            'options' => $version->options,
            'numeric_config' => $version->numeric_config,
            'numeric_bands' => $version->numeric_bands,
            'requires_observation' => $version->requires_observation,
            'respondent_role_hint' => $version->respondent_role_hint,
            'methodology_notes' => $version->methodology_notes,
            'source_summary' => $version->source_summary,
            'review_notes' => 'Successor draft created from v'.$version->version_number.'.',
        ]);

        $dependencySummary = $dependencies->questionVersion($version);
        $old = ['status' => $version->status];
        $version->update(['status' => QuestionVersion::STATUS_SUPERSEDED]);

        $audit->record('question.version.superseded', $version->fresh(), $old, [
            'status' => QuestionVersion::STATUS_SUPERSEDED,
            'successor_question_version_id' => $successor->question_version_id,
            'dependency_summary' => $dependencySummary,
        ]);

        return redirect()->route('admin.question-versions.show', $successor)
            ->with('success', 'Successor draft created. The previous published version is superseded but remains immutable for snapshots and reports.');
    }

    public function archive(QuestionVersion $version, AuditService $audit, GovernanceDependencyService $dependencies): RedirectResponse
    {
        if (! in_array($version->status, [QuestionVersion::STATUS_DRAFT, QuestionVersion::STATUS_INTERNAL_REVIEW, QuestionVersion::STATUS_APPROVED, QuestionVersion::STATUS_PUBLISHED], true)) {
            return back()->withErrors(['status' => 'This question version is already closed and cannot be archived again.']);
        }

        $dependencySummary = $dependencies->questionVersion($version);
        if ($dependencies->hasBlockingArchiveDependencies($dependencySummary)) {
            return back()->withErrors(['archive' => 'This question version is referenced by frameworks, snapshots, or reports and cannot be archived. Create a successor version instead.']);
        }

        $old = ['status' => $version->status];
        $version->update(['status' => QuestionVersion::STATUS_ARCHIVED]);

        $audit->record('question.version.archived', $version->fresh(), $old, [
            'status' => QuestionVersion::STATUS_ARCHIVED,
            'dependency_summary' => $dependencySummary,
        ]);

        return back()->with('success', 'Question version archived.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $existingOptions  options already stored on the draft
     */
    private function normaliseOptions(array $rows, array &$errors, QuestionVersion $version): array
    {
        $existingById = collect($version->options ?? [])
            ->filter(fn ($option) => is_array($option) && isset($option['option_id']))
            ->keyBy(fn ($option) => (int) $option['option_id']);

        $options = collect($rows)
            ->map(fn ($row) => is_array($row) ? $row : [])
            ->filter(fn ($row) => filled(Arr::get($row, 'option_label')) || filled(Arr::get($row, 'score_weight')))
            ->values()
            ->map(function (array $row, int $index) use (&$errors, $existingById): array {
                $label = trim((string) Arr::get($row, 'option_label', ''));
                $score = Arr::get($row, 'score_weight');
                $order = (int) (Arr::get($row, 'option_order') ?: $index + 1);
                $optionId = (int) (Arr::get($row, 'option_id') ?: 0);
                $existing = $existingById->get($optionId, []);

                if ($label === '') {
                    $errors["options.{$index}.option_label"][] = 'Each option needs a label.';
                }
                if ($score === null || $score === '') {
                    $errors["options.{$index}.score_weight"][] = 'Each option needs a score.';
                } elseif (! is_numeric($score) || (float) $score < 0 || (float) $score > 100) {
                    $errors["options.{$index}.score_weight"][] = 'Option scores must be between 0 and 100.';
                }

                $normalised = [
                    'option_label' => $label,
                    'option_order' => max(1, $order),
                    'score_weight' => $score === null || $score === '' ? null : (float) $score,
                ];

                // option_key and critical_failure are part of the published option
                // contract. critical_failure is read by ScoringService when a
                // catalogue aggregation policy enables critical failures. Neither is
                // editable on this screen, so carry the stored value forward rather
                // than dropping it, and honour an explicit submitted value if one
                // is ever posted.
                if (array_key_exists('option_key', $existing)) {
                    $normalised['option_key'] = $existing['option_key'];
                }

                $normalised['critical_failure'] = Arr::has($row, 'critical_failure')
                    ? filter_var(Arr::get($row, 'critical_failure'), FILTER_VALIDATE_BOOLEAN)
                    : (bool) ($existing['critical_failure'] ?? false);

                return $normalised;
            })
            ->sortBy('option_order')
            ->values()
            ->all();

        if ($options === []) {
            $errors['options'][] = 'Option-based questions need at least one answer option.';

            return $options;
        }

        // Answer options must exist as question_options rows: responses.value_option_id is
        // a foreign key to that table, so an option that lives only in version JSON cannot
        // be answered. This screen previously derived option_id from the row order, which
        // produced ids that did not resolve.
        return app(QuestionOptionSyncService::class)->sync($version->question, $options);
    }

    private function normaliseNumeric(Request $request, array &$errors): array
    {
        $min = $request->input('numeric_min');
        $max = $request->input('numeric_max');
        if ($min !== null && $min !== '' && $max !== null && $max !== '' && (float) $min > (float) $max) {
            $errors['numeric_min'][] = 'Numeric minimum cannot be greater than numeric maximum.';
        }

        $config = array_filter([
            'min_value' => $min === null || $min === '' ? null : (float) $min,
            'max_value' => $max === null || $max === '' ? null : (float) $max,
            'unit' => $request->filled('numeric_unit') ? trim((string) $request->input('numeric_unit')) : null,
            'step' => $request->filled('numeric_step') ? (float) $request->input('numeric_step') : null,
        ], fn ($value) => $value !== null && $value !== '');

        $bands = collect($request->input('numeric_bands', []))
            ->map(fn ($row) => is_array($row) ? $row : [])
            ->filter(fn ($row) => filled(Arr::get($row, 'label')) || filled(Arr::get($row, 'min_value')) || filled(Arr::get($row, 'max_value')) || filled(Arr::get($row, 'score_weight')))
            ->values()
            ->map(function (array $row, int $index) use (&$errors): array {
                $label = trim((string) Arr::get($row, 'label', ''));
                $bandMin = Arr::get($row, 'min_value');
                $bandMax = Arr::get($row, 'max_value');
                $score = Arr::get($row, 'score_weight');
                $order = (int) (Arr::get($row, 'display_order') ?: $index + 1);

                if ($label === '') {
                    $errors["numeric_bands.{$index}.label"][] = 'Each numeric band needs a label.';
                }
                if ($bandMin === null || $bandMin === '' || ! is_numeric($bandMin)) {
                    $errors["numeric_bands.{$index}.min_value"][] = 'Each numeric band needs a numeric minimum.';
                }
                if ($bandMax === null || $bandMax === '' || ! is_numeric($bandMax)) {
                    $errors["numeric_bands.{$index}.max_value"][] = 'Each numeric band needs a numeric maximum.';
                }
                if (is_numeric($bandMin) && is_numeric($bandMax) && (float) $bandMin > (float) $bandMax) {
                    $errors["numeric_bands.{$index}.min_value"][] = 'Band minimum cannot exceed band maximum.';
                }
                if ($score === null || $score === '') {
                    $errors["numeric_bands.{$index}.score_weight"][] = 'Each numeric band needs a score.';
                } elseif (! is_numeric($score) || (float) $score < 0 || (float) $score > 100) {
                    $errors["numeric_bands.{$index}.score_weight"][] = 'Band scores must be between 0 and 100.';
                }

                return [
                    'label' => $label,
                    'min_value' => $bandMin === null || $bandMin === '' ? null : (float) $bandMin,
                    'max_value' => $bandMax === null || $bandMax === '' ? null : (float) $bandMax,
                    'score_weight' => $score === null || $score === '' ? null : (float) $score,
                    'display_order' => max(1, $order),
                ];
            })
            ->sortBy('display_order')
            ->values()
            ->all();

        return [$config ?: null, $bands];
    }
}
