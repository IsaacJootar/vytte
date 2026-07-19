<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\AuditLog;
use App\Models\DepartmentFrameworkVersion;
use App\Models\PlatformSetting;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\SubIndex;
use App\Services\AssessmentReadinessService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Platform Admin landing page.
 *
 * It answers what needs attention, what is live, and whether the platform is healthy. It
 * deliberately does not present raw entity counts: knowing there are five framework
 * versions tells an administrator what is in the database, not what to do next.
 */
class DashboardController extends Controller
{
    public function index(AssessmentReadinessService $readiness): View
    {
        $drafts = DepartmentFrameworkVersion::where('status', DepartmentFrameworkVersion::STATUS_DRAFT)
            ->with(['module', 'sections.questionPlacements.questionVersion.questionType'])
            ->orderByDesc('updated_at')
            ->get();

        return view('admin.dashboard', [
            'attention' => $this->attentionItems($drafts, $readiness),
            'recentDrafts' => $drafts->take(5),
            'catalogue' => [
                'live' => AssessmentCatalogueRelease::where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)->count(),
                'superseded' => AssessmentCatalogueRelease::where('status', AssessmentCatalogueRelease::STATUS_SUPERSEDED)->count(),
                'questions' => Question::count(),
                'departments' => AssessmentModule::where('is_active', true)->count(),
            ],
            'health' => $this->health(),
            'recentActivity' => AuditLog::with('user')->latest()->take(6)->get(),
        ]);
    }

    /**
     * Work that is waiting on the administrator. An empty list is a good outcome and the
     * view says so, rather than showing an empty container.
     *
     * @param  Collection<int, DepartmentFrameworkVersion>  $drafts
     * @return list<array{tone: string, title: string, detail: string, action: string, href: string}>
     */
    private function attentionItems(Collection $drafts, AssessmentReadinessService $readiness): array
    {
        $items = [];

        $awaitingApproval = QuestionVersion::whereIn('status', [
            QuestionVersion::STATUS_DRAFT,
            QuestionVersion::STATUS_INTERNAL_REVIEW,
            QuestionVersion::STATUS_APPROVED,
        ])->whereIn('question_version_id', function ($query) {
            $query->select('question_version_id')->from('framework_question_placements');
        })->count();

        if ($awaitingApproval > 0) {
            $items[] = [
                'tone' => 'warning',
                'title' => $awaitingApproval.' '.str('question')->plural($awaitingApproval).' waiting for approval',
                'detail' => 'These must be approved before the assessments using them can be published.',
                'action' => 'Review',
                'href' => route('admin.assessments.index', ['status' => DepartmentFrameworkVersion::STATUS_DRAFT]),
            ];
        }

        $ready = $drafts->filter(fn ($draft) => $readiness->evaluate($draft)['ready']);
        if ($ready->isNotEmpty()) {
            $items[] = [
                'tone' => 'success',
                'title' => $ready->count().' '.str('assessment')->plural($ready->count()).' ready to publish',
                'detail' => $ready->take(3)->pluck('display_name')->join(', '),
                'action' => 'Publish',
                'href' => route('admin.assessments.review', $ready->first()),
            ];
        }

        $stalled = $drafts->filter(fn ($draft) => $draft->updated_at?->lt(now()->subDays(14)));
        if ($stalled->isNotEmpty()) {
            $items[] = [
                'tone' => 'neutral',
                'title' => $stalled->count().' '.str('draft')->plural($stalled->count()).' not touched in two weeks',
                'detail' => $stalled->take(3)->pluck('display_name')->join(', '),
                'action' => 'Continue',
                'href' => route('admin.assessments.index', ['status' => DepartmentFrameworkVersion::STATUS_DRAFT]),
            ];
        }

        $departmentsWithoutScore = AssessmentModule::where('is_active', true)
            ->whereNotIn('module_id', SubIndex::query()->select('module_id'))
            ->count();

        if ($departmentsWithoutScore > 0) {
            $items[] = [
                'tone' => 'warning',
                'title' => $departmentsWithoutScore.' '.str('department')->plural($departmentsWithoutScore).' cannot score questions yet',
                'detail' => 'A department needs a score before its questions can affect results.',
                'action' => 'Set up',
                'href' => route('admin.scoring-policies.index'),
            ];
        }

        return $items;
    }

    /**
     * Plain statements about platform condition rather than raw numbers.
     *
     * @return list<array{ok: bool, label: string, detail: string}>
     */
    private function health(): array
    {
        $failedJobs = DB::table('failed_jobs')->count();
        $emailEnabled = (bool) PlatformSetting::get('email.notifications_enabled', false);

        return [
            [
                'ok' => $failedJobs === 0,
                'label' => 'Background jobs',
                'detail' => $failedJobs === 0 ? 'No failures' : $failedJobs.' failed',
            ],
            [
                'ok' => $emailEnabled,
                'label' => 'Email delivery',
                'detail' => $emailEnabled ? 'Enabled' : 'Turned off',
            ],
            [
                'ok' => ! app()->environment('production') || ! config('app.debug'),
                'label' => 'Environment',
                'detail' => config('app.debug') ? 'Debug mode on' : 'Debug mode off',
            ],
        ];
    }
}
