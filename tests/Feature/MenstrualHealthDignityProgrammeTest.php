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

    public function test_the_school_and_phc_assessments_carry_the_girls_own_questions_in_one_template(): void
    {
        $this->seedOfficialCatalogue();

        // [module, placed, scored]: the facility questions plus the 21 questions for the girls and women
        // themselves, 17 of them scored (in a section kept out of the two headline scores) so that every
        // outcome question has to be answered.
        foreach ([['MHDS', 59, 43], ['MHDP', 60, 45]] as [$module, $placed, $scored]) {
            $framework = DepartmentFrameworkVersion::where('module_id', AssessmentModule::where('module_code', $module)->value('module_id'))
                ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
                ->firstOrFail();
            $placements = FrameworkQuestionPlacement::where('framework_version_id', $framework->framework_version_id)->get();

            $this->assertCount($placed, $placements, "{$module} placements");
            $this->assertCount($scored, $placements->where('scoring_contribution', true), "{$module} scored");
        }

        // There is no separate assessment for the girls and women: only the two, and nothing retired is offered.
        $this->assertSame(
            ['VYTTE_MHD_PHC_V5', 'VYTTE_MHD_SCHOOL_V5'],
            AssessmentCatalogueRelease::where('release_code', 'like', 'VYTTE_MHD_%')
                ->where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)->orderBy('release_code')->pluck('release_code')->all()
        );
    }

    public function test_each_template_declares_its_report_type_and_other_templates_stay_standard(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();

        $school = $this->site($user, $workspace, 'VYTTE_MHD_SCHOOL_V5', 'best');
        $dhr = $this->createAssessment($user, $workspace, 'VYTTE_DHR_V1');

        $this->assertSame(ReportType::PROGRAMME, ReportType::of($school));
        $this->assertNotEmpty(ReportType::config($school)['indicators']);
        $this->assertSame(ReportType::STANDARD, ReportType::of($dhr));
    }
    public function test_a_strong_ready_school_is_labelled_later_with_no_must_haves_missing(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();

        $report = app(ProgrammeReportService::class)->forAssessment($this->site($user, $workspace, 'VYTTE_MHD_SCHOOL_V5', 'best'));

        $this->assertSame('LOWER_NEED_READY', $report['classification']['key']);
        $this->assertSame('Later', $report['classification']['label']);
        $this->assertSame([], $report['flags']);
        $this->assertSame(100.0, (float) $report['provision']['score']);
    }

    public function test_the_weakest_answers_name_every_missing_must_have(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();

        $report = app(ProgrammeReportService::class)->forAssessment($this->site($user, $workspace, 'VYTTE_MHD_PHC_V5', 'worst'));

        $this->assertSame('HIGH_NEED_NOT_READY', $report['classification']['key']);
        $this->assertSame('Prepare first', $report['classification']['label']);
        $this->assertCount(4, $report['flags']);
        $this->assertContains('There is no secure place to store menstrual products.', $report['flags']);
    }

    public function test_the_site_report_page_and_pdf_render_for_a_programme_assessment(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->site($user, $workspace, 'VYTTE_MHD_SCHOOL_V5', 'best');

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

    public function test_the_overview_lists_each_school_once_and_pools_what_girls_reported(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();

        $this->site($user, $workspace, 'VYTTE_MHD_SCHOOL_V5', 'best', 'Kuje school', 'Kuje');
        $this->site($user, $workspace, 'VYTTE_MHD_PHC_V5', 'worst', 'Garki PHC', 'AMAC');
        // A third school answered by ten girls.
        $this->survey($this->project($user, $workspace, 'Bwari school'), $user, 10, best: true);

        $overview = app(ProgrammeOverviewService::class)->forProgramme($workspace, 'menstrual-health-dignity-bank-baseline');

        $this->assertSame(3, $overview['assessed']);
        $this->assertSame(2, $overview['counts']['LOWER_NEED_READY']);
        $this->assertSame(1, $overview['counts']['HIGH_NEED_NOT_READY']);
        $this->assertSame(3, $overview['survey']['schools']);
        $this->assertSame(12, $overview['survey']['respondents']);

        $this->actingAs($user)->get(route('programmes.show', 'menstrual-health-dignity-bank-baseline'))
            ->assertOk()
            ->assertSee('What girls and women told us');

        $this->actingAs($user)->get(route('programmes.show', 'no-such-programme'))->assertNotFound();
    }

    public function test_reports_state_findings_in_plain_sentences_not_the_question_and_answer(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->site($user, $workspace, 'VYTTE_MHD_SCHOOL_V5', 'worst');
        $report = app(ProgrammeReportService::class)->forAssessment($assessment);

        $gaps = collect($report['gaps'])->pluck('gap');
        $this->assertContains('There is no water at the toilet.', $report['all_gaps']);
        $this->assertNotContains('Is there water at or near the toilet today?', $report['all_gaps']);
        $this->assertNotEmpty($gaps);
        // A must-have already named under "must fix" is not repeated among the gaps.
        $this->assertContains('There is no toilet used only by girls.', $report['flags']);
        $this->assertNotContains('There is no toilet used only by girls.', $gaps);

        // The profile reads as short lines, not as questions with answers.
        $this->assertContains('100 students · 100 girls', $report['profile']);
        $this->actingAs($user)->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee($gaps->first())
            ->assertDontSee('Is there water at or near the toilet today?')
            ->assertDontSee('What type of school is this?');
    }

    public function test_the_report_opens_with_a_summary_the_must_have_checklist_and_next_steps(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->site($user, $workspace, 'VYTTE_MHD_SCHOOL_V5', 'worst');
        $report = app(ProgrammeReportService::class)->forAssessment($assessment);

        $this->assertNotEmpty($report['summary']);
        $this->assertContains('4 must-haves are missing before a Dignity Bank can start.', $report['summary']);
        $this->assertCount(4, $report['must_haves']);
        $this->assertSame([true, true, true, true], collect($report['must_haves'])->pluck('missing')->all());
        $this->assertNotEmpty($report['next_steps']);

        // A ready school shows every must-have as in place, in the template's own words.
        $ready = app(ProgrammeReportService::class)->forAssessment($this->site($user, $workspace, 'VYTTE_MHD_SCHOOL_V5', 'best'));
        $this->assertSame([false, false, false, false], collect($ready['must_haves'])->pluck('missing')->all());
        $this->assertContains('A secure place to store menstrual products is available.', collect($ready['must_haves'])->pluck('ok')->all());

        $this->actingAs($user)->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('In short')
            ->assertSee('Must-haves for a Dignity Bank')
            ->assertSee('What to do next')
            ->assertSee('Dignity Banks that keep working');
    }

    public function test_the_respondent_page_names_what_is_being_answered_and_explains_the_counts(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createAssessment($user, $workspace, 'VYTTE_MHD_SCHOOL_V5');
        $assessment->update(['publish_status' => Assessment::PUBLISH_PUBLISHED]);
        $token = Str::random(32);
        AssessmentRespondentToken::create(['token' => $token, 'assessment_id' => $assessment->assessment_id]);

        Livewire::test(PublicRespondentRunner::class, ['token' => $token])
            ->assertSee('You are answering')
            ->assertSee('Menstrual Health & Dignity Assessment — School')
            ->assertSee($assessment->target->name);

        $component = Livewire::test(PublicRespondentRunner::class, ['token' => $token]);
        $component->call('giveConsent');
        $component->assertSee('0 of 59 answered')
            ->assertSee('43 more required questions to answer (16 optional)');
    }

    public function test_ticked_boxes_are_saved_as_a_whole_set_so_quick_taps_cannot_get_out_of_step(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createAssessment($user, $workspace, 'VYTTE_MHD_SCHOOL_V5');
        $assessment->update(['publish_status' => Assessment::PUBLISH_PUBLISHED]);
        $token = Str::random(32);
        AssessmentRespondentToken::create(['token' => $token, 'assessment_id' => $assessment->assessment_id]);

        $component = Livewire::test(PublicRespondentRunner::class, ['token' => $token]);
        $component->call('giveConsent');
        $question = collect($component->get('questionData'))->firstWhere('response_type', 'MULTI_SELECT');
        $ids = collect($question['options'])->pluck('option_id')->all();
        $saved = fn () => Response::where('assessment_id', $assessment->assessment_id)->where('question_id', $question['question_id'])->first()?->typed_value['option_ids'];

        $component->call('saveMultiOptions', $question['question_id'], [$ids[0], $ids[1], $ids[2], $ids[3], $ids[4]]);
        $this->assertSame([$ids[0], $ids[1], $ids[2], $ids[3], $ids[4]], $saved());

        // The last set sent is the set saved, whatever was ticked before; choices from elsewhere are ignored.
        $component->call('saveMultiOptions', $question['question_id'], [$ids[4], $ids[0], 999999]);
        $this->assertSame([$ids[0], $ids[4]], $saved());

        // Un-ticking everything removes the answer.
        $component->call('saveMultiOptions', $question['question_id'], []);
        $this->assertNull($saved());
    }
    public function test_the_owner_can_switch_shared_device_on_for_a_link_and_nobody_else_can(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createAssessment($user, $workspace, 'VYTTE_MHD_SCHOOL_V5');
        $token = AssessmentRespondentToken::create(['token' => Str::random(32), 'assessment_id' => $assessment->assessment_id]);
        $this->assertFalse((bool) $token->fresh()->shared_device);

        $this->actingAs($user)->patch(route('assessments.respondent-link.shared-device', [$assessment, $token]), ['shared_device' => '1'])
            ->assertRedirect();
        $this->assertTrue($token->fresh()->shared_device);

        $this->actingAs($user)->patch(route('assessments.respondent-link.shared-device', [$assessment, $token]), ['shared_device' => '0']);
        $this->assertFalse($token->fresh()->shared_device);

        // Someone from another workspace cannot change it.
        [$stranger] = $this->userWithWorkspace();
        $this->actingAs($stranger)->patch(route('assessments.respondent-link.shared-device', [$assessment, $token]), ['shared_device' => '1'])
            ->assertNotFound();
        $this->assertFalse($token->fresh()->shared_device);
    }

    public function test_on_a_shared_device_each_person_gets_a_fresh_sheet_and_every_submission_counts(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createAssessment($user, $workspace, 'VYTTE_MHD_SCHOOL_V5');
        $assessment->update(['publish_status' => Assessment::PUBLISH_PUBLISHED]);
        $token = Str::random(32);
        $link = AssessmentRespondentToken::create(['token' => $token, 'assessment_id' => $assessment->assessment_id, 'shared_device' => true]);

        $answerEverything = function ($component): void {
            foreach (collect($component->get('questionData'))->where('is_scored', true) as $question) {
                $component->call('selectOption', $question['question_id'], $question['options'][0]['option_id']);
            }
        };

        $first = Livewire::test(PublicRespondentRunner::class, ['token' => $token]);
        $first->call('giveConsent');
        $answerEverything($first);
        $first->call('submit')
            ->assertSee('Thank you')
            ->assertSee('1 person has answered on this phone.')
            ->assertSee('Start the next person');

        // The next person gets a new, empty sheet; the first person's answers stay saved and counted.
        $first->call('startNextPerson')->assertRedirect(route('respondent.show', $token));
        $second = Livewire::test(PublicRespondentRunner::class, ['token' => $token]);
        $second->assertDontSee('Thank you')->assertSee('Shared phone · 1 already answered');
        $second->call('giveConsent');
        $this->assertCount(43, $second->instance()->unansweredRequired());
        $answerEverything($second);
        $second->call('submit')->assertSee('2 people have answered on this phone.');

        $sessions = PublicResponseSession::where('assessment_id', $assessment->assessment_id)->get();
        $this->assertCount(2, $sessions);
        $this->assertCount(2, $sessions->whereNotNull('submitted_at'));
        $this->assertSame(2, $link->fresh()->use_count);
    }

    public function test_without_the_shared_device_switch_a_phone_still_gets_one_response_only(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createAssessment($user, $workspace, 'VYTTE_MHD_SCHOOL_V5');
        $assessment->update(['publish_status' => Assessment::PUBLISH_PUBLISHED]);
        $token = Str::random(32);
        AssessmentRespondentToken::create(['token' => $token, 'assessment_id' => $assessment->assessment_id]);

        $component = Livewire::test(PublicRespondentRunner::class, ['token' => $token]);
        $component->call('giveConsent');
        foreach (collect($component->get('questionData'))->where('is_scored', true) as $question) {
            $component->call('selectOption', $question['question_id'], $question['options'][0]['option_id']);
        }
        $component->call('submit')->assertSee('Thank you')->assertDontSee('Start the next person');

        // Asking for a new sheet is refused, so the page cannot be tricked into allowing it.
        $component->call('startNextPerson')->assertNoRedirect();
        $this->assertCount(1, PublicResponseSession::where('assessment_id', $assessment->assessment_id)->get());
    }

    public function test_the_report_says_how_many_people_were_helped_to_answer(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->survey($this->project($user, $workspace, 'Helped school'), $user, 5, best: true, helped: 2);

        $this->assertSame(['answered' => 5, 'helped' => 2], app(ProgrammeIndicatorService::class)->assistance($assessment));
        $this->actingAs($user)->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('2 of 5 said someone helped them answer');
    }
    public function test_choosing_an_answer_never_moves_on_by_itself_and_next_is_always_the_persons_own(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createAssessment($user, $workspace, 'VYTTE_MHD_SCHOOL_V5');
        $assessment->update(['publish_status' => Assessment::PUBLISH_PUBLISHED]);
        $token = Str::random(32);
        AssessmentRespondentToken::create(['token' => $token, 'assessment_id' => $assessment->assessment_id]);

        $component = Livewire::test(PublicRespondentRunner::class, ['token' => $token]);
        $component->call('giveConsent');

        // A single-choice question, then a multiple-choice one: neither moves on by itself.
        $questions = collect($component->get('questionData'));
        $single = $questions->search(fn ($q) => $q['response_type'] === 'SINGLE_SELECT');
        $component->call('goToQuestion', $single)
            ->call('selectOption', $questions[$single]['question_id'], $questions[$single]['options'][0]['option_id'])
            ->assertSet('currentIndex', $single);

        $multi = $questions->search(fn ($q) => $q['response_type'] === 'MULTI_SELECT');
        $component->call('goToQuestion', $multi)
            ->call('toggleMultiOption', $questions[$multi]['question_id'], $questions[$multi]['options'][0]['option_id'])
            ->assertSet('currentIndex', $multi)
            ->call('goToQuestion', $multi + 1)
            ->assertSet('currentIndex', $multi + 1);

        // The page no longer carries the whole question list a second time in its state.
        $this->assertFalse(property_exists(PublicRespondentRunner::class, 'allQuestionData'));
    }
    public function test_people_answering_are_offered_two_ways_out_not_five_and_others_are_refused(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createAssessment($user, $workspace, 'VYTTE_MHD_SCHOOL_V5');
        $assessment->update(['publish_status' => Assessment::PUBLISH_PUBLISHED]);
        $token = Str::random(32);
        AssessmentRespondentToken::create(['token' => $token, 'assessment_id' => $assessment->assessment_id]);

        $component = Livewire::test(PublicRespondentRunner::class, ['token' => $token]);
        $component->call('giveConsent');

        $this->assertSame(['UNKNOWN' => "I don't know", 'DECLINED' => 'I prefer not to say'], $component->instance()->answerAlternatives());
        $component->assertSee("I don't know")->assertSee('I prefer not to say')
            ->assertDontSee('Not observed')->assertDontSee('Not assessed');

        // "I don't know" is accepted; a choice the template does not offer is refused.
        $question = collect($component->get('questionData'))->firstWhere('is_scored', true);
        $component->call('setResponseState', $question['question_id'], 'NOT_OBSERVED');
        $this->assertSame(0, Response::where('assessment_id', $assessment->assessment_id)->where('response_state', 'NOT_OBSERVED')->count());
        $component->call('setResponseState', $question['question_id'], 'UNKNOWN');
        $this->assertSame(1, Response::where('assessment_id', $assessment->assessment_id)->where('response_state', 'UNKNOWN')->count());
    }
    public function test_the_report_shows_what_the_girls_reported_with_the_facility_findings(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->survey($this->project($user, $workspace, 'Bwari school'), $user, 10, best: true);

        $this->actingAs($user)->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('What girls and women told us')
            ->assertSee('Had enough menstrual materials during the last period')
            ->assertSee('Later');

        $this->actingAs($user)->get(route('assessments.export.pdf', $assessment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_runner_lists_unanswered_required_questions_and_blocks_submit_until_none_remain(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createAssessment($user, $workspace, 'VYTTE_MHD_SCHOOL_V5');
        $assessment->update(['publish_status' => Assessment::PUBLISH_PUBLISHED]);
        $token = Str::random(32);
        AssessmentRespondentToken::create(['token' => $token, 'assessment_id' => $assessment->assessment_id]);

        $component = Livewire::test(PublicRespondentRunner::class, ['token' => $token]);
        $component->call('giveConsent');

        $this->assertCount(43, $component->instance()->unansweredRequired());
        $this->assertSame(43, $component->instance()->requiredTotal());
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

        foreach (['VYTTE_MHD_SCHOOL_V5', 'VYTTE_MHD_PHC_V5'] as $code) {
            $release = AssessmentCatalogueRelease::where('release_code', $code)->firstOrFail();
            $this->assertTrue($release->collection_config['allows_multi_respondent']);
            $this->assertSame(1, $release->collection_config['minimum_completed_respondents']);
        }

        // Three people at one school: a nurse, a teacher and a girl. Their section scores are averaged.
        $assessment = $this->survey($this->project($user, $workspace, 'Kwali school', 'Kwali'), $user, 3, best: [true, true, false], releaseCode: 'VYTTE_MHD_SCHOOL_V5');
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
        $outvoted = $this->survey($this->project($user, $workspace, 'Abaji school one', 'Abaji'), $user, 3, best: [true, true, false], releaseCode: 'VYTTE_MHD_SCHOOL_V5');
        $this->assertSame([], app(ProgrammeReportService::class)->forAssessment($outvoted)['flags']);

        // Two of three say so: the must-have is missing and the school is not ready.
        $missing = $this->survey($this->project($user, $workspace, 'Abaji school two', 'Abaji'), $user, 3, best: [true, false, false], releaseCode: 'VYTTE_MHD_SCHOOL_V5');
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
    private function survey(Project $project, User $user, int $respondents, bool|array $best, ?string $skipCode = null, int $skipCount = 0, bool $finalize = true, string $releaseCode = 'VYTTE_MHD_SCHOOL_V5', int $helped = 0): Assessment
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
                } elseif ($options->whereNotNull('score_weight')->isNotEmpty()) {
                    // An unscored question with a right answer (the knowledge questions) varies the same way.
                    $sorted = $options->sortBy('score_weight')->values();
                    $pick = (is_array($best) ? $best[$i] : $best) ? $sorted->last() : $sorted->first();
                } elseif ($question['question_code'] === 'MHG.A3' && $i < $helped) {
                    $pick = $options[1]; // "Someone read the questions to me"
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
