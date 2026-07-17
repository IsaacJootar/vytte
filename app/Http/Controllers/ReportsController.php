<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Project;
use Illuminate\Contracts\View\View;

class ReportsController extends Controller
{
    public function index(): View
    {
        $workspaceProjectIds = Project::select('project_id');

        $assessments = Assessment::whereIn('project_id', $workspaceProjectIds)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->with([
                'project',
                'target',
                'score.maturityLevel',
                'reportSnapshot',
                'shareLinks' => fn ($query) => $query->latest('created_at'),
            ])
            ->latest('completed_at')
            ->paginate(20);

        return view('reports.index', compact('assessments'));
    }
}
