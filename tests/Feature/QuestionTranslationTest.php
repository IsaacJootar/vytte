<?php

namespace Tests\Feature;

use App\Livewire\AssessmentRunner;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\AssessmentSnapshot;
use App\Models\Project;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionTranslation;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AssessmentCreationService;
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

        [$user] = $this->userWithWorkspace();
        $module = AssessmentModule::where('module_code', 'DMNH')->first();

        $this->actingAs($user)
            ->get(route('admin.modules.translations.edit', [$module, 'fr']))
            ->assertForbidden();
    }

    public function test_translation_edit_page_renders_for_platform_admin(): void
    {

        $admin = $this->platformAdmin();
        $module = AssessmentModule::where('module_code', 'DMNH')->first();

        $this->actingAs($admin)
            ->get(route('admin.modules.translations.edit', [$module, 'fr']))
            ->assertOk()
            ->assertSee('Translations')
            ->assertSee('French');
    }

    public function test_translation_edit_page_shows_english_question_text(): void
    {

        $admin = $this->platformAdmin();
        $module = AssessmentModule::where('module_code', 'DMNH')->first();
        $firstQuestion = Question::where('module_id', $module->module_id)->where('is_active', true)->first();

        $this->actingAs($admin)
            ->get(route('admin.modules.translations.edit', [$module, 'fr']))
            ->assertOk()
            ->assertSee($firstQuestion->question_text);
    }

    // ---- Saving translations ----

    public function test_saving_question_translation_creates_record(): void
    {

        $admin = $this->platformAdmin();
        $module = AssessmentModule::where('module_code', 'DMNH')->first();
        $question = Question::where('module_id', $module->module_id)->where('is_active', true)->first();

        $this->actingAs($admin)
            ->post(route('admin.modules.translations.update', [$module, 'fr']), [
                'questions' => [$question->question_id => 'Texte en franÃ§ais'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('question_translations', [
            'question_id' => $question->question_id,
            'locale' => 'fr',
            'question_text' => 'Texte en franÃ§ais',
        ]);
    }

    public function test_saving_option_translation_creates_record(): void
    {

        $admin = $this->platformAdmin();
        $module = AssessmentModule::where('module_code', 'DMNH')->first();
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

        $admin = $this->platformAdmin();
        $module = AssessmentModule::where('module_code', 'DMNH')->first();
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

        $admin = $this->platformAdmin();
        $module = AssessmentModule::where('module_code', 'DMNH')->first();
        $question = Question::where('module_id', $module->module_id)->where('is_active', true)->first();

        $this->actingAs($admin)->post(route('admin.modules.translations.update', [$module, 'fr']), [
            'questions' => [$question->question_id => 'Premier texte'],
        ]);
        $this->actingAs($admin)->post(route('admin.modules.translations.update', [$module, 'fr']), [
            'questions' => [$question->question_id => 'DeuxiÃ¨me texte'],
        ]);

        $this->assertSame(1, QuestionTranslation::where('question_id', $question->question_id)->where('locale', 'fr')->count());
        $this->assertDatabaseHas('question_translations', [
            'question_id' => $question->question_id,
            'locale' => 'fr',
            'question_text' => 'DeuxiÃ¨me texte',
        ]);
    }

    // ---- Runner applies translations ----

    public function test_runner_shows_translated_question_text_when_locale_is_fr(): void
    {

        [$user, $workspace] = $this->userWithWorkspace();
        $user->update(['locale' => 'fr']);
        App::setLocale('fr');
        $project = Project::create(['name' => 'T', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'T',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release);
        $payload = $assessment->snapshot->payload;
        $payload[0]['questions'][0]['translations']['fr'] = 'Question traduite en franÃ§ais';

        // Assessment snapshots are immutable: replace rather than mutate.
        $snapshotAttributes = collect($assessment->snapshot->toArray())
            ->except('snapshot_id')
            ->merge(['payload' => $payload])
            ->all();
        $assessment->snapshot->delete();
        AssessmentSnapshot::create($snapshotAttributes);
        $assessment = $assessment->fresh(['snapshot']);

        $component = Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment]);

        $component->call('giveConsent');

        $questionTexts = collect($component->get('questionData'))->pluck('question_text');
        $this->assertContains('Question traduite en franÃ§ais', $questionTexts->toArray());

        App::setLocale('en');
    }

    public function test_runner_falls_back_to_english_when_no_translation_exists(): void
    {

        [$user, $workspace] = $this->userWithWorkspace();
        $user->update(['locale' => 'fr']);
        App::setLocale('fr');
        $project = Project::create(['name' => 'T', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'T',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release);
        $englishText = $assessment->snapshot->payload[0]['questions'][0]['question_text'];

        $component = Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment]);

        $component->call('giveConsent');

        $questionTexts = collect($component->get('questionData'))->pluck('question_text');
        $this->assertContains($englishText, $questionTexts->toArray());

        App::setLocale('en');
    }
}
