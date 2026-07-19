<?php

namespace Tests\Feature;

use App\Models\AssessmentCatalogueRelease;
use App\Models\Project;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AssessmentCreationService;
use Database\Seeders\PlanFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanFeatureSeeder::class);
    }

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create(['locale' => 'en']);
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

    // ---- User model ----

    public function test_user_has_locale_column_defaulting_to_en(): void
    {
        $user = User::factory()->create();
        $this->assertEquals('en', $user->fresh()->locale);
    }

    // ---- Locale store endpoint ----

    public function test_locale_store_sets_session_locale(): void
    {
        [$user] = $this->userWithWorkspace();

        $this->actingAs($user)
            ->post(route('locale.store'), ['locale' => 'fr'])
            ->assertRedirect();

        $this->assertEquals('fr', session('locale'));
    }

    public function test_locale_store_updates_user_locale(): void
    {
        [$user] = $this->userWithWorkspace();

        $this->actingAs($user)
            ->post(route('locale.store'), ['locale' => 'fr'])
            ->assertRedirect();

        $this->assertEquals('fr', $user->fresh()->locale);
    }

    public function test_locale_store_rejects_unsupported_locale(): void
    {
        [$user] = $this->userWithWorkspace();

        $this->actingAs($user)
            ->post(route('locale.store'), ['locale' => 'zh'])
            ->assertRedirect();

        $this->assertEquals('en', $user->fresh()->locale);
        $this->assertEquals('en', session('locale'));
    }

    public function test_locale_store_requires_auth(): void
    {
        $this->post(route('locale.store'), ['locale' => 'fr'])
            ->assertRedirect(route('login'));
    }

    // ---- Middleware applies locale from user preference ----

    public function test_set_locale_middleware_applies_user_locale(): void
    {
        [$user] = $this->userWithWorkspace();
        $user->update(['locale' => 'fr']);

        // Hit any authenticated route so the middleware fires
        $response = $this->actingAs($user)->get(route('dashboard'));

        $this->assertEquals('fr', App::getLocale());
        $response->assertOk();
    }

    public function test_set_locale_middleware_defaults_to_en_for_unsaved_locale(): void
    {
        [$user] = $this->userWithWorkspace();
        $user->update(['locale' => 'en']);

        $this->actingAs($user)->get(route('dashboard'));

        $this->assertEquals('en', App::getLocale());
    }

    // ---- Translation keys resolve correctly ----

    public function test_english_translation_keys_resolve(): void
    {
        App::setLocale('en');

        $this->assertEquals('Previous', __('runner.previous'));
        $this->assertEquals('Next', __('runner.next'));
        $this->assertEquals('Submit Assessment', __('runner.submit'));
        $this->assertEquals('Participant Consent Required', __('runner.consent_required'));
    }

    public function test_french_translation_keys_resolve(): void
    {
        App::setLocale('fr');

        $this->assertEquals('Précédent', __('runner.previous'));
        $this->assertEquals('Suivant', __('runner.next'));
        $this->assertEquals("Soumettre l'évaluation", __('runner.submit'));
        $this->assertEquals('Consentement du participant requis', __('runner.consent_required'));

        App::setLocale('en');
    }

    public function test_parametrised_translation_key_resolves_correctly(): void
    {
        App::setLocale('en');
        $this->assertEquals('Question 3 of 10', __('runner.question_counter', ['current' => 3, 'total' => 10]));

        App::setLocale('fr');
        $this->assertEquals('Question 3 sur 10', __('runner.question_counter', ['current' => 3, 'total' => 10]));

        App::setLocale('en');
    }

    // ---- Locale switcher appears on run page ----

    public function test_locale_switcher_visible_on_assessment_run_page(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        // Seed just enough to have an assessment to run
        $project = Project::create(['name' => 'Locale Test', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'T',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release, creatorId: $user->user_id);

        $this->actingAs($user)
            ->get(route('assessments.run', $assessment))
            ->assertOk()
            ->assertSee(route('locale.store'));
    }
}
