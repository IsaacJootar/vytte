<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentScore;
use App\Models\Project;
use App\Models\Response;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AssessmentCreationService;
use App\Services\ScoringService;
use Database\Seeders\PlatformGovernedDemoSeeder;
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

        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();

        return app(AssessmentCreationService::class)->createFromCatalogue($project, $release);
    }

    private function answerAllScoredQuestionsWithBestOption(Assessment $assessment): void
    {
        $questions = collect($assessment->snapshot->payload)
            ->flatMap(fn ($module) => $module['questions'] ?? [])
            ->where('is_scored', true);

        foreach ($questions as $question) {
            $bestOptionId = collect($question['options'])
                ->sortByDesc('score_weight')
                ->first()['option_id'];

            Response::updateOrCreate(
                ['assessment_id' => $assessment->assessment_id, 'question_id' => $question['question_id'], 'respondent_id' => null],
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

    public function test_numeric_response_uses_frozen_numeric_scoring_band(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupAssessment($workspace, $user);
        $payload = $assessment->snapshot->payload;
        $question = $payload[0]['questions'][0];
        $payload[0]['questions'][0]['response_type'] = 'NUMERIC';
        $payload[0]['questions'][0]['options'] = [];
        $payload[0]['questions'][0]['numeric_bands'] = [
            ['min_value' => 0, 'max_value' => 50, 'score_weight' => 0],
            ['min_value' => 50, 'max_value' => 80, 'score_weight' => 50],
            ['min_value' => 80, 'max_value' => 100, 'score_weight' => 100],
        ];
        $assessment->snapshot->update(['payload' => $payload]);
        $response = Response::create([
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $question['question_id'],
            'value_numeric' => 75,
            'answered_at' => now(),
        ]);

        $result = app(ScoringService::class)->scoreResponseSet($assessment, collect([$response])->keyBy('question_id'));

        $this->assertSame(50.0, $result['overall_score']);
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
        $this->seed(PlatformGovernedDemoSeeder::class);

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
        $this->seed(PlatformGovernedDemoSeeder::class);

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
        $this->seed(PlatformGovernedDemoSeeder::class);

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

    public function test_scoring_uses_the_frozen_snapshot_not_live_scope_changes(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupAssessment($workspace, $user);
        $this->answerAllScoredQuestionsWithBestOption($assessment);

        app(ScoringService::class)->calculate($assessment);

        $score = AssessmentScore::where('assessment_id', $assessment->assessment_id)->firstOrFail();
        $this->assertEquals(100.0, (float) $score->overall_score);
        $this->assertSame('CALIBRATED', $score->calibration_status);
        $this->assertSame(1, $score->active_module_count);
    }

    // ---- PARTIAL when only some questions answered ----

    public function test_partial_answers_produce_partial_calibration_status(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupAssessment($workspace, $user);

        // Answer only the first scored question
        $firstQuestion = collect($assessment->snapshot->payload)
            ->flatMap(fn ($module) => $module['questions'] ?? [])
            ->where('is_scored', true)
            ->sortBy('display_order')
            ->first();
        $bestOptionId = collect($firstQuestion['options'])->sortByDesc('score_weight')->first()['option_id'];

        Response::updateOrCreate(
            ['assessment_id' => $assessment->assessment_id, 'question_id' => $firstQuestion['question_id'], 'respondent_id' => null],
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
        $this->seed(PlatformGovernedDemoSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupAssessment($workspace, $user);

        $profile = collect($assessment->snapshot->payload[0]['scoring_profile'])->first();
        $profileQuestions = collect($profile['questions'])->pluck('question_id')->all();
        $this->assertCount(2, $profileQuestions);

        foreach ($profileQuestions as $questionId) {
            $question = collect($assessment->snapshot->payload[0]['questions'])->firstWhere('question_id', $questionId);
            $optionId = collect($question['options'])->firstWhere('score_weight', 50)['option_id'];
            Response::updateOrCreate(
                ['assessment_id' => $assessment->assessment_id, 'question_id' => $questionId, 'respondent_id' => null],
                ['value_option_id' => $optionId, 'answered_at' => now()]
            );
        }

        app(ScoringService::class)->calculate($assessment);

        $subIndexScore = DB::table('sub_index_scores')
            ->where('assessment_id', $assessment->assessment_id)
            ->where('sub_index_id', $profile['sub_index_id'])
            ->first();

        $this->assertNotNull($subIndexScore);
        $this->assertEquals(50.0, (float) $subIndexScore->score);
        $this->assertSame('CALIBRATED', $subIndexScore->calibration_status);
    }

    // ---- Submit triggers scoring ----

    public function test_submit_route_triggers_scoring(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

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
        $this->seed(PlatformGovernedDemoSeeder::class);

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
        $this->seed(PlatformGovernedDemoSeeder::class);

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
        $this->seed(PlatformGovernedDemoSeeder::class);

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
