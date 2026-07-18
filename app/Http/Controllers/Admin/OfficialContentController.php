<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FacilityProfile;
use App\Models\FrameworkIndicator;
use App\Models\FrameworkQuestionPlacement;
use App\Models\FrameworkSection;
use App\Models\HealthDomain;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\QuestionVersion;
use Illuminate\Contracts\View\View;

class OfficialContentController extends Controller
{
    public function index(): View
    {
        $stats = [
            'Departments' => AssessmentModule::count(),
            'Question groups' => QuestionGroup::count(),
            'Question identities' => Question::count(),
            'Question versions' => QuestionVersion::count(),
            'Framework versions' => DepartmentFrameworkVersion::count(),
            'Sections' => FrameworkSection::count(),
            'Indicators' => FrameworkIndicator::count(),
            'Placements' => FrameworkQuestionPlacement::count(),
            'Catalogue releases' => AssessmentCatalogueRelease::count(),
            'Facility profiles' => FacilityProfile::count(),
            'Health domains' => HealthDomain::count(),
        ];

        $latestQuestionVersions = QuestionVersion::with(['question', 'questionType'])
            ->latest()
            ->limit(8)
            ->get();
        $latestFrameworks = DepartmentFrameworkVersion::with('module')
            ->latest()
            ->limit(8)
            ->get();
        $latestReleases = AssessmentCatalogueRelease::with(['facilityProfile', 'healthDomain'])
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.official-content.index', compact(
            'stats',
            'latestQuestionVersions',
            'latestFrameworks',
            'latestReleases',
        ));
    }
}
