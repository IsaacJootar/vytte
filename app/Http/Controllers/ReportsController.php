<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        $workspaceProjectIds = Project::select('project_id');

        $search = trim((string) $request->query('search', ''));

        // The single most recent report, shown as a hero at the top of the hub (independent
        // of the paginated list below and of any active search).
        $latest = Assessment::whereIn('project_id', $workspaceProjectIds)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->with(['project', 'target', 'score', 'reportSnapshot'])
            ->latest('completed_at')
            ->first();
        $latestReport = $this->latestReportPreview($latest);

        $assessments = Assessment::whereIn('project_id', $workspaceProjectIds)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.strtolower($search).'%';
                $query->where(function ($inner) use ($like) {
                    $inner->whereHas('target', fn ($t) => $t->whereRaw('LOWER(name) LIKE ?', [$like]))
                        ->orWhereHas('project', fn ($p) => $p->whereRaw('LOWER(name) LIKE ?', [$like]));
                });
            })
            ->with([
                'project',
                'target',
                'score.maturityLevel',
                'reportSnapshot',
                'catalogueRelease',
                'moduleScope.module',
                'shareLinks' => fn ($query) => $query->latest('created_at'),
            ])
            ->latest('completed_at')
            ->paginate(20)
            ->withQueryString();

        // The headline of each report, read straight from the frozen snapshot (no recompute)
        // so the hub stays fast: the biggest issue and the top risk.
        $previews = $assessments->getCollection()->mapWithKeys(function ($assessment) {
            $intel = $assessment->reportSnapshot?->payload['intelligence'] ?? null;
            if (! $intel) {
                return [$assessment->assessment_id => null];
            }
            $findings = collect($intel['findings'] ?? []);

            return [$assessment->assessment_id => [
                'top_finding' => $findings->firstWhere('category', 'CRITICAL_FINDING') ?? $findings->firstWhere('category', 'WEAKNESS'),
                'top_risk' => collect($intel['risks'] ?? [])->first(),
            ]];
        });

        return view('reports.index', compact('assessments', 'previews', 'latestReport', 'search'));
    }

    /**
     * The headline of the most recent report — score, biggest issue, top risk and next
     * action — read straight from the frozen snapshot.
     *
     * @return array<string, mixed>|null
     */
    private function latestReportPreview(?Assessment $latest): ?array
    {
        if ($latest === null) {
            return null;
        }

        $payload = $latest->reportSnapshot?->payload ?? [];
        $intelligence = $payload['intelligence'] ?? [];
        $findings = collect($intelligence['findings'] ?? []);

        return [
            'assessment' => $latest,
            'title' => $payload['title'] ?? 'Assessment report',
            'score' => $payload['score']['overall_score'] ?? null,
            'top_finding' => $findings->firstWhere('category', 'CRITICAL_FINDING')
                ?? $findings->firstWhere('category', 'WEAKNESS'),
            'top_risk' => collect($intelligence['risks'] ?? [])->first(),
            'top_action' => collect($intelligence['recommendations'] ?? [])->first(),
        ];
    }
}
