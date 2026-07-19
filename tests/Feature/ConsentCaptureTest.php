<?php

namespace Tests\Feature;

use App\Livewire\AssessmentRunner;
use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\FacilityProfile;
use App\Models\Project;
use App\Models\RespondentConsent;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AssessmentCreationService;
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

    private function createConsentAssessment(Workspace $workspace, User $user): Assessment
    {
        $project = Project::create(['name' => 'Consent Test Project', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Test Community',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();

        return app(AssessmentCreationService::class)->createFromCatalogue($project, $release)->fresh(['snapshot']);
    }

    private function createInternalModuleAssessment(Workspace $workspace, User $user): Assessment
    {
        $project = Project::create(['name' => 'Internal Test Project', 'owner_user_id' => $user->user_id]);
        $profile = FacilityProfile::where('profile_code', 'CLINIC')->firstOrFail();
        $target = Target::create([
            'target_type_code' => 'HEALTH_FACILITY',
            'facility_profile_id' => $profile->facility_profile_id,
            'name' => 'Test PHC',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_CLINIC_COMPREHENSIVE_V1')->firstOrFail();

        return app(AssessmentCreationService::class)->createFromCatalogue($project, $release)->fresh(['snapshot']);
    }

    // ---- Governed consent metadata ----

    public function test_mental_health_module_requires_consent(): void
    {
        $module = AssessmentModule::where('module_code', 'DMNH')->first();

        $this->assertTrue($module->requires_consent);
    }

    public function test_outpatient_module_does_not_require_consent(): void
    {
        $module = AssessmentModule::where('module_code', 'DOPD')->first();

        $this->assertFalse($module->requires_consent);
    }

    // ---- Livewire consent state ----

    public function test_runner_sets_needs_consent_true_for_focused_consent_assessment(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createConsentAssessment($workspace, $user);

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

    public function test_select_option_blocked_without_required_consent(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createConsentAssessment($workspace, $user);

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
        $assessment = $this->createConsentAssessment($workspace, $user);

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
        $assessment = $this->createConsentAssessment($workspace, $user);

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
        $assessment = $this->createConsentAssessment($workspace, $user);

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
        $assessment = $this->createConsentAssessment($workspace, $user);
        $module = AssessmentModule::where('module_code', 'DMNH')->first();

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
        $assessment = $this->createConsentAssessment($workspace, $user);
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
        $assessment = $this->createConsentAssessment($workspace, $user);

        $component = Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment]);

        $component->call('giveConsent');
        $component->call('giveConsent');

        $this->assertDatabaseCount('respondent_consents', 1);
    }
}
