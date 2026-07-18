<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): View
    {
        // All project IDs scoped to the current workspace (WorkspaceScope applies automatically)
        $workspaceProjectIds = Project::select('project_id');

        $activeProjectCount = Project::where('status', 'ACTIVE')->count();

        $totalAssessments = Assessment::whereIn('project_id', $workspaceProjectIds)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->count();

        $avgScore = null;
        if ($totalAssessments > 0) {
            $avgScore = DB::table('assessment_scores as s')
                ->join('assessments as a', 'a.assessment_id', '=', 's.assessment_id')
                ->whereIn('a.project_id', $workspaceProjectIds)
                ->whereNotNull('s.overall_score')
                ->avg('s.overall_score');

            $avgScore = $avgScore !== null ? round((float) $avgScore, 1) : null;
        }

        // Score distribution counts
        $allScores = DB::table('assessment_scores as s')
            ->join('assessments as a', 'a.assessment_id', '=', 's.assessment_id')
            ->whereIn('a.project_id', $workspaceProjectIds)
            ->whereNotNull('s.overall_score')
            ->pluck('s.overall_score')
            ->map(fn ($s) => (float) $s);

        $distribution = [
            'strong' => $allScores->filter(fn ($s) => $s >= 70)->count(),
            'moderate' => $allScores->filter(fn ($s) => $s >= 45 && $s < 70)->count(),
            'weak' => $allScores->filter(fn ($s) => $s < 45)->count(),
        ];

        $recentProjects = Project::with(['targets.targetType'])
            ->where('status', 'ACTIVE')
            ->latest()
            ->limit(5)
            ->get();

        $recentAssessments = Assessment::whereIn('project_id', $workspaceProjectIds)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->with(['project', 'moduleScope.module', 'score', 'reportSnapshot', 'templateVersion.template', 'catalogueRelease'])
            ->latest('completed_at')
            ->limit(5)
            ->get();

        return view('dashboard', compact(
            'activeProjectCount',
            'totalAssessments',
            'avgScore',
            'distribution',
            'recentProjects',
            'recentAssessments',
        ));
    }
}
