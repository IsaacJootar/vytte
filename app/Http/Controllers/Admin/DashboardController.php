<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentModule;
use App\Models\PlatformSetting;
use App\Models\Workspace;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $workspaceCount = Workspace::count();
        $moduleCount = AssessmentModule::count();
        $activeModuleCount = AssessmentModule::where('is_active', true)->count();
        $totalAssessments = Assessment::where('status', 'COMPLETE')->count();
        $emailEnabled = PlatformSetting::get('email.notifications_enabled', false);

        return view('admin.dashboard', compact(
            'workspaceCount',
            'moduleCount',
            'activeModuleCount',
            'totalAssessments',
            'emailEnabled',
        ));
    }
}
