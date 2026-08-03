<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentModule;
use App\Models\ContentContribution;
use App\Models\ContentPublisher;
use App\Scopes\WorkspaceScope;
use App\Services\AuditService;
use App\Services\ContentContributionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContentContributionController extends Controller
{
    public function index(Request $request): View
    {
        $query = ContentContribution::withoutGlobalScope(WorkspaceScope::class)
            ->with(['workspace', 'submitter', 'module'])
            ->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('admin.contributions.index', [
            'contributions' => $query->paginate(25)->withQueryString(),
            'statuses' => ContentContribution::STATUSES,
        ]);
    }

    public function show(string $contribution): View
    {
        $item = ContentContribution::withoutGlobalScope(WorkspaceScope::class)
            ->with(['workspace', 'submitter', 'module', 'proposedPublisher'])
            ->findOrFail($contribution);

        return view('admin.contributions.show', [
            'contribution' => $item,
            'departments' => AssessmentModule::where('is_active', true)->orderBy('module_name')->get(),
            'publishers' => ContentPublisher::where('verification_status', '!=', ContentPublisher::STATUS_SUSPENDED)->orderBy('name')->get(),
        ]);
    }

    public function review(Request $request, string $contribution, AuditService $audit): RedirectResponse
    {
        $item = ContentContribution::withoutGlobalScope(WorkspaceScope::class)->findOrFail($contribution);
        if ($item->status === 'PROMOTED') {
            return back()->withErrors(['status' => 'A promoted contribution is an immutable audit record.']);
        }
        $validated = $request->validate([
            'status' => ['required', Rule::in(['IN_REVIEW', 'NEEDS_CHANGES', 'ACCEPTED', 'REJECTED'])],
            'review_notes' => ['required', 'string', 'max:5000'],
        ]);
        $old = $item->only(['status', 'review_notes']);
        $item->update([
            ...$validated,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        $audit->record('content.contribution.reviewed', $item, $old, $validated, $item->workspace_id);

        return back()->with('success', 'Review decision saved.');
    }

    public function promote(Request $request, string $contribution, ContentContributionService $service, AuditService $audit): RedirectResponse
    {
        $item = ContentContribution::withoutGlobalScope(WorkspaceScope::class)->findOrFail($contribution);
        $validated = $request->validate([
            'module_id' => ['required', 'integer', Rule::exists('assessment_modules', 'module_id')->where('is_active', true)],
            'content_publisher_id' => ['required', 'uuid', Rule::exists('content_publishers', 'content_publisher_id')->whereNot('verification_status', ContentPublisher::STATUS_SUSPENDED)],
        ]);
        try {
            $version = $service->promote(
                $item,
                ContentPublisher::findOrFail($validated['content_publisher_id']),
                (int) $validated['module_id'],
                auth()->id(),
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }
        $audit->record('content.contribution.promoted', $item->fresh(), newValues: [
            'question_version_id' => $version->question_version_id,
            'status' => 'PROMOTED',
        ], workspaceId: $item->workspace_id);

        return redirect()->route('admin.question-versions.show', $version)->with('success', 'Contribution promoted to a draft question version. It still requires the ordinary review and publication process.');
    }
}
