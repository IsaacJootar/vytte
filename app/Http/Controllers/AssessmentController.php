<?php

namespace App\Http\Controllers;

use App\Actions\CompleteSelfAssessment;
use App\Models\Assessment;
use App\Models\AssessmentAiNarrative;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentShareLink;
use App\Models\FacilityProfile;
use App\Models\Project;
use App\Models\Response;
use App\Services\Ai\AiNarrativeService;
use App\Services\Ai\AiProductCatalog;
use App\Services\AssessmentCreationService;
use App\Services\AuditService;
use App\Services\PlanService;
use App\Services\Reporting\CustomSectionScoringService;
use App\Services\Reporting\LensCatalog;
use App\Services\Reporting\ReportComposer;
use App\Services\ReportSnapshotService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentController extends Controller
{
    public function index(Request $request): View
    {
        $workspaceProjectIds = Project::select('project_id');

        $search = trim((string) $request->query('search', ''));

        $assessments = Assessment::whereIn('project_id', $workspaceProjectIds)
            ->when($search !== '', function ($query) use ($search) {
                // What a person remembers: the target or the project it belongs to.
                $like = '%'.strtolower($search).'%';
                $query->where(function ($inner) use ($like) {
                    $inner->whereHas('target', fn ($t) => $t->whereRaw('LOWER(name) LIKE ?', [$like]))
                        ->orWhereHas('project', fn ($p) => $p->whereRaw('LOWER(name) LIKE ?', [$like]));
                });
            })
            ->with(['project', 'target', 'score', 'moduleScope.module', 'snapshot', 'catalogueRelease'])
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('assessments.index', compact('assessments', 'search'));
    }

    public function create(Project $project): View
    {
        $this->authorize('view', $project);
        $target = $project->targets->first();
        $settingTypeCode = $target
            ? DB::table('target_type_setting_map')->where('target_type_code', $target->target_type_code)->value('setting_type_code')
            : null;
        $usesDepartments = $target?->uses_departments ?? (bool) DB::table('setting_types')
            ->where('setting_type_code', $settingTypeCode)
            ->value('uses_departments');

        $facilityProfiles = FacilityProfile::where('status', FacilityProfile::STATUS_PUBLISHED)
            ->where('setting_type_code', $settingTypeCode)
            ->orderBy('display_order')
            ->get();
        // A comprehensive assessment is offered for the whole setting type, not gated to the
        // target's one specific facility profile. A target on any health-facility profile can
        // therefore run any published comprehensive release for health facilities, rather than
        // seeing "no release available" because its profile happens to differ from the one the
        // release was composed against.
        $profileIds = $facilityProfiles->pluck('facility_profile_id');

        $comprehensiveReleases = AssessmentCatalogueRelease::where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)
            ->where('creation_path', 'COMPREHENSIVE')
            ->whereIn('facility_profile_id', $profileIds)
            ->with(['facilityProfile', 'departmentFrameworkVersions.module'])
            ->orderBy('release_name')
            ->get();

        // Focused assessments are health *topics* (malaria, TB, WASH, nutrition), not
        // facility diagnostics. A topic is not tied to a kind of place, so every target —
        // a hospital, an NGO programme, a research study, a community — may run any focused
        // assessment relevant to its work. They are therefore offered to all target types.
        $focusedReleases = AssessmentCatalogueRelease::where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)
            ->where('creation_path', 'FOCUSED')
            ->with(['healthDomain', 'departmentFrameworkVersions.module'])
            ->orderBy('release_name')
            ->get();

        // Comprehensive is a whole-facility diagnostic — it only applies to health facilities.
        $isHealthFacility = $settingTypeCode === 'HEALTH_FACILITY';

        // The plain name of what is being assessed, used in copy instead of "facility profile".
        $settingLabel = $target?->targetType?->target_type_name
            ?? DB::table('setting_types')->where('setting_type_code', $settingTypeCode)->value('display_name')
            ?? 'target';

        return view('assessments.create', compact('project', 'target', 'usesDepartments', 'facilityProfiles', 'comprehensiveReleases', 'focusedReleases', 'settingLabel', 'isHealthFacility'));
    }

    public function store(Request $request, Project $project, AssessmentCreationService $creator): RedirectResponse
    {
        $this->authorize('update', $project);
        $workspace = app('current.workspace');

        if (PlanService::hasReachedAssessmentLimit($workspace, $project)) {
            return redirect()->route('billing.index')
                ->with('limit_error', 'You have reached the assessment limit for this project on your current plan. Upgrade to run more assessments.');
        }

        $validated = $request->validate([
            'creation_path' => ['required', 'in:COMPREHENSIVE,FOCUSED'],
            'catalogue_release_id' => ['required', 'uuid', 'exists:assessment_catalogue_releases,catalogue_release_id'],
            'departments' => ['nullable', 'array'],
            'departments.*' => ['integer'],
            'exclusion_reasons' => ['nullable', 'array'],
            'exclusion_reasons.*' => ['nullable', 'string', 'max:500'],
        ]);

        $release = AssessmentCatalogueRelease::findOrFail($validated['catalogue_release_id']);
        if ($release->creation_path !== $validated['creation_path']) {
            return back()->withErrors(['catalogue_release_id' => 'The selected catalogue release does not match this assessment path.'])->withInput();
        }

        $assessment = $creator->createFromCatalogue(
            $project,
            $release,
            $validated['departments'] ?? [],
            $validated['exclusion_reasons'] ?? [],
            auth()->id(),
        );

        // Land on the setup wizard rather than dropping the user straight into the questions —
        // so they review what they created, optionally add their own questions, then choose how
        // answers are collected.
        return redirect()->route('assessments.setup', $assessment);
    }

    /**
     * Kept for older links: the standalone decision screen is now the first step of the setup
     * wizard, so send anything pointing here into the wizard.
     */
    public function start(Assessment $assessment): RedirectResponse
    {
        return redirect()->route('assessments.setup', $assessment);
    }

    /**
     * The step-by-step setup wizard for a draft assessment: review → add your own questions →
     * choose how it is answered → finish. A completed assessment goes to its report; a published
     * (shared) one is managed from its collection page.
     */
    public function setup(Request $request, Assessment $assessment): View|RedirectResponse
    {
        $this->authorizeWorkspace($assessment);

        if ($assessment->status === Assessment::STATUS_COMPLETE) {
            return redirect()->route('assessments.results', $assessment);
        }
        if ($assessment->isPublished()) {
            return redirect()->route('assessments.respondent-collection', $assessment);
        }

        $assessment->load(['project', 'target', 'moduleScope.module', 'snapshot', 'catalogueRelease', 'localCustomSections']);

        $step = max(1, min(4, (int) $request->query('step', 1)));
        $allowsMultiRespondent = $assessment->snapshot?->collection_config['allows_multi_respondent'] ?? false;
        $mode = ($request->query('mode') === 'share' && $allowsMultiRespondent) ? 'share' : 'self';

        $areas = $assessment->moduleScope->where('in_scope', true)
            ->map(fn ($m) => $m->module?->module_name)->filter()->values();
        $questionCount = collect($assessment->snapshot?->payload ?? [])->sum(fn ($m) => count($m['questions'] ?? []));
        $section = $assessment->localCustomSections->first();
        $customCount = $section && is_array($section->questions) ? count($section->questions) : 0;
        $hasResponses = $assessment->responses()->exists();
        $subject = $assessment->catalogueRelease?->release_name
            ?? ($areas->count() === 1 ? $areas->first() : 'Health Assessment');

        return view('assessments.setup', compact(
            'assessment', 'step', 'mode', 'allowsMultiRespondent', 'areas',
            'questionCount', 'section', 'customCount', 'hasResponses', 'subject'
        ));
    }

    public function run(Assessment $assessment): View
    {
        $this->authorizeWorkspace($assessment);

        if ($assessment->snapshot?->collection_config['allows_multi_respondent'] ?? false) {
            return redirect()->route('assessments.respondent-collection', $assessment)
                ->with('error', 'Multi-respondent assessments must be finalized from the respondent collection review.');
        }

        $assessment->load(['project', 'target', 'moduleScope.module']);

        return view('assessments.run', compact('assessment'));
    }

    public function submit(Assessment $assessment): RedirectResponse
    {
        $this->authorizeWorkspace($assessment);

        if ($assessment->status === Assessment::STATUS_COMPLETE) {
            return redirect()->route('projects.show', $assessment->project_id)
                ->with('success', 'Assessment already submitted.');
        }

        $snapshot = $assessment->snapshot()->first();
        if ($snapshot?->collection_config['allows_multi_respondent'] ?? false) {
            return redirect()->route('assessments.respondent-collection', $assessment)
                ->with('error', 'Multi-respondent collections require authorized manual finalization.');
        }

        if (! $snapshot) {
            return redirect()->route('projects.show', $assessment->project_id)
                ->with('error', 'This assessment has no governed content snapshot and cannot be submitted.');
        }

        $requiredQuestions = collect($snapshot->payload)
            ->flatMap(fn ($module) => $module['questions'] ?? [])
            ->where('is_scored', true)
            ->values();
        $responses = Response::where('assessment_id', $assessment->assessment_id)
            ->whereNull('respondent_id')
            ->whereNull('public_response_session_id')
            ->whereIn('question_id', $requiredQuestions->pluck('question_id'))
            ->get()->keyBy('question_id');
        $hasMissingResponse = $requiredQuestions->contains(function ($question) use ($responses) {
            $response = $responses->get($question['question_id']);
            if (! $response) {
                return true;
            }

            return match ($question['response_type']) {
                'OPEN_ENDED' => blank($response->value_text),
                'NUMERIC' => $response->value_numeric === null,
                default => ! collect($question['options'] ?? [])
                    ->contains('option_id', (int) $response->value_option_id),
            };
        });

        if ($hasMissingResponse) {
            return redirect()->route('assessments.run', $assessment)
                ->with('error', 'Please answer every required scored question before submitting.');
        }

        // The official questions are done. If a tailored section is attached and not yet
        // answered, that is the last step of the same flow before the assessment is finished.
        $tailored = $assessment->localCustomSections()->first();
        if ($tailored && ! empty($tailored->questions) && $tailored->scored_at === null) {
            return redirect()->route('assessments.custom.answer', $assessment);
        }

        app(CompleteSelfAssessment::class)->handle($assessment);

        return redirect()->route('projects.show', $assessment->project_id)
            ->with('success', 'Assessment submitted.');
    }

    public function results(Assessment $assessment, ReportSnapshotService $reports): View|RedirectResponse
    {
        $this->authorizeWorkspace($assessment);

        if ($assessment->status !== Assessment::STATUS_COMPLETE) {
            return redirect()->route('assessments.run', $assessment);
        }

        $assessment->load([
            'project',
            'target.targetType',
            'moduleScope.module',
            'score.maturityLevel',
        ]);

        $report = $reports->payloadFor($assessment);
        $assessmentTitle = $report['title'];
        $subIndexScores = collect($report['sub_index_scores'])->map(fn ($row) => (object) $row);
        $domainScores = collect($report['domain_scores'])->map(fn ($row) => (object) $row);

        // The deterministic intelligence is frozen into the snapshot payload. Snapshots
        // taken before the reporting engine existed have no 'intelligence' key, so it is
        // recomputed on the fly — the engine is pure, so the result is identical.
        $composer = app(ReportComposer::class);
        $intelligence = $report['intelligence'] ?? $composer->intelligence($report);
        $lens = request()->query('lens', LensCatalog::DEFAULT);
        $lensView = $composer->throughLens($intelligence, is_string($lens) ? $lens : LensCatalog::DEFAULT);
        $lensOptions = ReportComposer::lenses();

        // Optional AI products — offered only if the integration is configured; any already
        // generated are shown. The report never depends on them.
        $aiAvailable = app(AiNarrativeService::class)->isAvailable();
        $aiProducts = AiProductCatalog::options();
        $narratives = AssessmentAiNarrative::where('assessment_id', $assessment->assessment_id)
            ->get()->keyBy('product');

        if ($assessment->score) {
            $assessment->score->overall_score = $report['score']['overall_score'];
            $assessment->score->calibration_status = $report['score']['calibration_status'];
            $assessment->score->scoring_version = $report['score']['scoring_version'];
            if ($assessment->score->maturityLevel && $report['score']['maturity_level']) {
                $assessment->score->maturityLevel->level_name = $report['score']['maturity_level']['name'];
                $assessment->score->maturityLevel->level_number = $report['score']['maturity_level']['number'];
            }
        }

        // History is comparable only when the exact governed composition matches.
        $history = Assessment::where('project_id', $assessment->project_id)
            ->where('status', Assessment::STATUS_COMPLETE)
            ->when(
                $assessment->composition_hash,
                fn ($query, $hash) => $query->where('composition_hash', $hash),
                fn ($query) => $query->where('assessment_id', $assessment->assessment_id)
            )
            ->with(['score.maturityLevel', 'reportSnapshot'])
            ->orderBy('completed_at')
            ->get();
        foreach ($history as $historicalAssessment) {
            $historicalScore = $historicalAssessment->reportSnapshot?->payload['score'] ?? null;
            if ($historicalScore && $historicalAssessment->score) {
                $historicalAssessment->score->overall_score = $historicalScore['overall_score'];
                $historicalAssessment->score->calibration_status = $historicalScore['calibration_status'];
            }
        }

        // Share links were flashed once at creation and never shown again, so a link the
        // user did not copy in that moment was gone. They are listed now.
        $shareLinks = AssessmentShareLink::where('assessment_id', $assessment->assessment_id)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->get();

        // The workspace's own tailored section — scored in its own lane, never the official score.
        // A shared-link assessment is answered by many respondents, so the section is aggregated
        // across them; a self-assessment has a single answer set.
        $customSection = $assessment->localCustomSections()->first();
        $customScorer = app(CustomSectionScoringService::class);
        $customScored = null;
        if ($customSection) {
            $respondentAnswers = $customSection->respondent_answers ?? [];
            $customScored = ! empty($respondentAnswers)
                ? $customScorer->aggregate($customSection->questions ?? [], array_values($respondentAnswers))
                : $customScorer->score($customSection->questions ?? [], $customSection->answers ?? []);
        }

        return view('assessments.results', compact('assessment', 'assessmentTitle', 'subIndexScores', 'domainScores', 'history', 'shareLinks', 'intelligence', 'lensView', 'lensOptions', 'aiAvailable', 'aiProducts', 'narratives', 'customSection', 'customScored'));
    }

    /**
     * Generate (or regenerate) one AI product for an assessment.
     *
     * A product is a retelling of the already-frozen intelligence for a particular audience;
     * it adds no new facts. A failure — no key, API down — degrades to a plain message and
     * never breaks the report.
     */
    public function generateNarrative(Request $request, Assessment $assessment, ReportSnapshotService $reports, AiNarrativeService $narrator): RedirectResponse
    {
        $this->authorize('update', $assessment);

        $product = $request->input('product', 'EXECUTIVE_BRIEFING');
        if (! is_string($product) || ! AiProductCatalog::exists($product)) {
            $product = 'EXECUTIVE_BRIEFING';
        }

        if ($assessment->status !== Assessment::STATUS_COMPLETE) {
            return back()->with('error', 'Complete the assessment before generating a summary.');
        }

        if (! $narrator->isAvailable()) {
            return back()->with('error', 'AI summaries are not available yet. They need the OpenAI API key to be configured.');
        }

        try {
            $result = $narrator->generate($reports->payloadFor($assessment), $product);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The AI summary could not be generated right now. Please try again shortly.');
        }

        AssessmentAiNarrative::updateOrCreate(
            ['assessment_id' => $assessment->assessment_id, 'product' => $result['product']],
            [
                'model' => $result['model'],
                'source_hash' => $result['source_hash'],
                'body' => $result['body'],
                'generated_by' => $request->user()->user_id,
                'created_at' => now(),
            ],
        );

        app(AuditService::class)->record('assessment.ai_narrative.generated', $assessment, newValues: [
            'product' => $result['product'],
            'model' => $result['model'],
        ]);

        // The freshly generated product is flagged so the results page opens only that one
        // and leaves the others collapsed.
        return back()
            ->with('success', AiProductCatalog::get($result['product'])['name'].' ready.')
            ->with('generated_product', $result['product']);
    }

    /**
     * Download one AI summary as a PDF. Text only, so this renders reliably.
     */
    public function narrativePdf(Assessment $assessment, string $product): \Illuminate\Http\Response|RedirectResponse
    {
        $this->authorizeWorkspace($assessment);

        $narrative = AssessmentAiNarrative::where('assessment_id', $assessment->assessment_id)
            ->where('product', $product)
            ->first();

        if ($narrative === null || ! AiProductCatalog::exists($product)) {
            return redirect()->route('assessments.results', $assessment);
        }

        $meta = AiProductCatalog::get($product);
        $pdf = Pdf::loadView('exports.narrative-pdf', [
            'title' => $meta['name'],
            'subtitle' => $assessment->target?->name,
            'body' => $narrative->body,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('summary-'.strtolower(str_replace('_', '-', $product)).'.pdf');
    }

    /**
     * Publish opens the assessment for responses. Until this is done an assessment is a
     * draft: it cannot generate respondent links and the public runner will not accept
     * answers. Publishing is the deliberate act that turns a set-up assessment into a
     * live data-collection activity.
     */
    public function publish(Assessment $assessment, AuditService $audit): RedirectResponse
    {
        $this->authorizeWorkspace($assessment);

        if (! $assessment->isDraft()) {
            return back()->with('info', 'This assessment is already published.');
        }

        if (! $assessment->snapshot()->exists()) {
            return back()->with('error', 'This assessment has no governed content and cannot be published.');
        }

        $assessment->markPublished(auth()->id());
        $audit->record('assessment.published', $assessment, ['publish_status' => Assessment::PUBLISH_DRAFT], [
            'publish_status' => Assessment::PUBLISH_PUBLISHED,
        ]);

        return back()->with('success', 'Assessment published. You can now share it and collect responses.');
    }

    /**
     * Close ends the collection window. No new responses are accepted after this; existing
     * ones are kept for finalisation. Reversible until the assessment is finalised.
     */
    public function close(Assessment $assessment, AuditService $audit): RedirectResponse
    {
        $this->authorizeWorkspace($assessment);

        if (! $assessment->isPublished() || $assessment->isComplete()) {
            return back()->with('error', 'Only a live assessment can be closed to responses.');
        }

        $assessment->markClosed();
        $audit->record('assessment.closed', $assessment, newValues: ['closed_at' => $assessment->closed_at?->toIso8601String()]);

        return back()->with('success', 'Collection closed. No new responses will be accepted.');
    }

    public function reopen(Assessment $assessment, AuditService $audit): RedirectResponse
    {
        $this->authorizeWorkspace($assessment);

        if (! $assessment->isClosed() || $assessment->isComplete()) {
            return back()->with('error', 'This assessment is not closed.');
        }

        $assessment->reopen();
        $audit->record('assessment.reopened', $assessment, newValues: ['closed_at' => null]);

        return back()->with('success', 'Collection reopened. The assessment is accepting responses again.');
    }

    /**
     * Live monitoring of an assessment's responses: how many have come in, how far each has
     * got, and how many are complete. A read-only view over the response sessions, so an
     * organisation can watch collection without touching the data.
     */
    public function monitor(Assessment $assessment): View
    {
        $this->authorizeWorkspace($assessment);

        $assessment->load(['project', 'target', 'snapshot']);

        $sessions = $assessment->publicResponseSessions()
            ->orderByDesc('last_activity_at')
            ->get();

        $submitted = $sessions->whereNotNull('submitted_at');
        $eligible = $submitted->where('eligibility_status', 'ELIGIBLE');

        $minimum = (int) ($assessment->snapshot?->collection_config['minimum_completed_respondents'] ?? 1);

        return view('assessments.monitor', [
            'assessment' => $assessment,
            'sessions' => $sessions,
            'stats' => [
                'started' => $sessions->count(),
                'in_progress' => $sessions->whereNull('submitted_at')->count(),
                'submitted' => $submitted->count(),
                'eligible' => $eligible->count(),
                'excluded' => $submitted->where('eligibility_status', 'EXCLUDED')->count(),
                'minimum' => $minimum,
                'completion_rate' => $sessions->count() > 0
                    ? (int) round($submitted->count() / $sessions->count() * 100)
                    : 0,
            ],
        ]);
    }

    /**
     * Tag where this run sits in the series (baseline / midline / endline / follow-up), so
     * the trend can tell a story rather than just compare adjacent runs.
     */
    public function setType(Request $request, Assessment $assessment): RedirectResponse
    {
        $this->authorize('update', $assessment);

        $validated = $request->validate([
            'assessment_type' => ['nullable', 'in:'.implode(',', Assessment::TYPES)],
        ]);

        $assessment->update(['assessment_type' => $validated['assessment_type'] ?: null]);

        return back()->with('success', 'Assessment type updated.');
    }

    private function authorizeWorkspace(Assessment $assessment): void
    {
        $this->authorize('view', $assessment);
    }
}
