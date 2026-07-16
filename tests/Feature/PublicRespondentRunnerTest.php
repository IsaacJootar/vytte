<?php

namespace Tests\Feature;

use App\Livewire\PublicRespondentRunner;
use App\Models\Assessment;
use App\Models\AssessmentModule;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentRespondentToken;
use App\Models\AssessmentTier;
use App\Models\Project;
use App\Models\Question;
use App\Models\QuestionTranslation;
use App\Models\RespondentConsent;
use App\Models\Target;
use App\Models\TargetCategory;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\HivawQuestionsSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PublicRespondentRunnerTest extends TestCase
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
        $project = Project::create(['name' => 'Test Project', 'owner_user_id' => $user->user_id]);
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

    private function createToken(Assessment $assessment): string
    {
        $token = Str::random(32);
        AssessmentRespondentToken::create([
            'token' => $token,
            'assessment_id' => $assessment->assessment_id,
        ]);

        return $token;
    }

    // ---- Token validation ----

    public function test_invalid_token_sets_token_valid_false(): void
    {
        Livewire::test(PublicRespondentRunner::class, ['token' => 'badtoken123'])
            ->assertSet('tokenValid', false);
    }

    public function test_valid_token_sets_token_valid_true(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);
        $token = $this->createToken($assessment);

        Livewire::test(PublicRespondentRunner::class, ['token' => $token])
            ->assertSet('tokenValid', true);
    }

    // ---- Language selection ----

    public function test_no_translations_skips_language_selection(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);
        $token = $this->createToken($assessment);

        Livewire::test(PublicRespondentRunner::class, ['token' => $token])
            ->assertSet('languageChosen', true)
            ->assertSet('currentLocale', 'en');
    }

    public function test_with_translations_shows_language_selection(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);
        $token = $this->createToken($assessment);

        $moduleId = AssessmentModule::where('module_code', 'HIVAW')->value('module_id');
        $questionId = Question::where('module_id', $moduleId)->value('question_id');

        QuestionTranslation::create([
            'question_id' => $questionId,
            'locale' => 'fr',
            'question_text' => 'Question en français',
        ]);

        Livewire::test(PublicRespondentRunner::class, ['token' => $token])
            ->assertSet('languageChosen', false)
            ->assertCount('availableLocales', 2);
    }

    public function test_select_locale_stores_choice_and_loads_questions(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);
        $token = $this->createToken($assessment);

        $moduleId = AssessmentModule::where('module_code', 'HIVAW')->value('module_id');
        $questionId = Question::where('module_id', $moduleId)->value('question_id');

        QuestionTranslation::create([
            'question_id' => $questionId,
            'locale' => 'fr',
            'question_text' => 'Question en français',
        ]);

        $component = Livewire::test(PublicRespondentRunner::class, ['token' => $token]);

        // Give consent first — HIVAW requires it before questions load
        $component->call('giveConsent');
        $component->call('selectLocale', 'fr');

        $component->assertSet('currentLocale', 'fr');
        $component->assertSet('languageChosen', true);
        $this->assertNotEmpty($component->get('questionData'));
    }

    // ---- Consent ----

    public function test_hivaw_requires_consent_for_public_runner(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);
        $token = $this->createToken($assessment);

        Livewire::test(PublicRespondentRunner::class, ['token' => $token])
            ->assertSet('needsConsent', true)
            ->assertSet('consentGiven', false);
    }

    public function test_select_option_blocked_without_consent(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);
        $token = $this->createToken($assessment);

        $component = Livewire::test(PublicRespondentRunner::class, ['token' => $token]);
        $respondentId = $component->get('respondentId');

        $moduleId = AssessmentModule::where('module_code', 'HIVAW')->value('module_id');
        $questionId = Question::where('module_id', $moduleId)->value('question_id');

        $component->call('selectOption', $questionId, 1);

        $this->assertDatabaseMissing('responses', [
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $questionId,
            'respondent_id' => $respondentId,
        ]);
    }

    public function test_give_consent_creates_record_with_respondent_session_id(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);
        $token = $this->createToken($assessment);

        $component = Livewire::test(PublicRespondentRunner::class, ['token' => $token]);
        $respondentId = $component->get('respondentId');

        $component->call('giveConsent');
        $component->assertSet('consentGiven', true);

        $this->assertDatabaseHas('respondent_consents', [
            'assessment_id' => $assessment->assessment_id,
            'respondent_session_id' => $respondentId,
        ]);

        $consent = RespondentConsent::where('assessment_id', $assessment->assessment_id)->first();
        $this->assertNull($consent->consented_by);
    }

    public function test_select_option_saves_response_with_respondent_id(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);
        $token = $this->createToken($assessment);

        $component = Livewire::test(PublicRespondentRunner::class, ['token' => $token]);
        $component->call('giveConsent');

        $firstQuestion = $component->get('questionData')[0];
        $firstOptionId = $firstQuestion['options'][0]['option_id'];
        $respondentId = $component->get('respondentId');

        $component->call('selectOption', $firstQuestion['question_id'], $firstOptionId);

        $this->assertDatabaseHas('responses', [
            'assessment_id' => $assessment->assessment_id,
            'question_id' => $firstQuestion['question_id'],
            'value_option_id' => $firstOptionId,
            'respondent_id' => $respondentId,
        ]);
    }

    // ---- Submit ----

    public function test_submit_marks_component_as_submitted(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);
        $token = $this->createToken($assessment);

        $component = Livewire::test(PublicRespondentRunner::class, ['token' => $token]);
        $component->call('giveConsent');

        $questionData = $component->get('questionData');
        foreach ($questionData as $q) {
            if ($q['is_scored']) {
                $component->call('selectOption', $q['question_id'], $q['options'][0]['option_id']);
            }
        }

        $component->call('submit');
        $component->assertSet('isSubmitted', true);
    }

    // ---- Closed assessment ----

    public function test_closed_assessment_shows_closed_state(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);
        $assessment->update(['status' => 'COMPLETE', 'completed_at' => now()]);
        $token = $this->createToken($assessment);

        Livewire::test(PublicRespondentRunner::class, ['token' => $token])
            ->assertSet('assessmentClosed', true);
    }

    // ---- RespondentLinkController ----

    public function test_respondent_link_endpoint_requires_auth(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);

        $this->post(route('assessments.respondent-link', $assessment))
            ->assertRedirect(route('login'));
    }

    public function test_respondent_link_creates_token_and_flashes_url(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->createHivawAssessment($workspace, $user);

        $this->actingAs($user)
            ->post(route('assessments.respondent-link', $assessment))
            ->assertRedirect()
            ->assertSessionHas('respondent_link');

        $this->assertDatabaseCount('assessment_respondent_tokens', 1);
        $token = AssessmentRespondentToken::first();
        $this->assertEquals($assessment->assessment_id, $token->assessment_id);
    }
}
