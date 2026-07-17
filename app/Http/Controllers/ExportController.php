<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\PlatformSetting;
use App\Models\Project;
use App\Services\PlanService;
use App\Services\ReportSnapshotService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function assessmentPdf(Assessment $assessment, ReportSnapshotService $reports): Response
    {
        $this->authorizeAssessmentAccess($assessment);

        $workspace = app('current.workspace');
        $data = $this->assessmentReportData($assessment, $reports);
        $data['showWatermark'] = ! PlanService::workspaceCanAccess($workspace, 'pdf_export_no_watermark');

        $pdf = Pdf::loadView('exports.assessment-pdf', $data)
            ->setPaper('a4', 'portrait');

        $filename = 'assessment-report-'.substr($assessment->assessment_id, 0, 8).'.pdf';

        return $pdf->download($filename);
    }

    public function projectCsv(Project $project): StreamedResponse
    {
        $workspace = app('current.workspace');

        if ($project->workspace_id !== $workspace->workspace_id) {
            abort(404);
        }

        if (! PlanService::workspaceCanAccess($workspace, 'csv_export')) {
            abort(403, 'CSV export is not available on your current plan. Upgrade to export project data.');
        }

        $assessments = Assessment::where('project_id', $project->project_id)
            ->where('status', 'COMPLETE')
            ->with(['target', 'score.maturityLevel', 'moduleScope.module', 'reportSnapshot'])
            ->orderBy('completed_at')
            ->get();

        $filename = 'project-data-'.substr($project->project_id, 0, 8).'.csv';

        return response()->streamDownload(function () use ($assessments) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Assessment ID',
                'Target Name',
                'Modules',
                'Assessor',
                'Completed At',
                'Overall Score',
                'Calibration Status',
                'Maturity Level',
            ]);

            foreach ($assessments as $assessment) {
                fputcsv($handle, [
                    $assessment->assessment_id,
                    $assessment->target?->name ?? '',
                    collect($assessment->reportSnapshot?->payload['modules'] ?? [])
                        ->pluck('module_name')
                        ->whenEmpty(fn () => $assessment->moduleScope->where('in_scope', true)->pluck('module.module_name'))
                        ->filter()->join(' | '),
                    $assessment->assessor_name ?? '',
                    $assessment->completed_at?->format('Y-m-d H:i:s') ?? '',
                    $assessment->score?->overall_score !== null ? (float) $assessment->score->overall_score : '',
                    $assessment->score?->calibration_status ?? 'NOT_CALIBRATED',
                    $assessment->score?->maturityLevel?->level_name ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function createShareLink(Assessment $assessment): RedirectResponse
    {
        $this->authorizeAssessmentAccess($assessment);

        $workspace = app('current.workspace');
        if (! PlanService::workspaceCanAccess($workspace, 'shareable_report_links')) {
            return back()->with('error', 'Shareable report links are not available on your current plan. Upgrade to share assessment results.');
        }

        $expiryDays = (int) PlatformSetting::get('sharing.link_expiry_days', 30);

        $link = URL::temporarySignedRoute(
            'reports.shared',
            now()->addDays($expiryDays),
            ['assessment' => $assessment->assessment_id]
        );

        return back()->with('share_link', $link);
    }

    public function sharedReport(Assessment $assessment, ReportSnapshotService $reports): View
    {
        if ($assessment->status !== 'COMPLETE') {
            abort(404);
        }

        $data = $this->assessmentReportData($assessment, $reports);

        return view('exports.shared-report', $data);
    }

    private function assessmentReportData(Assessment $assessment, ReportSnapshotService $reports): array
    {
        $report = $reports->payloadFor($assessment);
        $subIndexScores = collect($report['sub_index_scores'])->map(fn ($row) => (object) $row);
        $domainScores = collect($report['domain_scores'])->map(fn ($row) => (object) $row);

        return compact('assessment', 'report', 'subIndexScores', 'domainScores');
    }

    private function authorizeAssessmentAccess(Assessment $assessment): void
    {
        if (! app()->bound('current.workspace')) {
            abort(403);
        }

        $workspace = app('current.workspace');
        $project = Project::withoutGlobalScopes()->find($assessment->project_id);

        if (! $project || $project->workspace_id !== $workspace->workspace_id) {
            abort(404);
        }
    }
}
