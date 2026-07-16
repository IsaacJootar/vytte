<?php

namespace Tests\Feature;

use App\Livewire\AssessmentRunner;
use App\Models\Assessment;
use App\Models\AssessmentModule;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentTier;
use App\Models\Project;
use App\Models\Response;
use App\Models\Target;
use App\Models\TargetCategory;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\HivawQuestionsSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssessmentTest extends TestCase
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

    private function createProjectWithTarget(Workspace $workspace, User $user): array
    {
        $this->seed(ReferenceDataSeeder::class);
        $categoryId = TargetCategory::where('category_code', 'GENERAL_COMMUNITY')->value('category_id');

        $project = Project::create([
            'name' => 'Test Project',
            'owner_user_id' => $user->user_id,
        ]);

        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Test Community',
            'category_id' => $categoryId,
            'owner_workspace_id' => $workspace->workspace_id,
        ]);

        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        return [$project, $target];
    }

    private function createAssessment(Project $project, Target $target): Assessment
    {
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

    // ---- Auth gate ----

    public function test_assessment_create_requires_auth(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $this->seed(ReferenceDataSeeder::class);
        $project = Project::create(['name' => 'Test', 'owner_user_id' => $user->user_id]);

        // Request without actingAs — unauthenticated
        $this->get(route('assessments.create', $project))->assertRedirect(route('login'));
    }

    public function test_assessment_run_requires_auth(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);

        // Request without actingAs — unauthenticated
        $this->get(route('assessments.run', $assessment))->assertRedirect(route('login'));
    }

    // ---- Create ----

    public function test_assessment_create_form_renders_with_modules(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);

        $this->actingAs($user)
            ->get(route('assessments.create', $project))
            ->assertOk()
            ->assertSee('Start Assessment')
            ->assertSee('HIVAW');
    }

    // ---- Store ----

    public function test_assessment_store_creates_assessment_and_module_scope(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);

        $module = AssessmentModule::where('module_code', 'HIVAW')->first();

        $response = $this->actingAs($user)
            ->post(route('assessments.store', $project), [
                'module_id' => $module->module_id,
            ]);

        $assessment = Assessment::first();
        $this->assertNotNull($assessment);
        $this->assertEquals('IN_PROGRESS', $assessment->status);
        $this->assertEquals($project->project_id, $assessment->project_id);
        $this->assertEquals($target->target_id, $assessment->target_id);

        $scope = AssessmentModuleScope::where('assessment_id', $assessment->assessment_id)->first();
        $this->assertNotNull($scope);
        $this->assertEquals($module->module_id, $scope->module_id);
        $this->assertTrue($scope->in_scope);

        $response->assertRedirect(route('assessments.run', $assessment));
    }

    public function test_assessment_store_requires_module_id(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project] = $this->createProjectWithTarget($workspace, $user);

        $this->actingAs($user)
            ->post(route('assessments.store', $project), [])
            ->assertSessionHasErrors(['module_id']);
    }

    // ---- Runner page ----

    public function test_assessment_runner_page_renders(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);

        $this->actingAs($user)
            ->get(route('assessments.run', $assessment))
            ->assertOk()
            ->assertSeeLivewire(AssessmentRunner::class);
    }

    // ---- Workspace isolation ----

    public function test_workspace_b_cannot_run_workspace_a_assessment(): void
    {
        [$userA, $workspaceA] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspaceA, $userA);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);

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
            ->get(route('assessments.run', $assessment))
            ->assertNotFound();
    }

    // ---- Livewire runner ----

    public function test_livewire_runner_loads_questions(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);

        Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment])
            ->assertSet('currentIndex', 0)
            ->assertCount('questionData', 9);
    }

    public function test_livewire_select_option_saves_response_and_advances(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);

        $component = Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment]);

        $component->call('giveConsent');

        $firstQuestion = $component->get('questionData')[0];
        $firstOptionId = $firstQuestion['options'][0]['option_id'];

        $component->call('selectOption', $firstQuestion['question_id'], $firstOptionId)
            ->assertSet('currentIndex', 1);

        $this->assertDatabaseHas('responses', [
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $firstQuestion['question_id'],
            'value_option_id' => $firstOptionId,
        ]);
    }

    public function test_livewire_select_option_updates_existing_response(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);

        $component = Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment]);

        $component->call('giveConsent');

        $firstQuestion = $component->get('questionData')[0];
        $optionA = $firstQuestion['options'][0]['option_id'];
        $optionB = $firstQuestion['options'][1]['option_id'];

        // Select option A then option B
        $component->call('selectOption', $firstQuestion['question_id'], $optionA);
        $component->call('goToQuestion', 0);
        $component->call('selectOption', $firstQuestion['question_id'], $optionB);

        // Only one response record should exist
        $this->assertDatabaseCount('responses', 1);
        $this->assertDatabaseHas('responses', [
            'question_id' => $firstQuestion['question_id'],
            'value_option_id' => $optionB,
        ]);
    }

    public function test_livewire_cannot_submit_with_unanswered_scored_questions(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);

        Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment])
            ->assertSet('savedResponses', [])
            ->assertDontSee('Submit Assessment');
    }

    // ---- Submit ----

    public function test_submit_marks_assessment_complete(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);

        $this->actingAs($user)
            ->post(route('assessments.submit', $assessment))
            ->assertRedirect(route('projects.show', $assessment->project_id));

        $this->assertEquals('COMPLETE', $assessment->fresh()->status);
        $this->assertNotNull($assessment->fresh()->completed_at);
    }

    public function test_submit_already_complete_assessment_redirects_gracefully(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);
        $assessment->update(['status' => 'COMPLETE', 'completed_at' => now()]);

        $this->actingAs($user)
            ->post(route('assessments.submit', $assessment))
            ->assertRedirect(route('projects.show', $assessment->project_id));
    }
}
