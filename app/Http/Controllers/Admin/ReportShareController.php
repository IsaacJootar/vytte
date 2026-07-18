<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentShareLink;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportShareController extends Controller
{
    public function index(Request $request): View
    {
        $query = AssessmentShareLink::with(['assessment.project.workspace', 'assessment.target'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'revoked') {
                $query->where('is_active', false);
            } elseif ($status === 'expired') {
                $query->where('expires_at', '<', now());
            }
        }

        return view('admin.report-shares.index', [
            'shareLinks' => $query->paginate(30)->withQueryString(),
        ]);
    }

    public function revoke(AssessmentShareLink $shareLink, AuditService $audit): RedirectResponse
    {
        if (! $shareLink->is_active) {
            return back()->with('success', 'Shared report link is already revoked.');
        }

        $shareLink->update(['is_active' => false]);
        $audit->record('platform.report_link.revoked', $shareLink->assessment, newValues: [
            'link_id' => $shareLink->link_id,
            'revoked_by_platform_admin' => auth()->id(),
        ], workspaceId: $shareLink->assessment?->project()->withoutGlobalScopes()->value('workspace_id'));

        return back()->with('success', 'Shared report link revoked.');
    }
}
