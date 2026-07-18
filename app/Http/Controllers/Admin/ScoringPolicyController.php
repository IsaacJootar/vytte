<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentCatalogueRelease;
use App\Models\DepartmentFrameworkVersion;
use App\Services\ScoringService;
use Illuminate\Contracts\View\View;

class ScoringPolicyController extends Controller
{
    public function index(): View
    {
        $frameworks = DepartmentFrameworkVersion::with('module')
            ->select(['framework_version_id', 'module_id', 'display_name', 'framework_type', 'status', 'scoring_version', 'critical_failure_rules'])
            ->latest()
            ->limit(50)
            ->get();

        $catalogues = AssessmentCatalogueRelease::with(['facilityProfile', 'healthDomain'])
            ->select(['catalogue_release_id', 'release_code', 'release_name', 'creation_path', 'status', 'aggregation_policy', 'collection_config', 'facility_profile_id', 'health_domain_id'])
            ->latest()
            ->limit(50)
            ->get();

        return view('admin.scoring-policies.index', [
            'frameworks' => $frameworks,
            'catalogues' => $catalogues,
            'currentScoringVersion' => ScoringService::ALGORITHM_VERSION,
        ]);
    }
}
