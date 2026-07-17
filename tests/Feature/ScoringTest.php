<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentModule;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentScore;
use App\Models\AssessmentTier;
use App\Models\Project;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Response;
use App\Models\SubIndex;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\ScoringService;
use Database\Seeders\HivawQuestionsSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScoringTest extends TestCase
{
    use RefreshDatabase;

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

    private function setupAssessment(Workspace $workspace, User $user): Assessment
    {

        $project = Project::create([
            'name' => 'Scoring Test Project',
            'owner_user_id' => $user->user_id,
        ]);

        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Test Community',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);

        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $tier = AssessmentTier::where('tier_code', 'TIER_1')->first();
        $module = AssessmentModule::where('module_code', 'HIVAW')->first();

        $assessment = Assessment::create([
            'target_id' => $target->target_id,
            'project_id' => $project->project_id,
            'assessment_tier_id' => $tier->assessment_tier_id,
            'status' => 'IN_PROGRESS',
            'publish_status' => 'DRAFT',
            'assessor_name' => 'Test Assessor',
            'started_at' => now(),
        ]);

        AssessmentModuleScope::create([
            'assessment_id' => $assessment->assessment_id,
            'module_id' => $module->module_id,
            'in_scope' => true,
            'is_category_default' => true,
            'status' => 'PENDING',
        ]);

        return $assessment;
    }

    private function answerAllScoredQuestionsWithBestOption(Assessment $assessment): void
    {
        $scopeRow = DB::table('assessment_module_scope')
            ->where('assessment_id', $assessment->assessment_id)
            ->where('in_scope', true)
            ->first();

        $questions = DB::table('questions')
            ->where('module_id', $scopeRow->module_id)
            ->where('is_scored', true)
            ->pluck('question_id');

        foreach ($questions as $questionId) {
            $bestOptionId = DB::table('question_options')
                ->where('question_id', $questionId)
                ->orderByDesc('score_weight')
                ->value('option_id');

            Response::updateOrCreate(
                ['assessment_id' => $assessment->assessment_id, 'question_id' => $questionId, 'respondent_id' => null],
                ['value_option_id' => $bestOptionId, 'answered_at' => now()]
            );
        }
    }

    // ---- Score band helper ----

    public function test_band_for_strong_score(): void
    {
        $service = app(ScoringService::class);
        $this->assertSame('strong', $service->bandFor(70.0));
        $this->assertSame('strong', $service->bandFor(100.0));
        $this->assertSame('strong', $service->bandFor(85.5));
    }

    public function test_band_for_moderate_score(): void
    {
        $service = app(ScoringService::class);
        $this->assertSame('moderate', $service->bandFor(45.0));
        $this->assertSame('moderate', $service->bandFor(69.9));
        $this->assertSame('moderate', $service->bandFor(55.0));
    }

    public function test_band_for_weak_score(): void
    {
        $service = app(ScoringService::class);
        $this->assertSame('weak', $service->bandFor(0.0));
        $this->assertSame('weak', $service->bandFor(44.9));
        $this->assertSame('weak', $service->bandFor(20.0));
    }

    public function test_band_for_null_is_uncalibrated(): void
    {
        $this->assertSame('uncalibrated', app(ScoringService::class)->bandFor(null));
    }

    // ---- NOT_CALIBRATED when no answers ----

    public function test_no_answers_produces_not_calibrated_score(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupAssessment($workspace, $user);

        app(ScoringService::class)->calculate($assessment);

        $score = AssessmentScore::where('assessment_id', $assessment->assessment_id)->first();
        $this->assertNotNull($score);
        $this->assertNull($score->overall_score);
        $this->assertSame('NOT_CALIBRATED', $score->calibration_status);

        // Every sub-index must also be NOT_CALIBRATED
        $subScores = DB::table('sub_index_scores')
            ->where('assessment_id', $assessment->assessment_id)
            ->get();

        $this->assertGreaterThan(0, $subScores->count());
        foreach ($subScores as $row) {
            $this->assertNull($row->score);
            $this->assertSame('NOT_CALIBRATED', $row->calibration_status);
        }
    }

    // ---- CALIBRATED with all best options ----

    public function test_all_best_answers_produces_perfect_score(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupAssessment($workspace, $user);

        $this->answerAllScoredQuestionsWithBestOption($assessment);

        app(ScoringService::class)->calculate($assessment);

        $score = AssessmentScore::where('assessment_id', $assessment->assessment_id)->first();
        $this->assertNotNull($score);
        $this->assertEquals(100.0, (float) $score->overall_score);
        $this->assertSame('CALIBRATED', $score->calibration_status);
        $this->assertSame('strong', app(ScoringService::class)->bandFor((float) $score->overall_score));
        $this->assertSame(ScoringService::ALGORITHM_VERSION, $score->scoring_version);
    }

    public function test_zero_to_one_question_scales_are_normalized_to_zero_to_one_hundred(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupAssessment($workspace, $user);

        DB::table('question_options')->update([
            'score_weight' => DB::raw('score_weight / 100.0'),
        ]);

        $this->answerAllScoredQuestionsWithBestOption($assessment);
        app(ScoringService::class)->calculate($assessment);

        $score = AssessmentScore::where('assessment_id', $assessment->assessment_id)->firstOrFail();
        $this->assertEquals(100.0, (float) $score->overall_score);
        $this->assertSame('CALIBRATED', $score->calibration_status);
        $this->assertSame(ScoringService::ALGORITHM_VERSION, $score->scoring_version);
    }

    public function test_scoring_includes_every_in_scope_module(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupAssessment($workspace, $user);

        $secondModule = AssessmentModule::create([
            'target_type_code' => 'COMMUNITY',
            'module_code' => 'MULTI2',
            'module_name' => 'Second Scored Module',
            'is_active' => true,
        ]);
        AssessmentModuleScope::create([
            'assessment_id' => $assessment->assessment_id,
            'module_id' => $secondModule->module_id,
            'in_scope' => true,
            'is_category_default' => false,
            'status' => 'PENDING',
        ]);

        $subIndex = SubIndex::create([
            'module_id' => $secondModule->module_id,
            'domain_id' => DB::table('domains')->value('domain_id'),
            'acronym' => 'MULTI2',
            'full_name' => 'Second Module Index',
        ]);
        $question = Question::create([
            'module_id' => $secondModule->module_id,
            'question_code' => 'MULTI2.Q1',
            'question_text' => 'Second module scored question',
            'type_id' => DB::table('question_types')->where('type_code', 'SINGLE_SELECT')->value('type_id'),
            'display_order' => 1,
            'is_active' => true,
            'is_scored' => true,
        ]);
        $option = QuestionOption::create([
            'question_id' => $question->question_id,
            'option_label' => 'Half ready',
            'option_order' => 1,
            'score_weight' => 50,
        ]);
        $subIndex->questions()->attach($question->question_id, ['weight' => 1]);
        Response::create([
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $question->question_id,
            'value_option_id' => $option->option_id,
            'answered_at' => now(),
        ]);

        app(ScoringService::class)->calculate($assessment);

        $this->assertDatabaseHas('sub_index_scores', [
            'assessment_id' => $assessment->assessment_id,
            'sub_index_id' => $subIndex->sub_index_id,
            'score' => 50,
            'scoring_version' => ScoringService::ALGORITHM_VERSION,
        ]);

        $score = AssessmentScore::where('assessment_id', $assessment->assessment_id)->firstOrFail();
        $this->assertEquals(50.0, (float) $score->overall_score);
        $this->assertSame('PARTIAL', $score->calibration_status);
        $this->assertSame(2, $score->active_module_count);
    }

    // ---- PARTIAL when only some questions answered ----

    public function test_partial_answers_produce_partial_calibration_status(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupAssessment($workspace, $user);

        // Answer only the first scored question
        $scopeRow = DB::table('assessment_module_scope')
            ->where('assessment_id', $assessment->assessment_id)
            ->first();

        $firstQuestion = DB::table('questions')
            ->where('module_id', $scopeRow->module_id)
            ->where('is_scored', true)
            ->orderBy('display_order')
            ->first();

        $bestOptionId = DB::table('question_options')
            ->where('question_id', $firstQuestion->question_id)
            ->orderByDesc('score_weight')
            ->value('option_id');

        Response::updateOrCreate(
            ['assessment_id' => $assessment->assessment_id, 'question_id' => $firstQuestion->question_id, 'respondent_id' => null],
            ['value_option_id' => $bestOptionId, 'answered_at' => now()]
        );

        app(ScoringService::class)->calculate($assessment);

        $score = AssessmentScore::where('assessment_id', $assessment->assessment_id)->first();
        $this->assertNotNull($score);
        $this->assertNotNull($score->overall_score);
        // Not all sub-indices can be fully calibrated from one answer
        $this->assertNotSame('CALIBRATED', $score->calibration_status);
    }

    // ---- Known math: verify exact sub-index computation ----

    public function test_known_weight_produces_correct_sub_index_score(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupAssessment($workspace, $user);

        // CHKI sub-index has 2 questions (HIVAW.D1.Q2 and HIVAW.D1.Q3)
        // Both linked with weight = 1.0
        // Q2: pick "Unprotected sex only" (score_weight = 30)
        // Q3: pick "Sometimes" (score_weight = 30)
        // Expected CHKI score = (30*1 + 30*1) / (1+1) = 30.0

        $chkiSubIndexId = DB::table('sub_indices')
            ->where('acronym', 'CHKI')
            ->value('sub_index_id');

        $chkiQuestions = DB::table('sub_index_questions')
            ->where('sub_index_id', $chkiSubIndexId)
            ->pluck('question_id')
            ->all();

        $this->assertCount(2, $chkiQuestions);

        foreach ($chkiQuestions as $questionId) {
            // Find the option with score_weight = 30 for each CHKI question
            $optionId = DB::table('question_options')
                ->where('question_id', $questionId)
                ->where('score_weight', 30)
                ->value('option_id');

            $this->assertNotNull($optionId, "Option with weight 30 not found for question $questionId");

            Response::updateOrCreate(
                ['assessment_id' => $assessment->assessment_id, 'question_id' => $questionId, 'respondent_id' => null],
                ['value_option_id' => $optionId, 'answered_at' => now()]
            );
        }

        app(ScoringService::class)->calculate($assessment);

        $chkiScore = DB::table('sub_index_scores')
            ->where('assessment_id', $assessment->assessment_id)
            ->where('sub_index_id', $chkiSubIndexId)
            ->first();

        $this->assertNotNull($chkiScore);
        $this->assertEquals(30.0, (float) $chkiScore->score, 'CHKI score should be 30.0 from two weight-30 options');
        $this->assertSame('CALIBRATED', $chkiScore->calibration_status);
    }

    // ---- Submit triggers scoring ----

    public function test_submit_route_triggers_scoring(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupAssessment($workspace, $user);

        $this->answerAllScoredQuestionsWithBestOption($assessment);

        $this->actingAs($user)
            ->post(route('assessments.submit', $assessment));

        $this->assertEquals('COMPLETE', $assessment->fresh()->status);

        $score = AssessmentScore::where('assessment_id', $assessment->assessment_id)->first();
        $this->assertNotNull($score, 'assessment_scores record must exist after submit');
        $this->assertEquals(100.0, (float) $score->overall_score);
    }

    // ---- Maturity level assigned ----

    public function test_maturity_level_assigned_on_calibrated_score(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupAssessment($workspace, $user);

        $this->answerAllScoredQuestionsWithBestOption($assessment);

        app(ScoringService::class)->calculate($assessment);

        $score = AssessmentScore::where('assessment_id', $assessment->assessment_id)->first();
        $this->assertNotNull($score->maturity_level_id, 'A maturity level must be assigned for a calibrated score');
    }

    // ---- Scoring is idempotent (re-run overwrites, no duplicates) ----

    public function test_scoring_is_idempotent(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupAssessment($workspace, $user);

        $this->answerAllScoredQuestionsWithBestOption($assessment);

        $service = app(ScoringService::class);
        $service->calculate($assessment);
        $service->calculate($assessment);

        $this->assertDatabaseCount('assessment_scores', 1);
    }

    // ---- Cross-workspace isolation ----

    public function test_scoring_does_not_bleed_across_workspaces(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        // Workspace A — complete and score an assessment
        [$userA, $workspaceA] = $this->userWithWorkspace();
        $assessmentA = $this->setupAssessment($workspaceA, $userA);
        $this->answerAllScoredQuestionsWithBestOption($assessmentA);
        app(ScoringService::class)->calculate($assessmentA);

        // Workspace B — no assessments
        $userB = User::factory()->create();
        $workspaceB = Workspace::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspaceB->workspace_id,
            'user_id' => $userB->user_id,
            'role' => 'OWNER',
        ]);
        $userB->update(['active_workspace_id' => $workspaceB->workspace_id]);
        app()->instance('current.workspace', $workspaceB);

        // Workspace B has no projects in their workspace scope — isolation enforced at project level
        $wsBProjects = Project::all();
        $this->assertCount(0, $wsBProjects, 'Workspace B must not see workspace A projects');

        // Only one assessment_scores record exists — workspace A's
        $this->assertDatabaseCount('assessment_scores', 1);
        $this->assertDatabaseHas('assessment_scores', [
            'assessment_id' => $assessmentA->assessment_id,
        ]);

        // Workspace B cannot run workspace A's assessment
        $this->actingAs($userB)
            ->get(route('assessments.run', $assessmentA))
            ->assertNotFound();
    }
}
