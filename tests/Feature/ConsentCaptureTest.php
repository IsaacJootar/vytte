<?php

namespace Tests\Feature;

use App\Livewire\AssessmentRunner;
use App\Models\Assessment;
use App\Models\AssessmentModule;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentTier;
use App\Models\Project;
use App\Models\RespondentConsent;
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

class ConsentCaptureTest extends TestCase
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

    private function createHivawAssessment(Workspace $workspace, User $user): Assessment
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        $categoryId = TargetCategory::where('category_code', 'GENERAL_COMMUNITY')->value('category_id');
        $project = Project::create(['name' => 'Consent Test Project', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Test Community',
            'category_id' => $categoryId,
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

    private function createInternalModuleAssessment(Workspace $workspace, User $user): Assessment
    {
        $this->seed(ReferenceDataSeeder::class);

        $categoryId = TargetCategory::where('category_code', 'PHC')->value('category_id');
        $project = Project::create(['name' => 'Internal Test Project', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'HEALTH_FACILITY',
            'name' => 'Test PHC',
            'category_id' => $categoryId,
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $tier = AssessmentTier::where('tier_code', 'TIER_1')->first();
        $module = AssessmentModule::where('module_code', 'OPD')->first();

        $assessment = Assessment::create([
            'target_id' => $target->target_id,
            'project_id' => $project->project_id,
            'assessment_tier_id' => $tier->assessment_tier_id,
            'status' => 'IN_PROGRESS',
            'publish_status' => 'DRAFT',
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

    // ---- HIVAW requires consent ----

    public function test_hivaw_module_requires_consent(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $module = AssessmentModule::where('module_code', 'HIVAW')->first();

        $this->assertTrue($module->requires_consent);
    }

    public function test_opd_module_does_not_require_consent(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $module = AssessmentModule::where('module_code', 'OPD')->first();

        $this->assertFalse($module->requires_consent);
    }

    // ---- Livewire consent state ----

    public function test_runner_sets_needs_consent_true_for_hivaw(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);

        Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment])
            ->assertSet('needsConsent', true)
            ->assertSet('consentGiven', false);
    }

    public function test_runner_sets_needs_consent_false_for_internal_module(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createInternalModuleAssessment($workspace, $user);

        Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment])
            ->assertSet('needsConsent', false);
    }

    // ---- Consent blocks responses ----

    public function test_select_option_blocked_without_consent_for_hivaw(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);

        $component = Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment]);

        $firstQuestion = $component->get('questionData')[0];
        $firstOptionId = $firstQuestion['options'][0]['option_id'];

        $component->call('selectOption', $firstQuestion['question_id'], $firstOptionId);

        $this->assertDatabaseMissing('responses', [
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $firstQuestion['question_id'],
        ]);
    }

    // ---- Give consent ----

    public function test_give_consent_creates_consent_record(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);

        Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment])
            ->call('giveConsent')
            ->assertSet('consentGiven', true);

        $this->assertDatabaseHas('respondent_consents', [
            'assessment_id' => $assessment->assessment_id,
            'consented_by' => $user->user_id,
        ]);
    }

    public function test_give_consent_records_consent_text(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);

        Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment])
            ->call('giveConsent');

        $consent = RespondentConsent::where('assessment_id', $assessment->assessment_id)->first();
        $this->assertNotNull($consent);
        $this->assertEquals(AssessmentRunner::CONSENT_TEXT, $consent->consent_text);
    }

    // ---- Responses allowed after consent ----

    public function test_select_option_allowed_after_consent(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);

        $component = Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment]);

        $component->call('giveConsent');

        $firstQuestion = $component->get('questionData')[0];
        $firstOptionId = $firstQuestion['options'][0]['option_id'];

        $component->call('selectOption', $firstQuestion['question_id'], $firstOptionId);

        $this->assertDatabaseHas('responses', [
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $firstQuestion['question_id'],
            'value_option_id' => $firstOptionId,
        ]);
    }

    // ---- Consent persists across reload ----

    public function test_consent_given_flag_restored_on_remount_if_record_exists(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);
        $module = AssessmentModule::where('module_code', 'HIVAW')->first();

        RespondentConsent::create([
            'assessment_id' => $assessment->assessment_id,
            'module_id' => $module->module_id,
            'consent_text' => AssessmentRunner::CONSENT_TEXT,
            'consented_by' => $user->user_id,
            'consented_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment])
            ->assertSet('needsConsent', true)
            ->assertSet('consentGiven', true);
    }

    // ---- Consent not shown for complete assessment ----

    public function test_give_consent_ignored_for_complete_assessment(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);
        $assessment->update(['status' => 'COMPLETE', 'completed_at' => now()]);

        Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment])
            ->call('giveConsent');

        $this->assertDatabaseMissing('respondent_consents', [
            'assessment_id' => $assessment->assessment_id,
        ]);
    }

    // ---- give_consent idempotent ----

    public function test_give_consent_is_idempotent(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);

        $component = Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment]);

        $component->call('giveConsent');
        $component->call('giveConsent');

        $this->assertDatabaseCount('respondent_consents', 1);
    }
}
