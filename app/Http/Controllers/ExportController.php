<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentShareLink;
use App\Models\PlatformSetting;
use App\Models\Project;
use App\Services\AuditService;
use App\Services\PlanService;
use App\Services\ReportDeliveryService;
use App\Services\Reporting\ReportDocumentExporter;
use App\Services\ReportSnapshotService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
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

    public function assessmentWord(Assessment $assessment, ReportSnapshotService $reports, ReportDocumentExporter $exporter): Response
    {
        return $this->officeExport($assessment, $reports, fn ($payload) => $exporter->word($payload),
            'docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    public function assessmentExcel(Assessment $assessment, ReportSnapshotService $reports, ReportDocumentExporter $exporter): Response
    {
        return $this->officeExport($assessment, $reports, fn ($payload) => $exporter->excel($payload),
            'xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function assessmentPpt(Assessment $assessment, ReportSnapshotService $reports, ReportDocumentExporter $exporter): Response
    {
        return $this->officeExport($assessment, $reports, fn ($payload) => $exporter->powerpoint($payload),
            'pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation');
    }

    /**
     * Shared office-export path: authorise, read the one frozen payload, render, download.
     */
    private function officeExport(Assessment $assessment, ReportSnapshotService $reports, callable $render, string $extension, string $mime): Response
    {
        $this->authorizeAssessmentAccess($assessment);

        if ($assessment->status !== Assessment::STATUS_COMPLETE) {
            abort(404);
        }

        $payload = $reports->payloadFor($assessment);
        $body = $render($payload);
        $filename = 'assessment-report-'.substr($assessment->assessment_id, 0, 8).'.'.$extension;

        return response($body, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function projectCsv(Project $project): StreamedResponse
    {
        $this->authorize('view', $project);
        $workspace = app('current.workspace');

        if (! PlanService::workspaceCanAccess($workspace, 'csv_export')) {
            abort(403, 'CSV export is not available on your current plan. Upgrade to export project data.');
        }

        $assessments = Assessment::where('project_id', $project->project_id)
            ->where('status', Assessment::STATUS_COMPLETE)
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

        if ($assessment->status !== Assessment::STATUS_COMPLETE) {
            return back()->with('error', 'Complete the assessment before sharing its final report.');
        }
        if (! $assessment->reportSnapshot()->exists()) {
            app(ReportSnapshotService::class)->createFor($assessment);
        }

        $expiryDays = (int) PlatformSetting::get('sharing.link_expiry_days', 30);

        $shareLink = AssessmentShareLink::create([
            'assessment_id' => $assessment->assessment_id,
            'token' => Str::random(64),
            'created_by' => auth()->id(),
            'created_at' => now(),
            'expires_at' => now()->addDays($expiryDays),
            'is_active' => true,
        ]);
        app(AuditService::class)->record('assessment.report_link.created', $assessment, newValues: [
            'link_id' => $shareLink->link_id,
            'expires_at' => $shareLink->expires_at?->toIso8601String(),
        ]);

        $link = route('reports.shared.token', $shareLink->token);

        return back()->with('share_link', $link);
    }

    /**
     * Email a read-only report link to a recipient.
     */
    public function emailReport(Request $request, Assessment $assessment, ReportDeliveryService $delivery): RedirectResponse
    {
        $this->authorizeAssessmentAccess($assessment);

        $workspace = app('current.workspace');
        if (! PlanService::workspaceCanAccess($workspace, 'shareable_report_links')) {
            return back()->with('error', 'Emailing reports is not available on your current plan. Upgrade to share assessment results.');
        }

        if ($assessment->status !== Assessment::STATUS_COMPLETE) {
            return back()->with('error', 'Complete the assessment before emailing its report.');
        }

        $validated = $request->validate([
            'recipient_email' => ['required', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $delivery->sendForAssessment(
            $assessment,
            $validated['recipient_email'],
            $validated['message'] ?? null,
            creatorId: (string) $request->user()->user_id,
        );

        return back()->with('success', 'Report emailed to '.$validated['recipient_email'].'.');
    }

    public function revokeShareLink(Assessment $assessment, AssessmentShareLink $shareLink): RedirectResponse
    {
        $this->authorizeAssessmentAccess($assessment);
        if ($shareLink->assessment_id !== $assessment->assessment_id) {
            abort(404);
        }

        $shareLink->update(['is_active' => false]);
        app(AuditService::class)->record('assessment.report_link.revoked', $assessment, newValues: [
            'link_id' => $shareLink->link_id,
        ]);

        return back()->with('success', 'The shared report link has been deactivated.');
    }

    public function sharedReport(Assessment $assessment, ReportSnapshotService $reports): View
    {
        if ($assessment->status !== Assessment::STATUS_COMPLETE) {
            abort(404);
        }

        $data = $this->assessmentReportData($assessment, $reports);

        return view('exports.shared-report', $data);
    }

    public function sharedReportByToken(string $token, ReportSnapshotService $reports): View
    {
        $shareLink = AssessmentShareLink::with('assessment')->where('token', $token)->first();
        if (! $shareLink?->isUsable() || $shareLink->assessment?->status !== Assessment::STATUS_COMPLETE) {
            abort(404);
        }

        $shareLink->increment('use_count');
        $shareLink->update(['last_used_at' => now()]);
        app(AuditService::class)->record(
            'assessment.report_link.viewed',
            $shareLink->assessment,
            newValues: ['link_id' => $shareLink->link_id],
            workspaceId: $shareLink->assessment->project()->withoutGlobalScopes()->value('workspace_id'),
            userId: null,
        );

        return view('exports.shared-report', $this->assessmentReportData($shareLink->assessment, $reports));
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
        $this->authorize('view', $assessment);
    }
}
