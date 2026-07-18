<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\AuditLog;
use App\Models\DepartmentFrameworkVersion;
use App\Models\PlatformSetting;
use App\Models\QuestionVersion;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $workspaceCount = Workspace::count();
        $moduleCount = AssessmentModule::count();
        $activeModuleCount = AssessmentModule::where('is_active', true)->count();
        $totalAssessments = Assessment::where('status', Assessment::STATUS_COMPLETE)->count();
        $emailEnabled = PlatformSetting::get('email.notifications_enabled', false);
        $platformAdminCount = User::where('platform_role', 'PLATFORM_ADMIN')->count();
        $publishedQuestionVersions = QuestionVersion::where('status', QuestionVersion::STATUS_PUBLISHED)->count();
        $publishedFrameworks = DepartmentFrameworkVersion::where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)->count();
        $publishedCatalogueReleases = AssessmentCatalogueRelease::where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)->count();
        $recentAuditCount = AuditLog::where('created_at', '>=', now()->subDays(7))->count();

        return view('admin.dashboard', compact(
            'workspaceCount',
            'moduleCount',
            'activeModuleCount',
            'totalAssessments',
            'emailEnabled',
            'platformAdminCount',
            'publishedQuestionVersions',
            'publishedFrameworks',
            'publishedCatalogueReleases',
            'recentAuditCount',
        ));
    }
}
