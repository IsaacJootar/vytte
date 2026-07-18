<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionVersion;
use App\Services\AuditService;
use App\Services\QuestionVersionPublishingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuestionVersionController extends Controller
{
    public function index(Request $request): View
    {
        $query = QuestionVersion::with(['question.module', 'questionType'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->lower().'%';
            $query->whereHas('question', fn ($inner) => $inner
                ->whereRaw('LOWER(question_code) LIKE ?', [$search])
                ->orWhereRaw('LOWER(question_text) LIKE ?', [$search]));
        }

        return view('admin.question-versions.index', [
            'versions' => $query->paginate(30)->withQueryString(),
            'statuses' => [
                QuestionVersion::STATUS_DRAFT,
                QuestionVersion::STATUS_INTERNAL_REVIEW,
                QuestionVersion::STATUS_APPROVED,
                QuestionVersion::STATUS_PUBLISHED,
                QuestionVersion::STATUS_SUPERSEDED,
                QuestionVersion::STATUS_ARCHIVED,
            ],
        ]);
    }

    public function show(QuestionVersion $version): View
    {
        $version->load(['question.module', 'question.questionGroup', 'questionType']);

        return view('admin.question-versions.show', compact('version'));
    }

    public function markApproved(QuestionVersion $version, AuditService $audit): RedirectResponse
    {
        if (! in_array($version->status, [QuestionVersion::STATUS_DRAFT, QuestionVersion::STATUS_INTERNAL_REVIEW], true)) {
            return back()->withErrors(['status' => 'Only draft or internal-review question versions can be approved.']);
        }

        $old = ['status' => $version->status];
        $version->update([
            'status' => QuestionVersion::STATUS_APPROVED,
            'approved_by' => auth()->id(),
            'review_notes' => $version->review_notes ?: 'Approved by Vytte Platform Admin.',
        ]);
        $audit->record('question.version.approved', $version, $old, ['status' => QuestionVersion::STATUS_APPROVED]);

        return back()->with('success', 'Question version approved.');
    }

    public function publish(QuestionVersion $version, QuestionVersionPublishingService $publisher): RedirectResponse
    {
        try {
            $publisher->publish($version, auth()->id());
        } catch (\Throwable $exception) {
            return back()->withErrors(['publication' => $exception->getMessage()]);
        }

        return back()->with('success', 'Question version published and frozen.');
    }
}
