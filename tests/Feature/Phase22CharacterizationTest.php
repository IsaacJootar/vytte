<?php

namespace Tests\Feature;

use App\Livewire\AssessmentRunner;
use App\Livewire\PublicRespondentRunner;
use App\Models\Assessment;
use App\Models\AssessmentModule;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentRespondentToken;
use App\Models\AssessmentScore;
use App\Models\AssessmentTier;
use App\Models\Project;
use App\Models\Question;
use App\Models\Response;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\ScoringService;
use Database\Seeders\HivawQuestionsSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 22 regression characterization.
 *
 * These tests intentionally record current behavior, including known unsafe or
 * incomplete behavior. Changing an assertion requires the corresponding
 * architecture decision to be approved first.
 */
class Phase22CharacterizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);
    }

    private function createWorkspaceContext(string $plan = 'PRO'): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['plan' => $plan]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);

        $user->update(['active_workspace_id' => $workspace->workspace_id]);
        app()->instance('current.workspace', $workspace);

        return [$user, $workspace];
    }

    private function createAssessment(Workspace $workspace, User $user): Assessment
    {
        $project = Project::create([
            'name' => 'Phase 22 Characterization',
            'owner_user_id' => $user->user_id,
        ]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Characterization Community',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $assessment = Assessment::create([
            'target_id' => $target->target_id,
            'project_id' => $project->project_id,
            'assessment_tier_id' => AssessmentTier::where('tier_code', 'TIER_1')->value('assessment_tier_id'),
            'status' => 'IN_PROGRESS',
            'publish_status' => 'DRAFT',
            'started_at' => now(),
        ]);

        AssessmentModuleScope::create([
            'assessment_id' => $assessment->assessment_id,
            'module_id' => AssessmentModule::where('module_code', 'HIVAW')->value('module_id'),
            'in_scope' => true,
            'is_category_default' => true,
            'status' => 'PENDING',
        ]);

        return $assessment;
    }

    public function test_livewire_mount_enforces_workspace_boundary(): void
    {
        [$owner, $workspaceA] = $this->createWorkspaceContext();
        $assessment = $this->createAssessment($workspaceA, $owner);

        [$otherUser] = $this->createWorkspaceContext();

        Livewire::actingAs($otherUser)
            ->test(AssessmentRunner::class, ['assessment' => $assessment])
            ->assertNotFound();
    }

    public function test_runner_rejects_option_that_belongs_to_another_question(): void
    {
        [$user, $workspace] = $this->createWorkspaceContext();
        $assessment = $this->createAssessment($workspace, $user);
        $questions = Question::where('module_id', AssessmentModule::where('module_code', 'HIVAW')->value('module_id'))
            ->with('options')
            ->orderBy('display_order')
            ->take(2)
            ->get();

        $component = Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment]);
        $component->call('giveConsent');
        $component->call('selectOption', $questions[0]->question_id, $questions[1]->options->first()->option_id);

        $this->assertDatabaseMissing('responses', [
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $questions[0]->question_id,
            'value_option_id' => $questions[1]->options->first()->option_id,
        ]);
    }

    public function test_schema_rejects_duplicate_staff_response_keys(): void
    {
        [$user, $workspace] = $this->createWorkspaceContext();
        $assessment = $this->createAssessment($workspace, $user);
        $question = Question::with('options')->orderBy('display_order')->first();

        $options = $question->options->take(2)->values();
        Response::create([
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $question->question_id,
            'respondent_id' => null,
            'value_option_id' => $options[0]->option_id,
            'answered_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        Response::create([
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $question->question_id,
            'respondent_id' => null,
            'value_option_id' => $options[1]->option_id,
            'answered_at' => now(),
        ]);
    }

    public function test_public_runner_loads_every_in_scope_module(): void
    {
        [$user, $workspace] = $this->createWorkspaceContext();
        $assessment = $this->createAssessment($workspace, $user);

        $secondModule = AssessmentModule::create([
            'target_type_code' => 'COMMUNITY',
            'module_code' => 'PH22X',
            'module_name' => 'Phase 22 Second Module',
            'is_active' => true,
            'requires_consent' => false,
        ]);
        $numericTypeId = DB::table('question_types')->where('type_code', 'NUMERIC')->value('type_id');
        $secondQuestion = Question::create([
            'module_id' => $secondModule->module_id,
            'question_code' => 'PH22.WASH.Q1',
            'question_text' => 'Second module characterization question',
            'type_id' => $numericTypeId,
            'display_order' => 1,
            'is_active' => true,
            'is_scored' => false,
        ]);

        AssessmentModuleScope::create([
            'assessment_id' => $assessment->assessment_id,
            'module_id' => $secondModule->module_id,
            'in_scope' => true,
            'is_category_default' => false,
            'status' => 'PENDING',
        ]);

        $token = Str::random(32);
        AssessmentRespondentToken::create([
            'token' => $token,
            'assessment_id' => $assessment->assessment_id,
        ]);

        $component = Livewire::test(PublicRespondentRunner::class, ['token' => $token]);
        $component->call('giveConsent');

        $loadedQuestionIds = collect($component->get('questionData'))->pluck('question_id');
        $this->assertCount(10, $loadedQuestionIds);
        $this->assertTrue($loadedQuestionIds->contains($secondQuestion->question_id));
    }

    public function test_current_scoring_excludes_public_responses(): void
    {
        [$user, $workspace] = $this->createWorkspaceContext();
        $assessment = $this->createAssessment($workspace, $user);
        $question = Question::where('is_scored', true)->with('options')->orderBy('display_order')->first();
        $bestOption = $question->options->sortByDesc('score_weight')->first();

        Response::create([
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $question->question_id,
            'respondent_id' => (string) Str::uuid(),
            'value_option_id' => $bestOption->option_id,
            'answered_at' => now(),
        ]);

        app(ScoringService::class)->calculate($assessment);

        $score = AssessmentScore::where('assessment_id', $assessment->assessment_id)->firstOrFail();
        $this->assertNull($score->overall_score);
        $this->assertSame('NOT_CALIBRATED', $score->calibration_status);
    }

    public function test_current_runner_has_no_storage_path_for_numeric_question(): void
    {
        [$user, $workspace] = $this->createWorkspaceContext();
        $assessment = $this->createAssessment($workspace, $user);
        $moduleId = AssessmentModule::where('module_code', 'HIVAW')->value('module_id');
        $numericQuestion = Question::create([
            'module_id' => $moduleId,
            'question_code' => 'PH22.NUM.Q1',
            'question_text' => 'Numeric characterization question',
            'type_id' => DB::table('question_types')->where('type_code', 'NUMERIC')->value('type_id'),
            'display_order' => 99,
            'is_active' => true,
            'is_scored' => true,
        ]);

        $component = Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment]);
        $component->call('giveConsent');

        $numericData = collect($component->get('questionData'))->firstWhere('question_id', $numericQuestion->question_id);
        $this->assertSame([], $numericData['options']);
        $this->assertArrayNotHasKey('type_id', $numericData);
        $this->assertFalse($component->instance()->canSubmit());
    }
}
