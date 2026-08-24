<?php

namespace Tests\Feature;

use App\Livewire\AssessmentRunner;
use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FrameworkQuestionPlacement;
use App\Models\Project;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\Response;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AssessmentCreationService;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\RestoresCatalogueSnapshot;
use Tests\TestCase;

class HealthFacilityDigitalReadinessTest extends TestCase
{
    use RefreshDatabase;
    use RestoresCatalogueSnapshot;

    public function test_all_thirty_two_questions_are_published_with_distinct_hashes(): void
    {
        $this->seedOfficialCatalogue();

        $hashes = [];
        for ($number = 1; $number <= 32; $number++) {
            $code = sprintf('DHR.%03d', $number);
            $question = Question::where('question_code', $code)->firstOrFail();
            $version = QuestionVersion::where('question_id', $question->question_id)
                ->where('status', QuestionVersion::STATUS_PUBLISHED)
                ->firstOrFail();

            $this->assertNotNull($version->content_hash);
            $hashes[] = $version->content_hash;
        }

        $this->assertCount(32, array_unique($hashes));
    }

    public function test_framework_has_thirty_scored_and_two_unscored_placements(): void
    {
        $this->seedOfficialCatalogue();

        $module = AssessmentModule::where('module_code', 'DHR')->firstOrFail();
        $framework = DepartmentFrameworkVersion::where('module_id', $module->module_id)
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->firstOrFail();

        $placements = FrameworkQuestionPlacement::where('framework_version_id', $framework->framework_version_id)->get();
        $this->assertCount(32, $placements);
        $this->assertCount(30, $placements->where('scoring_contribution', true));

        $unscored = $placements->where('scoring_contribution', false);
        $this->assertCount(2, $unscored);
        $unscoredCodes = $unscored->map(fn ($p) => Question::find($p->question_id)->question_code)->sort()->values()->all();
        $this->assertSame(['DHR.014', 'DHR.032'], $unscoredCodes);
    }

    public function test_catalogue_release_is_published_as_focused(): void
    {
        $this->seedOfficialCatalogue();

        $release = AssessmentCatalogueRelease::where('release_code', 'VYTTE_DHR_V1')->firstOrFail();
        $this->assertSame('FOCUSED', $release->creation_path);
        $this->assertSame(AssessmentCatalogueRelease::STATUS_PUBLISHED, $release->status);
    }

    public function test_no_product_name_appears_anywhere_in_the_published_content(): void
    {
        $this->seedOfficialCatalogue();

        $module = AssessmentModule::where('module_code', 'DHR')->firstOrFail();
        $framework = DepartmentFrameworkVersion::where('module_id', $module->module_id)
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->firstOrFail();

        $questionTexts = Question::where('module_id', $module->module_id)->pluck('question_text')->join(' ');
        $this->assertStringNotContainsStringIgnoringCase('cureva', $questionTexts);
        $this->assertStringNotContainsStringIgnoringCase('cureva', (string) $framework->description);
    }

    public function test_best_answers_score_near_the_top_and_worst_answers_score_near_the_bottom(): void
    {
        $this->seedOfficialCatalogue();

        [$user, $workspace] = $this->userWithWorkspace();

        $best = $this->runAssessment($user, $workspace, 'best');
        $worst = $this->runAssessment($user, $workspace, 'worst');

        // Every question in this framework was authored so its highest-scoring option
        // represents the healthiest answer and its lowest represents the weakest — this is
        // the one thing most likely to have been gotten backwards given how many questions
        // (frequency-of-problem, workaround maturity, etc.) needed the scoring direction
        // reasoned through individually rather than copied straight from the source email.
        $this->assertGreaterThanOrEqual(95.0, (float) $best->score->overall_score);
        $this->assertLessThanOrEqual(5.0, (float) $worst->score->overall_score);
        $this->assertGreaterThan((float) $worst->score->overall_score, (float) $best->score->overall_score);
    }

    public function test_dhr_assessment_resolves_to_its_own_maturity_bands(): void
    {
        $this->seedOfficialCatalogue();

        [$user, $workspace] = $this->userWithWorkspace();

        $best = $this->runAssessment($user, $workspace, 'best', 'VYTTE_DHR_V1');
        $worst = $this->runAssessment($user, $workspace, 'worst', 'VYTTE_DHR_V1');

        $this->assertSame('Advanced', $best->score->maturityLevel->level_name);
        $this->assertSame('Critical', $worst->score->maturityLevel->level_name);

        // Both bands must actually belong to the DHR framework, not just happen to share a name.
        $framework = DepartmentFrameworkVersion::where('module_id', AssessmentModule::where('module_code', 'DHR')->value('module_id'))
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)->firstOrFail();
        $this->assertSame($framework->framework_version_id, $best->score->maturityLevel->framework_version_id);
        $this->assertSame($framework->framework_version_id, $worst->score->maturityLevel->framework_version_id);
    }

    public function test_other_frameworks_still_use_the_platform_default_bands(): void
    {
        $this->seedOfficialCatalogue();

        [$user, $workspace] = $this->userWithWorkspace();

        // A stable, always-published focused release unrelated to WASH/DHR — WASH's own
        // focused release code is randomised on every republish (AssessmentPublicationService
        // generates a random suffix), so it can't be hardcoded here.
        $worst = $this->runAssessment($user, $workspace, 'worst', 'VYTTE_IPC_V1');

        $this->assertNull($worst->score->maturityLevel->framework_version_id);
        $this->assertContains($worst->score->maturityLevel->level_name, [
            'Urgent Action', 'Foundational', 'Developing', 'Established', 'Leading',
        ]);
    }

    public function test_comprehensive_assessment_always_uses_the_platform_default(): void
    {
        $this->seedOfficialCatalogue();

        [$user, $workspace] = $this->userWithWorkspace();

        // Any currently-published multi-framework Comprehensive release exercises the same
        // "more than one framework in scope" fallback path — it doesn't need to specifically
        // include DHR, since resolveMaturityLevelId() only ever counts distinct frameworks in
        // scope. Looked up by criteria rather than a hardcoded version, since comprehensive
        // release codes advance (superseding earlier versions) whenever any pinned department
        // framework is corrected — VYTTE_PHC_ASSESSMENT_V1..V3 are already superseded.
        $releaseCode = AssessmentCatalogueRelease::where('creation_path', 'COMPREHENSIVE')
            ->where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)
            ->get()
            ->first(fn ($release) => $release->departmentFrameworkVersions()->count() > 1)
            ->release_code;
        $worst = $this->runAssessment($user, $workspace, 'worst', $releaseCode);

        $this->assertNull($worst->score->maturityLevel->framework_version_id);
    }

    public function test_red_flag_gating_rule_targets_the_four_confirmed_minimum_conditions(): void
    {
        $this->seedOfficialCatalogue();

        $release = AssessmentCatalogueRelease::where('release_code', 'VYTTE_DHR_V1')->firstOrFail();
        $rules = $release->aggregation_policy['compound_critical_rules'] ?? [];

        $this->assertCount(1, $rules);
        $this->assertSame('ANY', $rules[0]['operator']);
        $this->assertCount(4, $rules[0]['conditions']);

        $expectedQuestionIds = Question::whereIn('question_code', ['DHR.017', 'DHR.025', 'DHR.027', 'DHR.029'])
            ->pluck('question_id')
            ->sort()
            ->values()
            ->all();
        $conditionQuestionIds = collect($rules[0]['conditions'])
            ->pluck('source_question_id')
            ->sort()
            ->values()
            ->all();
        $this->assertSame($expectedQuestionIds, $conditionQuestionIds);

        foreach ($rules[0]['conditions'] as $condition) {
            $this->assertSame('OPTION_SELECTED', $condition['comparison']);
            $version = QuestionVersion::where('question_id', $condition['source_question_id'])
                ->where('status', QuestionVersion::STATUS_PUBLISHED)
                ->firstOrFail();
            $worstOption = collect($version->options)->sortBy('score_weight')->first();
            $this->assertSame($worstOption['option_id'], $condition['value']);
        }
    }

    public function test_worst_answers_trigger_the_red_flag_with_a_named_reason(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();

        $worst = $this->runAssessment($user, $workspace, 'worst');
        $best = $this->runAssessment($user, $workspace, 'best');

        $this->assertSame('CRITICAL_FAILURE', $worst->score->calibration_status);
        // DHR's own pre-existing per-question policy (option_score_at_or_below: 0) also fires
        // here, since every scored question's worst option scores 0 — that's expected and
        // independent of this rule; the assertion only needs the compound rule's own reason.
        $this->assertContains(
            'The facility does not meet a minimum condition for digital deployment: no reliable electricity or backup power, no person responsible for implementation, no basic mechanism for protecting patient information, or no realistic staff training pathway.',
            $worst->score->critical_findings
        );

        $this->assertNotSame('CRITICAL_FAILURE', $best->score->calibration_status);
        $this->assertSame([], $best->score->critical_findings);
    }

    public function test_assessor_can_record_verification_status_independent_of_the_respondent_answer(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->startAssessment($user, $workspace);

        $component = Livewire::actingAs($user)->test(AssessmentRunner::class, ['assessment' => $assessment]);
        $component->call('giveConsent');
        $question = collect($component->get('questionData'))->firstWhere('question_code', 'DHR.018');

        $component
            ->call('selectOption', $question['question_id'], $question['options'][0]['option_id'])
            ->call('saveObservationStatus', $question['question_id'], 'PARTIALLY_VERIFIED');

        $this->assertDatabaseHas('responses', [
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $question['question_id'],
            'observation_status' => 'PARTIALLY_VERIFIED',
        ]);
    }

    public function test_assessor_can_check_off_specific_evidence_items(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->startAssessment($user, $workspace);

        $component = Livewire::actingAs($user)->test(AssessmentRunner::class, ['assessment' => $assessment]);
        $component->call('giveConsent');
        $question = collect($component->get('questionData'))->firstWhere('question_code', 'DHR.018');
        $this->assertNotEmpty($question['observation_checklist']);

        $component
            ->call('selectOption', $question['question_id'], $question['options'][0]['option_id'])
            ->call('toggleEvidenceItem', $question['question_id'], 'Internet modem/router observed')
            ->call('toggleEvidenceItem', $question['question_id'], 'Connectivity test completed');

        $response = Response::where('assessment_id', $assessment->assessment_id)
            ->where('question_id', $question['question_id'])->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['Internet modem/router observed', 'Connectivity test completed'],
            $response->evidence_checked
        );

        // Toggling the same item again removes it.
        $component->call('toggleEvidenceItem', $question['question_id'], 'Internet modem/router observed');
        $this->assertEqualsCanonicalizing(
            ['Connectivity test completed'],
            $response->fresh()->evidence_checked
        );
    }

    public function test_verification_controls_are_only_offered_for_questions_flagged_for_observation(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->startAssessment($user, $workspace);

        $component = Livewire::actingAs($user)->test(AssessmentRunner::class, ['assessment' => $assessment]);
        $component->call('giveConsent');
        $questions = collect($component->get('questionData'))->keyBy('question_code');

        $this->assertTrue($questions['DHR.018']['requires_observation']);
        $this->assertFalse($questions['DHR.001']['requires_observation']);

        // The save methods themselves refuse to record anything against a question that was
        // never flagged for observation — not just a UI omission.
        $component->call('selectOption', $questions['DHR.001']['question_id'], $questions['DHR.001']['options'][0]['option_id'])
            ->call('saveObservationStatus', $questions['DHR.001']['question_id'], 'VERIFIED');

        $this->assertDatabaseMissing('responses', [
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $questions['DHR.001']['question_id'],
            'observation_status' => 'VERIFIED',
        ]);
    }

    public function test_observation_status_does_not_affect_scoring(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();

        $withVerification = $this->runAssessment($user, $workspace, 'worst', 'VYTTE_DHR_V1');
        [$user2, $workspace2] = $this->userWithWorkspace();
        $withoutVerification = $this->runAssessment($user2, $workspace2, 'worst', 'VYTTE_DHR_V1');

        Response::where('assessment_id', $withVerification->assessment_id)
            ->update(['observation_status' => 'NOT_VERIFIED', 'evidence_checked' => json_encode(['Internet modem/router observed'])]);
        app(ScoringService::class)->calculate($withVerification->fresh());

        $this->assertSame(
            (float) $withoutVerification->score->overall_score,
            (float) $withVerification->fresh('score')->score->overall_score
        );
    }

    public function test_respondent_can_name_their_own_priorities_and_pick_a_top_one(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->startAssessment($user, $workspace);

        $component = Livewire::actingAs($user)->test(AssessmentRunner::class, ['assessment' => $assessment]);
        $component->call('giveConsent');
        $question = collect($component->get('questionData'))->firstWhere('question_code', 'DHR.032');
        $this->assertSame('RANKING', $question['response_type']);

        $component
            ->call('saveRankingItem', $question['question_id'], 0, 'Cannot find old patient records')
            ->call('saveRankingItem', $question['question_id'], 1, 'Medicine stock runs out unpredictably')
            ->call('saveRankingItem', $question['question_id'], 2, 'Reporting to LGA takes too long')
            ->call('saveRankingPriority', $question['question_id'], 0);

        $response = Response::where('assessment_id', $assessment->assessment_id)
            ->where('question_id', $question['question_id'])->firstOrFail();
        $this->assertSame([
            'type' => 'RANKING',
            'items' => ['Cannot find old patient records', 'Medicine stock runs out unpredictably', 'Reporting to LGA takes too long'],
            'top_priority_index' => 0,
        ], $response->typed_value);
        $this->assertNull($response->value_option_id);
    }

    public function test_clearing_the_item_marked_as_top_priority_clears_that_pick_too(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->startAssessment($user, $workspace);

        $component = Livewire::actingAs($user)->test(AssessmentRunner::class, ['assessment' => $assessment]);
        $component->call('giveConsent');
        $question = collect($component->get('questionData'))->firstWhere('question_code', 'DHR.032');

        $component
            ->call('saveRankingItem', $question['question_id'], 0, 'Cannot find old patient records')
            ->call('saveRankingPriority', $question['question_id'], 0)
            ->call('saveRankingItem', $question['question_id'], 0, '');

        $response = Response::where('assessment_id', $assessment->assessment_id)
            ->where('question_id', $question['question_id'])->first();
        // The whole response is removed once every item is empty — nothing left worth saving.
        $this->assertNull($response);
    }

    public function test_priority_cannot_be_set_on_an_empty_item(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->startAssessment($user, $workspace);

        $component = Livewire::actingAs($user)->test(AssessmentRunner::class, ['assessment' => $assessment]);
        $component->call('giveConsent');
        $question = collect($component->get('questionData'))->firstWhere('question_code', 'DHR.032');

        $component->call('saveRankingPriority', $question['question_id'], 1);

        $this->assertDatabaseMissing('responses', [
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $question['question_id'],
        ]);
    }

    public function test_ranking_response_never_affects_the_score(): void
    {
        $this->seedOfficialCatalogue();
        [$user, $workspace] = $this->userWithWorkspace();

        $withPriorities = $this->runAssessment($user, $workspace, 'worst', 'VYTTE_DHR_V1');
        [$user2, $workspace2] = $this->userWithWorkspace();
        $withoutPriorities = $this->runAssessment($user2, $workspace2, 'worst', 'VYTTE_DHR_V1');

        $question = collect($withPriorities->snapshot->payload)
            ->flatMap(fn ($module) => $module['questions'] ?? [])
            ->firstWhere('question_code', 'DHR.032');
        Response::updateOrCreate(
            ['assessment_id' => $withPriorities->assessment_id, 'question_id' => $question['question_id'], 'respondent_id' => null],
            ['typed_value' => ['type' => 'RANKING', 'items' => ['A problem', '', ''], 'top_priority_index' => 0], 'response_state' => 'ANSWERED', 'answered_at' => now()]
        );
        app(ScoringService::class)->calculate($withPriorities->fresh());

        $this->assertSame(
            (float) $withoutPriorities->score->overall_score,
            (float) $withPriorities->fresh('score')->score->overall_score
        );
    }

    private function startAssessment(User $user, Workspace $workspace): Assessment
    {
        $project = Project::create(['name' => 'DHR runner project '.uniqid(), 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'HEALTH_FACILITY',
            'name' => 'DHR runner facility',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $release = AssessmentCatalogueRelease::where('release_code', 'VYTTE_DHR_V1')->firstOrFail();

        return app(AssessmentCreationService::class)->createFromCatalogue($project, $release);
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);
        app()->instance('current.workspace', $workspace);

        return [$user, $workspace];
    }

    private function runAssessment(User $user, Workspace $workspace, string $answerMode, string $releaseCode = 'VYTTE_DHR_V1'): Assessment
    {
        $project = Project::create(['name' => "{$releaseCode} {$answerMode} Project ".uniqid(), 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'HEALTH_FACILITY',
            'name' => "{$releaseCode} {$answerMode} Facility",
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $release = AssessmentCatalogueRelease::where('release_code', $releaseCode)->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release);

        $questions = collect($assessment->snapshot->payload)
            ->flatMap(fn ($module) => $module['questions'] ?? [])
            ->where('is_scored', true);

        foreach ($questions as $question) {
            $sorted = collect($question['options'])->whereNotNull('score_weight')->sortBy('score_weight');
            $optionId = ($answerMode === 'best' ? $sorted->last() : $sorted->first())['option_id'];

            Response::updateOrCreate(
                ['assessment_id' => $assessment->assessment_id, 'question_id' => $question['question_id'], 'respondent_id' => null],
                ['value_option_id' => $optionId, 'answered_at' => now()]
            );
        }

        app(ScoringService::class)->calculate($assessment);
        $assessment->update(['status' => Assessment::STATUS_COMPLETE, 'completed_at' => now()]);

        return $assessment->fresh(['snapshot', 'score']);
    }
}
