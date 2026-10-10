<?php

namespace Tests\Feature;

use App\Actions\CompleteSelfAssessment;
use App\Livewire\PublicRespondentRunner;
use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\AssessmentRespondentToken;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FrameworkQuestionPlacement;
use App\Models\Project;
use App\Models\PublicResponseSession;
use App\Models\Response;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AssessmentCreationService;
use App\Services\MultiRespondentAggregationService;
use App\Services\Reporting\ProgrammeIndicatorService;
use App\Services\Reporting\ProgrammeOverviewService;
use App\Services\Reporting\ProgrammeReportService;
use App\Services\RespondentSubmissionService;
use App\Support\ReportType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\RestoresCatalogueSnapshot;
use Tests\TestCase;

/**
 * The menstrual health & Dignity Bank programme: the School and PHC assessments, the girls' and
 * women's survey, and the programme report built on top of them.
 */
class MenstrualHealthDignityProgrammeTest extends TestCase
{
    use RefreshDatabase;
    use RestoresCatalogueSnapshot;

    public function test_the_three_templates_are_published_with_the_expected_questions(): void
    {
        $this->seedOfficialCatalogue();

        // [module, placed, scored]
        foreach ([['MHDS', 37, 26], ['MHDP', 38, 28], ['MHDG', 21, 17]] as [$module, $placed, $scored]) {
            $framework = DepartmentFrameworkVersion::where('module_id', AssessmentModule::where('module_code', $module)->value('module_id'))
                ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
                ->firstOrFail();
            $placements = FrameworkQuestionPlacement::where('framework_version_id', $framework->framework_version_id)->get();

            $this->assertCount($placed, $placements, "{$module} placements");
            $this->assertCount($scored, $placements->where('scoring_contribution', true), "{$module} scored");
        }
    }

    public function test_each_template_declares_its_report_type_and_other_templates_stay_standard(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();

        $school = $this->site($user, $workspace, 'VYTTE_MHD_SCHOOL_V1', 'best');
        $survey = $this->createAssessment($user, $workspace, 'VYTTE_MHD_GIRLS_V1');
        $dhr = $this->createAssessment($user, $workspace, 'VYTTE_DHR_V1');

        $this->assertSame(ReportType::PROGRAMME, ReportType::of($school));
        $this->assertFalse(ReportType::isSurvey($school));
        $this->assertTrue(ReportType::isSurvey($survey));
        $this->assertSame(ReportType::STANDARD, ReportType::of($dhr));
    }

    public function test_a_strong_ready_school_is_labelled_later_with_no_must_haves_missing(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();

        $report = app(ProgrammeReportService::class)->forAssessment($this->site($user, $workspace, 'VYTTE_MHD_SCHOOL_V1', 'best'));

        $this->assertSame('LOWER_NEED_READY', $report['classification']['key']);
        $this->assertSame('Later', $report['classification']['label']);
        $this->assertSame([], $report['flags']);
        $this->assertSame(100.0, (float) $report['provision']['score']);
    }

    public function test_the_weakest_answers_name_every_missing_must_have(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();

        $report = app(ProgrammeReportService::class)->forAssessment($this->site($user, $workspace, 'VYTTE_MHD_PHC_V1', 'worst'));

        $this->assertSame('HIGH_NEED_NOT_READY', $report['classification']['key']);
        $this->assertSame('Prepare first', $report['classification']['label']);
        $this->assertCount(4, $report['flags']);
        $this->assertContains('There is no secure place to store menstrual products.', $report['flags']);
    }

    public function test_the_site_report_page_and_pdf_render_for_a_programme_assessment(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->site($user, $workspace, 'VYTTE_MHD_SCHOOL_V1', 'best');

        $this->actingAs($user)->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('Programme overview')
            ->assertSee('Later');

        $this->actingAs($user)->get(route('assessments.export.pdf', $assessment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_survey_indicators_count_only_real_answers_and_compare_with_the_earlier_survey(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $project = $this->project($user, $workspace, 'Bwari school');

        // Baseline: ten girls, none had enough materials. Endline: ten girls, all did.
        $baseline = $this->survey($project, $user, 10, best: false);
        $endline = $this->survey($project, $user, 10, best: true);

        $results = app(ProgrammeIndicatorService::class)->withComparison($endline);
        $byCode = collect($results['indicators'])->keyBy('code');

        $this->assertSame(10, $results['respondents']);
        $this->assertTrue($results['enough']);
        $this->assertSame($baseline->assessment_id, $results['previous']['assessment_id']);

        $this->assertSame(100.0, $byCode['I1']['value']);
        $this->assertSame(0.0, $byCode['I1']['before']);
        $this->assertSame(100.0, $byCode['I1']['change']);
        $this->assertTrue($byCode['I1']['improved']);

        // "Ran out of materials" is a lower-is-better indicator: it fell, so it improved.
        $this->assertSame(0.0, $byCode['I3']['value']);
        $this->assertSame(-100.0, $byCode['I3']['change']);
        $this->assertTrue($byCode['I3']['improved']);

        // The baseline has no earlier survey to compare with.
        $first = app(ProgrammeIndicatorService::class)->withComparison($baseline);
        $this->assertNull($first['previous']);
    }

    public function test_a_respondent_who_gave_no_direct_answer_is_left_out_of_that_indicator(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $project = $this->project($user, $workspace, 'Wuse school');
        $assessment = $this->survey($project, $user, 10, best: true, skipCode: 'MHG.B1', skipCount: 4);

        $row = collect(app(ProgrammeIndicatorService::class)->forAssessment($assessment)['indicators'])->keyBy('code')['I1'];

        $this->assertSame(6, $row['n']);
        $this->assertSame(100.0, $row['value']);
    }

    public function test_the_overview_lists_each_school_once_and_summarises_surveys_separately(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();

        $this->site($user, $workspace, 'VYTTE_MHD_SCHOOL_V1', 'best', 'Kuje school', 'Kuje');
        $this->site($user, $workspace, 'VYTTE_MHD_PHC_V1', 'worst', 'Garki PHC', 'AMAC');
        $project = $this->project($user, $workspace, 'Bwari school');
        $this->survey($project, $user, 10, best: true);

        $overview = app(ProgrammeOverviewService::class)->forProgramme($workspace, 'menstrual-health-dignity-bank-baseline');

        $this->assertSame(2, $overview['assessed']);
        $this->assertSame(1, $overview['counts']['LOWER_NEED_READY']);
        $this->assertSame(1, $overview['counts']['HIGH_NEED_NOT_READY']);
        $this->assertSame(1, $overview['survey']['schools']);
        $this->assertSame(10, $overview['survey']['respondents']);

        $this->actingAs($user)->get(route('programmes.show', 'menstrual-health-dignity-bank-baseline'))
            ->assertOk()
            ->assertSee('What girls and women told us');

        $this->actingAs($user)->get(route('programmes.show', 'no-such-programme'))->assertNotFound();
    }

    public function test_the_survey_report_page_and_pdf_render(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->survey($this->project($user, $workspace, 'Bwari school'), $user, 10, best: true);

        $this->actingAs($user)->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('Had enough menstrual materials during the last period');

        $this->actingAs($user)->get(route('assessments.export.pdf', $assessment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_runner_lists_unanswered_required_questions_and_blocks_submit_until_none_remain(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createAssessment($user, $workspace, 'VYTTE_MHD_SCHOOL_V1');
        $assessment->update(['publish_status' => Assessment::PUBLISH_PUBLISHED]);
        $token = Str::random(32);
        AssessmentRespondentToken::create(['token' => $token, 'assessment_id' => $assessment->assessment_id]);

        $component = Livewire::test(PublicRespondentRunner::class, ['token' => $token]);
        $component->call('giveConsent');

        $this->assertCount(26, $component->instance()->unansweredRequired());
        $this->assertFalse($component->instance()->canSubmit());

        foreach (collect($component->get('questionData'))->where('is_scored', true) as $question) {
            $component->call('selectOption', $question['question_id'], $question['options'][0]['option_id']);
        }

        $this->assertSame([], $component->instance()->unansweredRequired());
        $this->assertTrue($component->instance()->canSubmit());
    }

    public function test_school_and_phc_assessments_can_be_answered_by_several_people_and_reported_as_one(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();

        foreach (['VYTTE_MHD_SCHOOL_V1', 'VYTTE_MHD_PHC_V1'] as $code) {
            $release = AssessmentCatalogueRelease::where('release_code', $code)->firstOrFail();
            $this->assertTrue($release->collection_config['allows_multi_respondent']);
            $this->assertSame(1, $release->collection_config['minimum_completed_respondents']);
        }

        // Three people at one school: a nurse, a teacher and a girl. Their section scores are averaged.
        $assessment = $this->survey($this->project($user, $workspace, 'Kwali school', 'Kwali'), $user, 3, best: [true, true, false], releaseCode: 'VYTTE_MHD_SCHOOL_V1');
        $report = app(ProgrammeReportService::class)->forAssessment($assessment);

        $this->assertSame(3, $assessment->aggregationResult->eligible_respondent_count);
        $this->assertEqualsWithDelta(66.7, (float) $report['provision']['score'], 0.5);
        $this->assertNotEmpty($report['gaps']);
        // The profile shows the most common answer and the average of a number, not the first person's.
        $answers = collect($report['context'])->flatMap(fn ($group) => $group['items'])->pluck('answer', 'question');
        $this->assertSame('110 students', $answers['About how many students does the school have in total?']);
    }

    public function test_a_must_have_is_missing_when_at_least_half_of_the_answerers_say_so(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();

        // One of three says there is no girls' toilet: outvoted, so the school is not held back.
        $outvoted = $this->survey($this->project($user, $workspace, 'Abaji school one', 'Abaji'), $user, 3, best: [true, true, false], releaseCode: 'VYTTE_MHD_SCHOOL_V1');
        $this->assertSame([], app(ProgrammeReportService::class)->forAssessment($outvoted)['flags']);

        // Two of three say so: the must-have is missing and the school is not ready.
        $missing = $this->survey($this->project($user, $workspace, 'Abaji school two', 'Abaji'), $user, 3, best: [true, false, false], releaseCode: 'VYTTE_MHD_SCHOOL_V1');
        $report = app(ProgrammeReportService::class)->forAssessment($missing);
        $this->assertContains('There is no toilet used only by girls.', $report['flags']);
        $this->assertSame('HIGH_NEED_NOT_READY', $report['classification']['key']);
    }
    public function test_monitor_shows_where_responses_come_from(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $project = $this->project($user, $workspace, 'Bwari school', 'Bwari');
        $assessment = $this->survey($project, $user, 1, best: true, finalize: false);
        $assessment->update(['publish_status' => Assessment::PUBLISH_PUBLISHED]);

        $this->actingAs($user)->get(route('monitor.index'))
            ->assertOk()
            ->assertSee('Where responses are coming from')
            ->assertSee('Bwari');
    }

    /* ---------------------------------------------------------------- helpers */

    /** @return array{0: User, 1: Workspace} */
    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->workspace_id, 'user_id' => $user->user_id, 'role' => 'OWNER']);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);
        app()->instance('current.workspace', $workspace);

        return [$user, $workspace];
    }

    private function project(User $user, Workspace $workspace, string $name, string $subRegion = 'Bwari'): Project
    {
        $project = Project::create(['name' => $name, 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'HEALTH_FACILITY',
            'name' => $name,
            'owner_workspace_id' => $workspace->workspace_id,
            'country' => 'Nigeria',
            'region' => 'FCT',
            'sub_region' => $subRegion,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        return $project;
    }

    private function createAssessment(User $user, Workspace $workspace, string $releaseCode): Assessment
    {
        $project = $this->project($user, $workspace, $releaseCode.' '.uniqid());
        $release = AssessmentCatalogueRelease::where('release_code', $releaseCode)->firstOrFail();

        return app(AssessmentCreationService::class)->createFromCatalogue($project, $release, creatorId: $user->user_id);
    }

    /** A completed school or PHC assessment answered by one person: every scored question best or worst. */
    private function site(User $user, Workspace $workspace, string $releaseCode, string $mode, ?string $name = null, string $subRegion = 'Bwari'): Assessment
    {
        $project = $this->project($user, $workspace, $name ?? $releaseCode.' '.uniqid(), $subRegion);

        return $this->survey($project, $user, 1, best: $mode === 'best', releaseCode: $releaseCode);
    }
    /**
     * A girls' and women's survey with $respondents submissions. `best` makes every scored answer the
     * strongest, otherwise the weakest. The first $skipCount respondents give no direct answer to
     * $skipCode (an "I cannot give a direct answer" state).
     */
    private function survey(Project $project, User $user, int $respondents, bool|array $best, ?string $skipCode = null, int $skipCount = 0, bool $finalize = true, string $releaseCode = 'VYTTE_MHD_GIRLS_V1'): Assessment
    {
        $release = AssessmentCatalogueRelease::where('release_code', $releaseCode)->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release, creatorId: $user->user_id);
        $questions = collect($assessment->snapshot->payload)->flatMap(fn ($m) => $m['questions'] ?? [])->values();

        for ($i = 0; $i < $respondents; $i++) {
            $token = Str::random(32);
            AssessmentRespondentToken::create(['token' => $token, 'assessment_id' => $assessment->assessment_id]);
            $session = PublicResponseSession::create([
                'token' => $token,
                'assessment_id' => $assessment->assessment_id,
                'locale' => 'en',
                'started_at' => now(),
                'last_activity_at' => now(),
            ]);

            foreach ($questions as $question) {
                $row = ['assessment_id' => $assessment->assessment_id, 'question_id' => $question['question_id'], 'public_response_session_id' => $session->session_id, 'answered_at' => now()];
                $options = collect($question['options'])->values();

                if ($question['response_type'] === 'MULTI_SELECT') {
                    Response::create($row + ['typed_value' => ['type' => 'MULTI_SELECT', 'option_ids' => [$options[0]['option_id']]], 'response_state' => 'ANSWERED']);

                    continue;
                }
                if ($question['response_type'] === 'NUMERIC') {
                    Response::create($row + ['value_numeric' => 100 + $i * 10, 'typed_value' => ['type' => 'NUMBER', 'value' => 100 + $i * 10], 'response_state' => 'ANSWERED']);

                    continue;
                }
                if ($skipCode !== null && $question['question_code'] === $skipCode && $i < $skipCount) {
                    Response::create($row + ['response_state' => 'UNKNOWN']);

                    continue;
                }
                if ($question['is_scored']) {
                    $sorted = $options->sortBy('score_weight')->values();
                    $pick = (is_array($best) ? $best[$i] : $best) ? $sorted->last() : $sorted->first();
                } else {
                    $pick = $options[0];
                }
                Response::create($row + ['value_option_id' => $pick['option_id'], 'response_state' => 'ANSWERED']);
            }

            app(RespondentSubmissionService::class)->submit($session);
        }

        if ($finalize) {
            app(MultiRespondentAggregationService::class)->finalize($assessment, $user->user_id);
            sleep(1); // distinct completion times so "earlier survey" is unambiguous
        }

        return $assessment->fresh(['snapshot', 'reportSnapshot', 'aggregationResult']);
    }
}
