<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentCatalogueRelease;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FacilityProfile;
use App\Models\HealthDomain;
use App\Services\ScoringService;
use App\Services\CataloguePublishingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogueReleaseController extends Controller
{
    public function index(Request $request): View
    {
        $query = AssessmentCatalogueRelease::with(['facilityProfile', 'healthDomain'])
            ->withCount('departmentFrameworkVersions')
            ->latest();

        if ($request->filled('creation_path')) {
            $query->where('creation_path', $request->string('creation_path'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('admin.catalogue-releases.index', [
            'releases' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.catalogue-releases.create', [
            'facilityProfiles' => FacilityProfile::where('status', FacilityProfile::STATUS_PUBLISHED)->orderBy('profile_name')->get(),
            'healthDomains' => HealthDomain::orderBy('domain_name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'release_code' => ['required', 'string', 'max:80', 'unique:assessment_catalogue_releases,release_code'],
            'release_name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'creation_path' => ['required', 'in:COMPREHENSIVE,FOCUSED'],
            'facility_profile_id' => ['nullable', 'required_if:creation_path,COMPREHENSIVE', 'uuid', Rule::exists('facility_profiles', 'facility_profile_id')],
            'health_domain_id' => ['nullable', 'required_if:creation_path,FOCUSED', 'integer', Rule::exists('health_domains', 'health_domain_id')],
        ]);

        $release = AssessmentCatalogueRelease::create([
            ...$validated,
            'release_code' => strtoupper($validated['release_code']),
            'facility_profile_id' => $validated['creation_path'] === 'COMPREHENSIVE' ? $validated['facility_profile_id'] : null,
            'health_domain_id' => $validated['creation_path'] === 'FOCUSED' ? $validated['health_domain_id'] : null,
            'aggregation_policy' => ['method' => 'MEAN_OF_SCORED_SUB_INDICES'],
            'composition_rules' => ['deduplicate_questions' => true],
            'collection_config' => [
                'allows_multi_respondent' => false,
                'scoring_profile_version' => ScoringService::ALGORITHM_VERSION,
            ],
            'status' => AssessmentCatalogueRelease::STATUS_DRAFT,
        ]);

        return redirect()->route('admin.catalogue-releases.show', $release)
            ->with('success', 'Draft catalogue release created.');
    }

    public function show(AssessmentCatalogueRelease $release): View
    {
        $release->load(['facilityProfile.departments', 'healthDomain', 'departmentFrameworkVersions.module']);

        return view('admin.catalogue-releases.show', [
            'release' => $release,
            'publishedFrameworks' => DepartmentFrameworkVersion::with('module')
                ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
                ->orderByDesc('published_at')
                ->get(),
            'facilityProfiles' => FacilityProfile::where('status', FacilityProfile::STATUS_PUBLISHED)->orderBy('profile_name')->get(),
            'healthDomains' => HealthDomain::orderBy('domain_name')->get(),
        ]);
    }

    public function update(Request $request, AssessmentCatalogueRelease $release): RedirectResponse
    {
        $this->ensureDraft($release);

        $validated = $request->validate([
            'release_name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'facility_profile_id' => ['nullable', 'uuid', Rule::exists('facility_profiles', 'facility_profile_id')],
            'health_domain_id' => ['nullable', 'integer', Rule::exists('health_domains', 'health_domain_id')],
            'allows_multi_respondent' => ['nullable', 'boolean'],
            'minimum_completed_respondents' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $allowsMulti = (bool) ($validated['allows_multi_respondent'] ?? false);
        $release->update([
            'release_name' => $validated['release_name'],
            'description' => $validated['description'] ?? null,
            'facility_profile_id' => $release->creation_path === 'COMPREHENSIVE' ? $validated['facility_profile_id'] : null,
            'health_domain_id' => $release->creation_path === 'FOCUSED' ? $validated['health_domain_id'] : null,
            'collection_config' => [
                'allows_multi_respondent' => $allowsMulti,
                'minimum_completed_respondents' => $allowsMulti ? (int) ($validated['minimum_completed_respondents'] ?? 1) : null,
                'aggregation_method' => $allowsMulti ? 'ARITHMETIC_MEAN' : null,
                'scoring_profile_version' => ScoringService::ALGORITHM_VERSION,
            ],
        ]);

        return back()->with('success', 'Catalogue release saved.');
    }

    public function attachFramework(Request $request, AssessmentCatalogueRelease $release): RedirectResponse
    {
        $this->ensureDraft($release);

        $validated = $request->validate([
            'framework_version_id' => ['required', 'uuid', Rule::exists('department_framework_versions', 'framework_version_id')->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)],
            'applicability' => ['required', 'in:REQUIRED,DEFAULT,OPTIONAL'],
            'display_order' => ['required', 'integer', 'min:1', 'max:999'],
            'area_label' => ['nullable', 'string', 'max:180'],
        ]);

        $framework = DepartmentFrameworkVersion::findOrFail($validated['framework_version_id']);

        $release->departmentFrameworkVersions()->syncWithoutDetaching([
            $framework->framework_version_id => [
                'module_id' => $framework->module_id,
                'applicability' => $validated['applicability'],
                'display_order' => $validated['display_order'],
                'area_label' => $validated['area_label'] ?? null,
            ],
        ]);

        return back()->with('success', 'Framework pinned to release.');
    }

    public function detachFramework(AssessmentCatalogueRelease $release, DepartmentFrameworkVersion $framework): RedirectResponse
    {
        $this->ensureDraft($release);
        $release->departmentFrameworkVersions()->detach($framework->framework_version_id);

        return back()->with('success', 'Framework removed from release.');
    }

    public function publish(AssessmentCatalogueRelease $release, CataloguePublishingService $publisher): RedirectResponse
    {
        try {
            $publisher->publish($release, auth()->id());
        } catch (\Throwable $exception) {
            return back()->withErrors(['publication' => $exception->getMessage()]);
        }

        return back()->with('success', 'Catalogue release published and frozen.');
    }

    private function ensureDraft(AssessmentCatalogueRelease $release): void
    {
        abort_unless($release->status === AssessmentCatalogueRelease::STATUS_DRAFT, 403, 'Only draft catalogue releases can be edited.');
    }
}
