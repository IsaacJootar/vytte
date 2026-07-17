<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentModule;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentTier;
use App\Models\Project;
use App\Models\Response;
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

class ResultsTest extends TestCase
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

    private function setupCompleteAssessment(Workspace $workspace, User $user, bool $withAnswers = true): Assessment
    {

        $project = Project::create([
            'name' => 'Results Test Project',
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
            'status' => 'COMPLETE',
            'publish_status' => 'DRAFT',
            'assessor_name' => 'Test Assessor',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        AssessmentModuleScope::create([
            'assessment_id' => $assessment->assessment_id,
            'module_id' => $module->module_id,
            'in_scope' => true,
            'is_category_default' => true,
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);

        if ($withAnswers) {
            $scopeRow = DB::table('assessment_module_scope')
                ->where('assessment_id', $assessment->assessment_id)
                ->first();

            $questions = DB::table('questions')
                ->where('module_id', $scopeRow->module_id)
                ->where('is_scored', true)
                ->pluck('question_id');

            foreach ($questions as $qId) {
                $optionId = DB::table('question_options')
                    ->where('question_id', $qId)
                    ->orderByDesc('score_weight')
                    ->value('option_id');

                Response::updateOrCreate(
                    ['assessment_id' => $assessment->assessment_id, 'question_id' => $qId, 'respondent_id' => null],
                    ['value_option_id' => $optionId, 'answered_at' => now()]
                );
            }
        }

        app(ScoringService::class)->calculate($assessment);

        return $assessment;
    }

    // ---- Auth gate ----

    public function test_results_page_requires_auth(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspace, $user);

        $this->get(route('assessments.results', $assessment))
            ->assertRedirect(route('login'));
    }

    // ---- Redirect for incomplete assessment ----

    public function test_results_redirects_to_runner_if_not_complete(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $project = Project::create(['name' => 'Test', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'T',
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
            'assessor_name' => 'Tester',
            'started_at' => now(),
        ]);
        AssessmentModuleScope::create([
            'assessment_id' => $assessment->assessment_id,
            'module_id' => $module->module_id,
            'in_scope' => true,
            'is_category_default' => true,
            'status' => 'PENDING',
        ]);

        $this->actingAs($user)
            ->get(route('assessments.results', $assessment))
            ->assertRedirect(route('assessments.run', $assessment));
    }

    // ---- Results page renders correctly ----

    public function test_results_page_renders_for_calibrated_assessment(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspace, $user, withAnswers: true);

        $this->actingAs($user)
            ->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('Overall Score')
            ->assertSee('Sub-index Breakdown')
            ->assertSee('Domain Breakdown');
    }

    public function test_results_page_shows_module_name(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspace, $user);

        $this->actingAs($user)
            ->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('HIV Awareness'); // partial match on HIVAW module name
    }

    public function test_results_page_shows_score_for_calibrated_assessment(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspace, $user, withAnswers: true);

        // With all best answers, score = 100.0
        $this->actingAs($user)
            ->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('100');
    }

    // ---- Uncalibrated shown as flagged ----

    public function test_results_page_flags_uncalibrated_when_no_answers(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspace, $user, withAnswers: false);

        $response = $this->actingAs($user)
            ->get(route('assessments.results', $assessment));

        $response->assertOk();
        // The NOT_CALIBRATED warning banner must be visible — never shows a zero
        $response->assertSee('Not enough responses');
        $response->assertDontSee('>0.0<'); // score 0 must not appear
    }

    public function test_results_page_shows_uncalibrated_sub_index_flag(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspace, $user, withAnswers: false);

        $this->actingAs($user)
            ->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('Not calibrated — no answers');
    }

    // ---- Findings shown for weak scores ----

    public function test_findings_shown_for_weak_sub_index_scores(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();

        // Set up assessment with worst answers so scores will be Weak
        $project = Project::create(['name' => 'Findings Test', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'TC',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $tier = AssessmentTier::where('tier_code', 'TIER_1')->first();
        $module = AssessmentModule::where('module_code', 'HIVAW')->first();

        $assessment = Assessment::create([
            'target_id' => $target->target_id,
            'project_id' => $project->project_id,
            'assessment_tier_id' => $tier->assessment_tier_id,
            'status' => 'COMPLETE',
            'publish_status' => 'DRAFT',
            'assessor_name' => 'Tester',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        AssessmentModuleScope::create([
            'assessment_id' => $assessment->assessment_id,
            'module_id' => $module->module_id,
            'in_scope' => true,
            'is_category_default' => true,
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);

        // Answer all scored questions with the WORST option (lowest score_weight)
        $questions = DB::table('questions')
            ->where('module_id', $module->module_id)
            ->where('is_scored', true)
            ->pluck('question_id');

        foreach ($questions as $qId) {
            $worstOptionId = DB::table('question_options')
                ->where('question_id', $qId)
                ->orderBy('score_weight')
                ->value('option_id');

            Response::updateOrCreate(
                ['assessment_id' => $assessment->assessment_id, 'question_id' => $qId, 'respondent_id' => null],
                ['value_option_id' => $worstOptionId, 'answered_at' => now()]
            );
        }

        app(ScoringService::class)->calculate($assessment);

        $this->actingAs($user)
            ->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('Findings')
            ->assertSee('Weak');
    }

    // ---- Score history shows when 2+ runs ----

    public function test_score_history_hidden_for_single_run(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspace, $user);

        $this->actingAs($user)
            ->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertDontSee('Score History');
    }

    // ---- Workspace isolation ----

    public function test_workspace_b_cannot_view_workspace_a_results(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$userA, $workspaceA] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspaceA, $userA);

        $userB = User::factory()->create();
        $workspaceB = Workspace::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspaceB->workspace_id,
            'user_id' => $userB->user_id,
            'role' => 'OWNER',
        ]);
        $userB->update(['active_workspace_id' => $workspaceB->workspace_id]);
        app()->instance('current.workspace', $workspaceB);

        $this->actingAs($userB)
            ->get(route('assessments.results', $assessment))
            ->assertNotFound();
    }

    // ---- Domain breakdown visible ----

    public function test_domain_breakdown_shown_for_calibrated_assessment(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspace, $user, withAnswers: true);

        $this->actingAs($user)
            ->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('Domain Breakdown');
    }

    // ---- Print button present ----

    public function test_print_button_present_on_results_page(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->setupCompleteAssessment($workspace, $user);

        $this->actingAs($user)
            ->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('Print');
    }
}
