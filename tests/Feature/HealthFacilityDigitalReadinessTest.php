<?php

namespace Tests\Feature;

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
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthFacilityDigitalReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_thirty_one_questions_are_published_with_distinct_hashes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $hashes = [];
        for ($number = 1; $number <= 31; $number++) {
            $code = sprintf('DHR.%03d', $number);
            $question = Question::where('question_code', $code)->firstOrFail();
            $version = QuestionVersion::where('question_id', $question->question_id)
                ->where('status', QuestionVersion::STATUS_PUBLISHED)
                ->firstOrFail();

            $this->assertNotNull($version->content_hash);
            $hashes[] = $version->content_hash;
        }

        $this->assertCount(31, array_unique($hashes));
    }

    public function test_framework_has_thirty_scored_and_one_unscored_placement(): void
    {
        $this->seed(DatabaseSeeder::class);

        $module = AssessmentModule::where('module_code', 'DHR')->firstOrFail();
        $framework = DepartmentFrameworkVersion::where('module_id', $module->module_id)
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->firstOrFail();

        $placements = FrameworkQuestionPlacement::where('framework_version_id', $framework->framework_version_id)->get();
        $this->assertCount(31, $placements);
        $this->assertCount(30, $placements->where('scoring_contribution', true));

        $unscored = $placements->where('scoring_contribution', false);
        $this->assertCount(1, $unscored);
        $unscoredQuestion = Question::find($unscored->first()->question_id);
        $this->assertSame('DHR.014', $unscoredQuestion->question_code);
    }

    public function test_catalogue_release_is_published_as_focused(): void
    {
        $this->seed(DatabaseSeeder::class);

        $release = AssessmentCatalogueRelease::where('release_code', 'VYTTE_DHR_V1')->firstOrFail();
        $this->assertSame('FOCUSED', $release->creation_path);
        $this->assertSame(AssessmentCatalogueRelease::STATUS_PUBLISHED, $release->status);
    }

    public function test_no_product_name_appears_anywhere_in_the_published_content(): void
    {
        $this->seed(DatabaseSeeder::class);

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
        $this->seed(DatabaseSeeder::class);

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

    private function runAssessment(User $user, Workspace $workspace, string $answerMode): Assessment
    {
        $project = Project::create(['name' => "DHR {$answerMode} Project", 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'HEALTH_FACILITY',
            'name' => "DHR {$answerMode} Facility",
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $release = AssessmentCatalogueRelease::where('release_code', 'VYTTE_DHR_V1')->firstOrFail();
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
