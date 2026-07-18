<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FrameworkIndicator;
use App\Models\FrameworkQuestionPlacement;
use App\Models\FrameworkSection;
use App\Models\QuestionVersion;
use App\Models\SubIndex;
use App\Services\AuditService;
use App\Services\DepartmentFrameworkPublishingService;
use App\Services\GovernanceDependencyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FrameworkVersionController extends Controller
{
    public function index(Request $request): View
    {
        $query = DepartmentFrameworkVersion::with('module')
            ->withCount(['sections', 'indicators', 'questionPlacements'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('framework_type')) {
            $query->where('framework_type', $request->string('framework_type'));
        }

        return view('admin.framework-versions.index', [
            'frameworks' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.framework-versions.create', [
            'modules' => AssessmentModule::where('is_active', true)->orderBy('module_name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'module_id' => ['required', 'integer', Rule::exists('assessment_modules', 'module_id')],
            'framework_type' => ['required', 'in:DEPARTMENT,FOCUSED'],
            'display_name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'purpose' => ['nullable', 'string'],
            'source_authority' => ['required', 'string', 'max:180'],
            'source_url' => ['nullable', 'url', 'max:2000'],
            'license_code' => ['required', 'string', 'max:80'],
            'methodology_notes' => ['nullable', 'string'],
            'source_summary' => ['nullable', 'string'],
        ]);

        $nextVersion = ((int) DepartmentFrameworkVersion::where('module_id', $validated['module_id'])->max('version_number')) + 1;

        $framework = DepartmentFrameworkVersion::create([
            ...$validated,
            'version_number' => $nextVersion,
            'status' => DepartmentFrameworkVersion::STATUS_DRAFT,
        ]);

        return redirect()->route('admin.framework-versions.show', $framework)
            ->with('success', 'Draft framework version created.');
    }

    public function show(DepartmentFrameworkVersion $framework, GovernanceDependencyService $dependencies): View
    {
        $framework->load([
            'module',
            'parentVersion',
            'sections.indicators.placements.questionVersion.questionType',
            'questionPlacements.questionVersion.questionType',
            'questionPlacements.section',
            'questionPlacements.indicator',
        ]);

        return view('admin.framework-versions.show', [
            'framework' => $framework,
            'modules' => AssessmentModule::where('is_active', true)->orderBy('module_name')->get(),
            'publishedQuestionVersions' => QuestionVersion::with(['question', 'questionType'])
                ->where('status', QuestionVersion::STATUS_PUBLISHED)
                ->orderByDesc('published_at')
                ->limit(500)
                ->get(),
            'subIndices' => SubIndex::orderBy('full_name')->get(),
            'dependencySummary' => $dependencies->frameworkVersion($framework),
        ]);
    }

    public function update(Request $request, DepartmentFrameworkVersion $framework): RedirectResponse
    {
        $this->ensureDraft($framework);

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'purpose' => ['nullable', 'string'],
            'source_authority' => ['required', 'string', 'max:180'],
            'source_url' => ['nullable', 'url', 'max:2000'],
            'license_code' => ['required', 'string', 'max:80'],
            'methodology_notes' => ['nullable', 'string'],
            'source_summary' => ['nullable', 'string'],
            'review_notes' => ['nullable', 'string'],
            'effective_date' => ['nullable', 'date'],
        ]);

        $framework->update($validated);

        return back()->with('success', 'Framework metadata saved.');
    }

    public function storeSection(Request $request, DepartmentFrameworkVersion $framework): RedirectResponse
    {
        $this->ensureDraft($framework);

        $validated = $request->validate([
            'section_code' => ['required', 'string', 'max:80'],
            'section_name' => ['required', 'string', 'max:180'],
            'purpose' => ['nullable', 'string'],
            'display_order' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        FrameworkSection::create([
            ...$validated,
            'section_code' => strtoupper($validated['section_code']),
            'framework_version_id' => $framework->framework_version_id,
        ]);

        return back()->with('success', 'Section added.');
    }

    public function updateSection(Request $request, DepartmentFrameworkVersion $framework, FrameworkSection $section): RedirectResponse
    {
        $this->ensureDraft($framework);
        abort_unless($section->framework_version_id === $framework->framework_version_id, 404);

        $section->update($request->validate([
            'section_name' => ['required', 'string', 'max:180'],
            'purpose' => ['nullable', 'string'],
            'display_order' => ['required', 'integer', 'min:1', 'max:999'],
        ]));

        return back()->with('success', 'Section saved.');
    }

    public function destroySection(DepartmentFrameworkVersion $framework, FrameworkSection $section): RedirectResponse
    {
        $this->ensureDraft($framework);
        abort_unless($section->framework_version_id === $framework->framework_version_id, 404);
        $section->delete();

        return back()->with('success', 'Section removed.');
    }

    public function storeIndicator(Request $request, DepartmentFrameworkVersion $framework): RedirectResponse
    {
        $this->ensureDraft($framework);

        $validated = $request->validate([
            'framework_section_id' => ['required', 'uuid', Rule::exists('framework_sections', 'framework_section_id')->where('framework_version_id', $framework->framework_version_id)],
            'indicator_code' => ['required', 'string', 'max:80'],
            'indicator_name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'display_order' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        FrameworkIndicator::create([
            ...$validated,
            'indicator_code' => strtoupper($validated['indicator_code']),
            'framework_version_id' => $framework->framework_version_id,
        ]);

        return back()->with('success', 'Indicator added.');
    }

    public function updateIndicator(Request $request, DepartmentFrameworkVersion $framework, FrameworkIndicator $indicator): RedirectResponse
    {
        $this->ensureDraft($framework);
        abort_unless($indicator->framework_version_id === $framework->framework_version_id, 404);

        $indicator->update($request->validate([
            'framework_section_id' => ['required', 'uuid', Rule::exists('framework_sections', 'framework_section_id')->where('framework_version_id', $framework->framework_version_id)],
            'indicator_name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'display_order' => ['required', 'integer', 'min:1', 'max:999'],
        ]));

        return back()->with('success', 'Indicator saved.');
    }

    public function destroyIndicator(DepartmentFrameworkVersion $framework, FrameworkIndicator $indicator): RedirectResponse
    {
        $this->ensureDraft($framework);
        abort_unless($indicator->framework_version_id === $framework->framework_version_id, 404);
        $indicator->delete();

        return back()->with('success', 'Indicator removed.');
    }

    public function storePlacement(Request $request, DepartmentFrameworkVersion $framework): RedirectResponse
    {
        $this->ensureDraft($framework);

        $validated = $request->validate([
            'framework_section_id' => ['required', 'uuid', Rule::exists('framework_sections', 'framework_section_id')->where('framework_version_id', $framework->framework_version_id)],
            'framework_indicator_id' => ['required', 'uuid', Rule::exists('framework_indicators', 'framework_indicator_id')->where('framework_version_id', $framework->framework_version_id)],
            'question_version_id' => ['required', 'uuid', Rule::exists('question_versions', 'question_version_id')->where('status', QuestionVersion::STATUS_PUBLISHED)],
            'sub_index_id' => ['nullable', 'integer', Rule::exists('sub_indices', 'sub_index_id')],
            'display_order' => ['required', 'integer', 'min:1', 'max:9999'],
            'is_required' => ['nullable', 'boolean'],
            'scoring_contribution' => ['nullable', 'boolean'],
            'weight' => ['required', 'numeric', 'min:0', 'max:999'],
            'criticality' => ['required', 'string', 'max:30'],
            'evidence_expectation' => ['nullable', 'string'],
            'help_text' => ['nullable', 'string'],
            'local_display_text' => ['nullable', 'string'],
        ]);

        $questionVersion = QuestionVersion::findOrFail($validated['question_version_id']);

        FrameworkQuestionPlacement::create([
            ...$validated,
            'framework_version_id' => $framework->framework_version_id,
            'question_id' => $questionVersion->question_id,
            'is_required' => (bool) ($validated['is_required'] ?? true),
            'scoring_contribution' => (bool) ($validated['scoring_contribution'] ?? false),
        ]);

        return back()->with('success', 'Question placement added.');
    }

    public function destroyPlacement(DepartmentFrameworkVersion $framework, FrameworkQuestionPlacement $placement): RedirectResponse
    {
        $this->ensureDraft($framework);
        abort_unless($placement->framework_version_id === $framework->framework_version_id, 404);
        $placement->delete();

        return back()->with('success', 'Question placement removed.');
    }

    public function publish(DepartmentFrameworkVersion $framework, DepartmentFrameworkPublishingService $publisher): RedirectResponse
    {
        try {
            $publisher->publish($framework, auth()->id());
        } catch (\Throwable $exception) {
            return back()->withErrors(['publication' => $exception->getMessage()]);
        }

        return back()->with('success', 'Framework version published and frozen.');
    }

    public function supersede(DepartmentFrameworkVersion $framework, AuditService $audit, GovernanceDependencyService $dependencies): RedirectResponse
    {
        if ($framework->status !== DepartmentFrameworkVersion::STATUS_PUBLISHED) {
            return back()->withErrors(['status' => 'Only published framework versions can be superseded.']);
        }

        $dependencySummary = $dependencies->frameworkVersion($framework);

        $successor = DB::transaction(function () use ($framework, $audit, $dependencySummary): DepartmentFrameworkVersion {
            $framework->load(['sections.indicators', 'questionPlacements']);
            $nextVersion = ((int) DepartmentFrameworkVersion::where('module_id', $framework->module_id)->max('version_number')) + 1;

            $successor = DepartmentFrameworkVersion::create([
                'module_id' => $framework->module_id,
                'framework_type' => $framework->framework_type,
                'version_number' => $nextVersion,
                'status' => DepartmentFrameworkVersion::STATUS_DRAFT,
                'display_name' => $framework->display_name,
                'description' => $framework->description,
                'purpose' => $framework->purpose,
                'source_authority' => $framework->source_authority,
                'source_url' => $framework->source_url,
                'license_code' => $framework->license_code,
                'methodology_notes' => $framework->methodology_notes,
                'source_summary' => $framework->source_summary,
                'review_notes' => 'Successor draft created from v'.$framework->version_number.'.',
                'parent_version_id' => $framework->framework_version_id,
            ]);

            $sectionMap = [];
            foreach ($framework->sections as $section) {
                $newSection = FrameworkSection::create([
                    'framework_version_id' => $successor->framework_version_id,
                    'section_code' => $section->section_code,
                    'section_name' => $section->section_name,
                    'purpose' => $section->purpose,
                    'display_order' => $section->display_order,
                ]);
                $sectionMap[$section->framework_section_id] = $newSection->framework_section_id;
            }

            $indicatorMap = [];
            foreach ($framework->sections->flatMap->indicators as $indicator) {
                $newIndicator = FrameworkIndicator::create([
                    'framework_version_id' => $successor->framework_version_id,
                    'framework_section_id' => $sectionMap[$indicator->framework_section_id],
                    'indicator_code' => $indicator->indicator_code,
                    'indicator_name' => $indicator->indicator_name,
                    'description' => $indicator->description,
                    'display_order' => $indicator->display_order,
                ]);
                $indicatorMap[$indicator->framework_indicator_id] = $newIndicator->framework_indicator_id;
            }

            foreach ($framework->questionPlacements as $placement) {
                FrameworkQuestionPlacement::create([
                    'framework_version_id' => $successor->framework_version_id,
                    'framework_section_id' => $sectionMap[$placement->framework_section_id],
                    'framework_indicator_id' => $indicatorMap[$placement->framework_indicator_id],
                    'question_id' => $placement->question_id,
                    'question_version_id' => $placement->question_version_id,
                    'sub_index_id' => $placement->sub_index_id,
                    'display_order' => $placement->display_order,
                    'is_required' => $placement->is_required,
                    'applicability' => $placement->applicability,
                    'evidence_expectation' => $placement->evidence_expectation,
                    'weight' => $placement->weight,
                    'scoring_contribution' => $placement->scoring_contribution,
                    'criticality' => $placement->criticality,
                    'help_text' => $placement->help_text,
                    'local_display_text' => $placement->local_display_text,
                    'metadata' => $placement->metadata,
                ]);
            }

            $old = ['status' => $framework->status];
            $framework->update(['status' => DepartmentFrameworkVersion::STATUS_SUPERSEDED]);
            $audit->record('department.framework.superseded', $framework->fresh(), $old, [
                'status' => DepartmentFrameworkVersion::STATUS_SUPERSEDED,
                'successor_framework_version_id' => $successor->framework_version_id,
                'dependency_summary' => $dependencySummary,
            ]);

            return $successor;
        });

        return redirect()->route('admin.framework-versions.show', $successor)
            ->with('success', 'Successor draft framework created. Existing snapshots and reports still reference the original frozen version.');
    }

    public function archive(DepartmentFrameworkVersion $framework, AuditService $audit, GovernanceDependencyService $dependencies): RedirectResponse
    {
        if (! in_array($framework->status, [DepartmentFrameworkVersion::STATUS_DRAFT, DepartmentFrameworkVersion::STATUS_PUBLISHED], true)) {
            return back()->withErrors(['status' => 'This framework version is already closed and cannot be archived again.']);
        }

        $dependencySummary = $dependencies->frameworkVersion($framework);
        if ($dependencies->hasBlockingArchiveDependencies($dependencySummary)) {
            return back()->withErrors(['archive' => 'This framework version is referenced by catalogue releases, snapshots, or reports and cannot be archived. Create a successor framework instead.']);
        }

        $old = ['status' => $framework->status];
        $framework->update(['status' => DepartmentFrameworkVersion::STATUS_ARCHIVED]);
        $audit->record('department.framework.archived', $framework->fresh(), $old, [
            'status' => DepartmentFrameworkVersion::STATUS_ARCHIVED,
            'dependency_summary' => $dependencySummary,
        ]);

        return back()->with('success', 'Framework version archived.');
    }

    private function ensureDraft(DepartmentFrameworkVersion $framework): void
    {
        abort_unless($framework->status === DepartmentFrameworkVersion::STATUS_DRAFT, 403, 'Only draft framework versions can be edited.');
    }
}
