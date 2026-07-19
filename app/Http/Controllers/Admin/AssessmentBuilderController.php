<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\HealthDomain;
use App\Services\AssessmentPublicationService;
use App\Services\AssessmentReadinessService;
use App\Services\AssessmentVersionService;
use App\Services\AuditService;
use App\Services\FrameworkContentService;
use App\Services\GovernanceDependencyService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The Assessment Builder is the simple authoring surface. It presents one governed
 * department framework version as "an assessment" and hides governance vocabulary behind
 * product language.
 *
 * It orchestrates existing governed models and services and owns no lifecycle, publication
 * or scoring rules of its own. Draft protection, immutability and publication validation
 * stay where they already live, and Advanced Tools continues to expose the underlying
 * objects unchanged.
 */
class AssessmentBuilderController extends Controller
{
    /**
     * Wizard steps. Only Basic Information is implemented at this stage. The remaining
     * steps are shown as upcoming so the shape of the workflow is visible without
     * presenting controls that do nothing.
     *
     * @var list<array{key: string, label: string, available: bool}>
     */
    public const STEPS = [
        ['key' => 'basics', 'label' => 'Basic Information', 'available' => true],
        ['key' => 'build', 'label' => 'Build Assessment', 'available' => true],
        ['key' => 'review', 'label' => 'Review', 'available' => true],
        ['key' => 'publish', 'label' => 'Publish', 'available' => true],
    ];

    public function index(Request $request): View
    {
        $query = DepartmentFrameworkVersion::query()
            ->with('module')
            ->withCount(['sections', 'questionPlacements'])
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->lower().'%';
            $query->whereRaw('LOWER(display_name) LIKE ?', [$search]);
        }

        return view('admin.assessment-builder.index', [
            'assessments' => $query->paginate(20)->withQueryString(),
            'statuses' => [
                DepartmentFrameworkVersion::STATUS_DRAFT,
                DepartmentFrameworkVersion::STATUS_PUBLISHED,
                DepartmentFrameworkVersion::STATUS_SUPERSEDED,
                DepartmentFrameworkVersion::STATUS_ARCHIVED,
            ],
            'draftCount' => DepartmentFrameworkVersion::where('status', DepartmentFrameworkVersion::STATUS_DRAFT)->count(),
            'publishedCount' => DepartmentFrameworkVersion::where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.assessment-builder.create', [
            'departments' => $this->availableDepartments(),
            'steps' => self::STEPS,
            'currentStep' => 'basics',
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $validated = $this->validateBasics($request);

        $nextVersion = ((int) DepartmentFrameworkVersion::where('module_id', $validated['module_id'])->max('version_number')) + 1;

        $assessment = DepartmentFrameworkVersion::create([
            ...$validated,
            'framework_type' => DepartmentFrameworkVersion::TYPE_FOCUSED,
            'version_number' => $nextVersion,
            'status' => DepartmentFrameworkVersion::STATUS_DRAFT,
        ]);

        $audit->record('assessment.draft.created', $assessment, newValues: [
            'display_name' => $assessment->display_name,
            'module_id' => $assessment->module_id,
            'version_number' => $assessment->version_number,
        ]);

        return redirect()->route('admin.assessments.show', $assessment)
            ->with('success', 'Draft saved. You can come back and continue building this assessment at any time.');
    }

    public function show(DepartmentFrameworkVersion $assessment): View
    {
        $assessment->load('module')->loadCount(['sections', 'questionPlacements']);

        return view('admin.assessment-builder.show', [
            'assessment' => $assessment,
            'steps' => self::STEPS,
            'currentStep' => 'basics',
            'isEditable' => $this->isEditable($assessment),
        ]);
    }

    /**
     * The Build Assessment step: sections with their questions, in author language.
     */
    public function build(DepartmentFrameworkVersion $assessment): View
    {
        $assessment->load([
            'module',
            'sections.questionPlacements.questionVersion.questionType',
            'sections.questionPlacements.question',
        ]);

        return view('admin.assessment-builder.build', [
            'assessment' => $assessment,
            'steps' => self::STEPS,
            'currentStep' => 'build',
            'isEditable' => $this->isEditable($assessment),
            'questionCount' => $assessment->sections->sum(fn ($section) => $section->questionPlacements->count()),
        ]);
    }

    /**
     * The Review step: everything the author built, plus anything blocking publication.
     */
    public function review(DepartmentFrameworkVersion $assessment, AssessmentReadinessService $readiness, AssessmentVersionService $versions): View
    {
        $assessment->load([
            'module.healthDomains',
            'sections.questionPlacements.questionVersion.questionType',
            'sections.questionPlacements.subIndex',
        ]);

        return view('admin.assessment-builder.review', [
            'assessment' => $assessment,
            'steps' => self::STEPS,
            'currentStep' => 'review',
            'isEditable' => $this->isEditable($assessment),
            'readiness' => $readiness->evaluate($assessment),
            'healthAreas' => HealthDomain::orderBy('domain_name')->get(['health_domain_id', 'domain_name']),
            'suggestedHealthAreaId' => $assessment->module?->healthDomains?->first()?->health_domain_id,
            'publishedRelease' => AssessmentCatalogueRelease::whereHas(
                'departmentFrameworkVersions',
                fn ($query) => $query->where('department_framework_versions.framework_version_id', $assessment->framework_version_id)
            )->where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)->first(),
            'openDraftVersion' => $versions->openDraftFor($assessment),
        ]);
    }

    /**
     * Source and licence are required by the framework publisher. They are asked for at
     * Review rather than at draft creation so starting an assessment stays simple.
     */
    public function saveProvenance(Request $request, DepartmentFrameworkVersion $assessment, AuditService $audit): RedirectResponse
    {
        if (! $this->isEditable($assessment)) {
            return back()->withErrors(['status' => 'This assessment has been published and cannot be edited.']);
        }

        $validated = $request->validate([
            'source_authority' => ['required', 'string', 'max:180'],
            'license_code' => ['required', 'string', 'max:80'],
            'source_url' => ['nullable', 'url', 'max:2000'],
        ], [], [
            'source_authority' => 'source',
            'license_code' => 'usage terms',
            'source_url' => 'link',
        ]);

        $old = $assessment->only(['source_authority', 'license_code', 'source_url']);
        $assessment->update($validated);
        $audit->record('assessment.provenance.recorded', $assessment->fresh(), $old, $validated);

        return back()->with('success', 'Source and usage details saved.');
    }

    public function publish(Request $request, DepartmentFrameworkVersion $assessment, AssessmentPublicationService $publisher): RedirectResponse
    {
        $validated = $request->validate([
            'health_domain_id' => ['required', 'integer', Rule::exists('health_domains', 'health_domain_id')],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Confirm that you understand publishing is permanent.',
        ], ['health_domain_id' => 'health area']);

        try {
            $publisher->publish($assessment, (int) $validated['health_domain_id'], auth()->id());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        } catch (\Throwable $exception) {
            return back()->withErrors(['publish' => $exception->getMessage()]);
        }

        return redirect()->route('admin.assessments.review', $assessment)
            ->with('success', 'Assessment published. It is now available to workspaces and is locked.');
    }

    /**
     * Starts a new version of a published assessment. The published one is untouched.
     */
    public function createVersion(DepartmentFrameworkVersion $assessment, AssessmentVersionService $versions): RedirectResponse
    {
        try {
            $successor = $versions->startNewVersion($assessment, auth()->id());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('admin.assessments.build', $successor)
            ->with('success', 'Version '.$successor->version_number.' created as a draft. The published version stays available until this one is published.');
    }

    /**
     * Discards a draft assessment.
     *
     * Without this a draft created by mistake stays in the list forever, and a mistaken
     * new version is worse than untidy: only one open draft version is allowed per
     * published assessment, so it blocks any further version permanently.
     *
     * Only a draft can be discarded. Published, superseded and archived versions are
     * immutable historical records and are refused by the model guard as well.
     */
    public function destroy(DepartmentFrameworkVersion $assessment, AuditService $audit, GovernanceDependencyService $dependencies): RedirectResponse
    {
        if (! $this->isEditable($assessment)) {
            return back()->withErrors([
                'status' => 'Only a draft can be discarded. Published assessments are kept so that reports stay reproducible.',
            ]);
        }

        $summary = $dependencies->frameworkVersion($assessment);
        if ($dependencies->hasBlockingArchiveDependencies($summary)) {
            return back()->withErrors([
                'status' => 'This draft is already referenced by other content and cannot be discarded.',
            ]);
        }

        $name = $assessment->display_name;
        $predecessor = $assessment->parent_version_id;

        $audit->record('assessment.draft.discarded', $assessment, ['display_name' => $name], [
            'framework_version_id' => $assessment->framework_version_id,
            'version_number' => $assessment->version_number,
            'previous_framework_version_id' => $predecessor,
        ]);

        // Sections, indicators and placements are removed by their cascading foreign keys.
        // Question identities and versions are deliberately left: they are reusable library
        // content and may be placed in other assessments.
        $assessment->delete();

        return redirect()->route('admin.assessments.index')
            ->with('success', 'Draft "'.$name.'" discarded.');
    }

    /**
     * Read-only preview of what a respondent sees. Renders the frozen published payload
     * for a published assessment, and the current draft content otherwise.
     */
    public function preview(DepartmentFrameworkVersion $assessment, FrameworkContentService $content): View
    {
        $payload = $assessment->status === DepartmentFrameworkVersion::STATUS_DRAFT
            ? $content->frameworkPayload($assessment)
            : ($assessment->published_payload ?? $content->frameworkPayload($assessment));

        $questions = collect($payload['questions'] ?? [])->sortBy('display_order')->values();

        return view('admin.assessment-builder.preview', [
            'assessment' => $assessment,
            'steps' => self::STEPS,
            'currentStep' => 'review',
            'sections' => collect($payload['sections'] ?? [])->sortBy('display_order')->values(),
            'questionsBySection' => $questions->groupBy('section_id'),
            'isFrozen' => $assessment->status !== DepartmentFrameworkVersion::STATUS_DRAFT,
        ]);
    }

    public function edit(DepartmentFrameworkVersion $assessment): View
    {
        return view('admin.assessment-builder.edit', [
            'assessment' => $assessment->load('module'),
            'departments' => $this->availableDepartments(),
            'steps' => self::STEPS,
            'currentStep' => 'basics',
            'isEditable' => $this->isEditable($assessment),
        ]);
    }

    public function update(Request $request, DepartmentFrameworkVersion $assessment, AuditService $audit): RedirectResponse
    {
        if (! $this->isEditable($assessment)) {
            return back()->withErrors([
                'status' => 'This assessment has been published and cannot be edited. Create a new version to make changes.',
            ]);
        }

        $validated = $this->validateBasics($request);
        $old = $assessment->only(['display_name', 'description', 'module_id', 'purpose']);

        $assessment->update($validated);
        $audit->record('assessment.draft.updated', $assessment->fresh(), $old, $validated);

        return redirect()->route('admin.assessments.show', $assessment)
            ->with('success', 'Basic information saved.');
    }

    /**
     * @return array{display_name: string, description: ?string, module_id: int, purpose: ?string}
     */
    private function validateBasics(Request $request): array
    {
        return $request->validate([
            'display_name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'module_id' => ['required', 'integer', Rule::exists('assessment_modules', 'module_id')->where('is_active', true)],
            'purpose' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'display_name' => 'assessment name',
            'module_id' => 'department',
            'purpose' => 'intended use',
        ]);
    }

    private function isEditable(DepartmentFrameworkVersion $assessment): bool
    {
        return $assessment->status === DepartmentFrameworkVersion::STATUS_DRAFT;
    }

    private function availableDepartments(): Collection
    {
        return AssessmentModule::where('is_active', true)
            ->orderBy('module_name')
            ->get(['module_id', 'module_code', 'module_name']);
    }
}
