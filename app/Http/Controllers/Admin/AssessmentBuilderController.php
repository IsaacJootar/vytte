<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        ['key' => 'review', 'label' => 'Review', 'available' => false],
        ['key' => 'publish', 'label' => 'Publish', 'available' => false],
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
