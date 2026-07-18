<?php

namespace Tests\Feature;

use App\Livewire\AssessmentRunner;
use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentTier;
use App\Models\FacilityProfile;
use App\Models\Project;
use App\Models\Question;
use App\Models\QuestionType;
use App\Models\Response;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\AssessmentTemplateSeeder;
use Database\Seeders\HivawQuestionsSeeder;
use Database\Seeders\PlanFeatureSeeder;
use Database\Seeders\PlatformGovernedDemoSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanFeatureSeeder::class);
    }

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['plan' => 'PRO']);
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

        $project = Project::create([
            'name' => 'Test Project',
            'owner_user_id' => $user->user_id,
        ]);

        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Test Community',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);

        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        return [$project, $target];
    }

    private function createHealthFacilityProject(Workspace $workspace, User $user): array
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        $project = Project::create([
            'name' => 'Clinic Test Project',
            'owner_user_id' => $user->user_id,
        ]);

        $profile = FacilityProfile::where('profile_code', 'CLINIC')->firstOrFail();
        $target = Target::create([
            'target_type_code' => 'HEALTH_FACILITY',
            'facility_profile_id' => $profile->facility_profile_id,
            'name' => 'ABC Clinic',
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

    public function test_assessment_create_form_renders_exactly_two_paths(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createHealthFacilityProject($workspace, $user);

        $this->actingAs($user)
            ->get(route('assessments.create', $project))
            ->assertOk()
            ->assertSee('Comprehensive Health Assessment')
            ->assertSee('Focused Health Assessment')
            ->assertSee('Demo Clinic Comprehensive Health Assessment')
            ->assertSee('Demo Focused Mental Health Assessment')
            ->assertDontSee('Standard Battery');
    }

    // ---- Store ----

    public function test_assessment_store_creates_assessment_and_module_scope(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createHealthFacilityProject($workspace, $user);

        $module = AssessmentModule::where('module_code', 'DMNH')->first();
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();

        $response = $this->actingAs($user)
            ->post(route('assessments.store', $project), [
                'creation_path' => 'FOCUSED',
                'catalogue_release_id' => $release->catalogue_release_id,
            ]);

        $assessment = Assessment::first();
        $this->assertNotNull($assessment);
        $this->assertEquals('IN_PROGRESS', $assessment->status);
        $this->assertEquals($project->project_id, $assessment->project_id);
        $this->assertEquals($target->target_id, $assessment->target_id);
        $this->assertEquals('FOCUSED', $assessment->creation_path);
        $this->assertEquals($release->catalogue_release_id, $assessment->catalogue_release_id);
        $this->assertNotNull($assessment->snapshot);

        $scope = AssessmentModuleScope::where('assessment_id', $assessment->assessment_id)
            ->where('in_scope', true)
            ->first();
        $this->assertNotNull($scope);
        $this->assertEquals($module->module_id, $scope->module_id);
        $this->assertTrue($scope->in_scope);

        $response->assertRedirect(route('assessments.run', $assessment));
    }

    public function test_assessment_store_requires_path_and_published_template(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project] = $this->createHealthFacilityProject($workspace, $user);

        $this->actingAs($user)
            ->post(route('assessments.store', $project), [])
            ->assertSessionHasErrors(['creation_path', 'catalogue_release_id']);
    }

    public function test_assessment_store_rejects_template_from_other_creation_path(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project] = $this->createHealthFacilityProject($workspace, $user);
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();

        $this->actingAs($user)
            ->post(route('assessments.store', $project), [
                'creation_path' => 'COMPREHENSIVE',
                'catalogue_release_id' => $release->catalogue_release_id,
            ])
            ->assertSessionHasErrors(['catalogue_release_id']);

        $this->assertDatabaseCount('assessments', 0);
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

    public function test_numeric_question_renders_and_saves_a_valid_measurement(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);
        $question = Question::where('question_code', 'HIVAW.D3.Q3')->firstOrFail();
        $question->update([
            'type_id' => QuestionType::where('type_code', 'NUMERIC')->value('type_id'),
            'numeric_unit' => 'days',
            'numeric_min' => 0,
            'numeric_max' => 365,
            'numeric_step' => 0.1,
        ]);

        Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment])
            ->call('giveConsent')
            ->call('saveNumeric', $question->question_id, '4.5')
            ->assertSet("savedNumericResponses.{$question->question_id}", 4.5);

        $this->assertDatabaseHas('responses', [
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $question->question_id,
            'value_numeric' => 4.5,
            'value_option_id' => null,
        ]);
    }

    public function test_numeric_question_rejects_values_outside_its_frozen_range(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);
        $question = Question::where('question_code', 'HIVAW.D3.Q3')->firstOrFail();
        $question->update([
            'type_id' => QuestionType::where('type_code', 'NUMERIC')->value('type_id'),
            'numeric_min' => 0,
            'numeric_max' => 100,
        ]);

        Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment])
            ->call('giveConsent')
            ->call('saveNumeric', $question->question_id, '101')
            ->assertHasErrors("numeric.{$question->question_id}");

        $this->assertDatabaseMissing('responses', [
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $question->question_id,
        ]);
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

    public function test_forged_active_workspace_without_membership_fails_closed(): void
    {
        [$owner, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $owner);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);
        $outsider = User::factory()->create(['active_workspace_id' => $workspace->workspace_id]);

        $this->actingAs($outsider)
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

    public function test_livewire_open_ended_response_is_saved_as_text(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);

        $component = Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment]);
        $component->call('giveConsent');

        $openQuestion = collect($component->get('questionData'))
            ->first(fn ($question) => $question['response_type'] === 'OPEN_ENDED' && $question['options'] === []);

        $this->assertNotNull($openQuestion);
        $component->call('saveText', $openQuestion['question_id'], 'Transport cost is the main barrier.');

        $this->assertDatabaseHas('responses', [
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $openQuestion['question_id'],
            'value_text' => 'Transport cost is the main barrier.',
        ]);
    }

    public function test_optional_evidence_note_is_attached_to_the_question_response(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);

        $component = Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment]);
        $component->call('giveConsent');
        $question = $component->get('questionData')[0];
        $optionId = $question['options'][0]['option_id'];

        $component
            ->call('selectOption', $question['question_id'], $optionId)
            ->call('saveEvidenceNote', $question['question_id'], 'Observed register for the previous month.');

        $this->assertDatabaseHas('responses', [
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $question['question_id'],
            'value_option_id' => $optionId,
            'evidence_note' => 'Observed register for the previous month.',
        ]);
    }

    public function test_evidence_note_alone_does_not_count_as_an_answer(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);

        $component = Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment]);
        $component->call('giveConsent');
        $question = $component->get('questionData')[0];

        $component
            ->call('saveEvidenceNote', $question['question_id'], 'Document is available for review.')
            ->assertSet('savedResponses', [])
            ->assertDontSee('Submit Assessment');
    }

    public function test_evidence_note_cannot_bypass_required_consent(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);

        $component = Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment]);
        $question = $component->get('questionData')[0];

        $component->call('saveEvidenceNote', $question['question_id'], 'Should not be accepted.');

        $this->assertDatabaseMissing('responses', [
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $question['question_id'],
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

    public function test_assessment_rejects_unknown_lifecycle_status(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);

        $this->expectException(\LogicException::class);
        $assessment->update(['status' => 'COMPLETED']);
    }

    public function test_completed_assessment_cannot_return_to_in_progress(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);
        $assessment->update(['status' => Assessment::STATUS_COMPLETE, 'completed_at' => now()]);

        $this->expectException(\LogicException::class);
        $assessment->update(['status' => Assessment::STATUS_IN_PROGRESS, 'completed_at' => null]);
    }

    public function test_submit_rejects_unanswered_scored_questions(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);

        $this->actingAs($user)
            ->post(route('assessments.submit', $assessment))
            ->assertRedirect(route('assessments.run', $assessment));

        $this->assertEquals('IN_PROGRESS', $assessment->fresh()->status);
        $this->assertNull($assessment->fresh()->completed_at);
    }

    public function test_submit_marks_fully_answered_assessment_complete(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$project, $target] = $this->createProjectWithTarget($workspace, $user);
        $this->seed(HivawQuestionsSeeder::class);
        $assessment = $this->createAssessment($project, $target);

        Question::where('is_active', true)
            ->where('is_scored', true)
            ->with('options')
            ->get()
            ->each(function (Question $question) use ($assessment) {
                Response::create([
                    'assessment_id' => $assessment->assessment_id,
                    'question_id' => $question->question_id,
                    'value_option_id' => $question->options->firstOrFail()->option_id,
                    'answered_at' => now(),
                ]);
            });

        $this->actingAs($user)
            ->post(route('assessments.submit', $assessment))
            ->assertRedirect(route('projects.show', $assessment->project_id));

        $this->assertEquals('COMPLETE', $assessment->fresh()->status);
        $this->assertNotNull($assessment->fresh()->completed_at);
        $reportSnapshot = $assessment->fresh()->reportSnapshot;
        $this->assertNotNull($reportSnapshot);
        $this->assertSame('vytte-report-1.0', $reportSnapshot->schema_version);
        $this->assertSame(
            $reportSnapshot->content_hash,
            hash('sha256', json_encode($reportSnapshot->payload, JSON_THROW_ON_ERROR))
        );

        $originalTitle = $reportSnapshot->payload['title'];
        AssessmentModule::where('module_code', 'HIVAW')->update(['module_name' => 'Changed after completion']);
        $this->assertSame($originalTitle, $assessment->fresh()->reportSnapshot->payload['title']);
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
