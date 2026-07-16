<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\PlatformSetting;
use App\Models\Project;
use App\Services\PlanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function assessmentPdf(Assessment $assessment): Response
    {
        $this->authorizeAssessmentAccess($assessment);

        $workspace = app('current.workspace');
        $data = $this->assessmentReportData($assessment);
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
            ->with(['target', 'score', 'moduleScope.module'])
            ->orderBy('completed_at')
            ->get();

        $filename = 'project-data-'.substr($project->project_id, 0, 8).'.csv';

        return response()->streamDownload(function () use ($assessments) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Assessment ID',
                'Target Name',
                'Module',
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
                    $assessment->moduleScope->first()?->module?->module_name ?? '',
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

    public function sharedReport(Assessment $assessment): View
    {
        if ($assessment->status !== 'COMPLETE') {
            abort(404);
        }

        $data = $this->assessmentReportData($assessment);

        return view('exports.shared-report', $data);
    }

    private function assessmentReportData(Assessment $assessment): array
    {
        $assessment->load([
            'project',
            'target',
            'moduleScope.module',
            'score.maturityLevel',
        ]);

        $subIndexScores = DB::table('sub_index_scores as sis')
            ->join('sub_indices as si', 'si.sub_index_id', '=', 'sis.sub_index_id')
            ->join('domains as d', 'd.domain_id', '=', 'si.domain_id')
            ->where('sis.assessment_id', $assessment->assessment_id)
            ->where('sis.respondent_type', 'STAFF')
            ->select('sis.*', 'si.acronym', 'si.full_name', 'si.description', 'd.domain_name', 'd.domain_code')
            ->orderBy('d.domain_code')
            ->orderBy('si.acronym')
            ->get();

        $domainScores = DB::table('domain_scores as ds')
            ->join('domains as d', 'd.domain_id', '=', 'ds.domain_id')
            ->where('ds.assessment_id', $assessment->assessment_id)
            ->select('ds.*', 'd.domain_name', 'd.domain_code')
            ->orderBy('d.domain_code')
            ->get();

        return compact('assessment', 'subIndexScores', 'domainScores');
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
