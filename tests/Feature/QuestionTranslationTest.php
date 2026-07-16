<?php

namespace Tests\Feature;

use App\Livewire\AssessmentRunner;
use App\Models\Assessment;
use App\Models\AssessmentModule;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentTier;
use App\Models\Project;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionTranslation;
use App\Models\Target;
use App\Models\TargetCategory;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\HivawQuestionsSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\TestCase;

class QuestionTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function platformAdmin(): User
    {
        $user = User::factory()->create(['platform_role' => 'PLATFORM_ADMIN', 'locale' => 'en']);
        $workspace = Workspace::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);
        app()->instance('current.workspace', $workspace);

        return $user;
    }

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create(['locale' => 'en']);
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

    // ---- Admin translation page ----

    public function test_translation_edit_page_requires_platform_admin(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user] = $this->userWithWorkspace();
        $module = AssessmentModule::where('module_code', 'HIVAW')->first();

        $this->actingAs($user)
            ->get(route('admin.modules.translations.edit', [$module, 'fr']))
            ->assertForbidden();
    }

    public function test_translation_edit_page_renders_for_platform_admin(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        $admin = $this->platformAdmin();
        $module = AssessmentModule::where('module_code', 'HIVAW')->first();

        $this->actingAs($admin)
            ->get(route('admin.modules.translations.edit', [$module, 'fr']))
            ->assertOk()
            ->assertSee('Translations')
            ->assertSee('French');
    }

    public function test_translation_edit_page_shows_english_question_text(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        $admin = $this->platformAdmin();
        $module = AssessmentModule::where('module_code', 'HIVAW')->first();
        $firstQuestion = Question::where('module_id', $module->module_id)->where('is_active', true)->first();

        $this->actingAs($admin)
            ->get(route('admin.modules.translations.edit', [$module, 'fr']))
            ->assertOk()
            ->assertSee($firstQuestion->question_text);
    }

    // ---- Saving translations ----

    public function test_saving_question_translation_creates_record(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        $admin = $this->platformAdmin();
        $module = AssessmentModule::where('module_code', 'HIVAW')->first();
        $question = Question::where('module_id', $module->module_id)->where('is_active', true)->first();

        $this->actingAs($admin)
            ->post(route('admin.modules.translations.update', [$module, 'fr']), [
                'questions' => [$question->question_id => 'Texte en français'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('question_translations', [
            'question_id' => $question->question_id,
            'locale' => 'fr',
            'question_text' => 'Texte en français',
        ]);
    }

    public function test_saving_option_translation_creates_record(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        $admin = $this->platformAdmin();
        $module = AssessmentModule::where('module_code', 'HIVAW')->first();
        $question = Question::where('module_id', $module->module_id)->where('is_active', true)->first();
        $option = QuestionOption::where('question_id', $question->question_id)->first();

        $this->actingAs($admin)
            ->post(route('admin.modules.translations.update', [$module, 'fr']), [
                'options' => [$option->option_id => 'Oui'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('question_option_translations', [
            'option_id' => $option->option_id,
            'locale' => 'fr',
            'option_label' => 'Oui',
        ]);
    }

    public function test_blank_translation_deletes_existing_record(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        $admin = $this->platformAdmin();
        $module = AssessmentModule::where('module_code', 'HIVAW')->first();
        $question = Question::where('module_id', $module->module_id)->where('is_active', true)->first();

        QuestionTranslation::create([
            'question_id' => $question->question_id,
            'locale' => 'fr',
            'question_text' => 'Texte existant',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.modules.translations.update', [$module, 'fr']), [
                'questions' => [$question->question_id => ''],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('question_translations', [
            'question_id' => $question->question_id,
            'locale' => 'fr',
        ]);
    }

    public function test_saving_translation_twice_updates_not_duplicates(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        $admin = $this->platformAdmin();
        $module = AssessmentModule::where('module_code', 'HIVAW')->first();
        $question = Question::where('module_id', $module->module_id)->where('is_active', true)->first();

        $this->actingAs($admin)->post(route('admin.modules.translations.update', [$module, 'fr']), [
            'questions' => [$question->question_id => 'Premier texte'],
        ]);
        $this->actingAs($admin)->post(route('admin.modules.translations.update', [$module, 'fr']), [
            'questions' => [$question->question_id => 'Deuxième texte'],
        ]);

        $this->assertSame(1, QuestionTranslation::where('question_id', $question->question_id)->where('locale', 'fr')->count());
        $this->assertDatabaseHas('question_translations', [
            'question_id' => $question->question_id,
            'locale' => 'fr',
            'question_text' => 'Deuxième texte',
        ]);
    }

    // ---- Runner applies translations ----

    public function test_runner_shows_translated_question_text_when_locale_is_fr(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $user->update(['locale' => 'fr']);
        App::setLocale('fr');

        $categoryId = TargetCategory::where('category_code', 'GENERAL_COMMUNITY')->value('category_id');
        $project = Project::create(['name' => 'T', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'T',
            'category_id' => $categoryId,
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $tier = AssessmentTier::where('tier_code', 'TIER_1')->first();
        $module = AssessmentModule::where('module_code', 'HIVAW')->first();
        $question = Question::where('module_id', $module->module_id)->where('is_active', true)->orderBy('display_order')->first();

        QuestionTranslation::create([
            'question_id' => $question->question_id,
            'locale' => 'fr',
            'question_text' => 'Question traduite en français',
        ]);

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

        $component = Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment]);

        $component->call('giveConsent');

        $questionTexts = collect($component->get('questionData'))->pluck('question_text');
        $this->assertContains('Question traduite en français', $questionTexts->toArray());

        App::setLocale('en');
    }

    public function test_runner_falls_back_to_english_when_no_translation_exists(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        [$user, $workspace] = $this->userWithWorkspace();
        $user->update(['locale' => 'fr']);
        App::setLocale('fr');

        $categoryId = TargetCategory::where('category_code', 'GENERAL_COMMUNITY')->value('category_id');
        $project = Project::create(['name' => 'T', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'T',
            'category_id' => $categoryId,
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $tier = AssessmentTier::where('tier_code', 'TIER_1')->first();
        $module = AssessmentModule::where('module_code', 'HIVAW')->first();
        $question = Question::where('module_id', $module->module_id)->where('is_active', true)->orderBy('display_order')->first();

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

        $component = Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment]);

        $component->call('giveConsent');

        $questionTexts = collect($component->get('questionData'))->pluck('question_text');
        $this->assertContains($question->question_text, $questionTexts->toArray());

        App::setLocale('en');
    }
}
