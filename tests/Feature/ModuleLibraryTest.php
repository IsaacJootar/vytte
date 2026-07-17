<?php

namespace Tests\Feature;

use App\Models\AssessmentModule;
use App\Models\ModuleDomain;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\HivawQuestionsSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleLibraryTest extends TestCase
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

    // ---- Auth gate ----

    public function test_module_library_index_requires_auth(): void
    {
        $this->get(route('modules.index'))->assertRedirect(route('login'));
    }

    public function test_module_show_requires_auth(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $module = AssessmentModule::first();

        $this->get(route('modules.show', $module))->assertRedirect(route('login'));
    }

    // ---- Index ----

    public function test_module_library_index_renders(): void
    {
        [$user] = $this->userWithWorkspace();
        $this->seed(ReferenceDataSeeder::class);

        $this->actingAs($user)
            ->get(route('modules.index'))
            ->assertOk()
            ->assertSee('Module Library');
    }

    public function test_module_library_index_shows_modules_by_target_type(): void
    {
        [$user] = $this->userWithWorkspace();
        $this->seed(ReferenceDataSeeder::class);

        $this->actingAs($user)
            ->get(route('modules.index'))
            ->assertOk()
            ->assertSee('Health Facility')
            ->assertSee('OPD')
            ->assertSee('Outpatient Department');
    }

    public function test_community_assessment_templates_are_not_a_separate_plan_feature(): void
    {
        [$user] = $this->userWithWorkspace();
        $this->seed(ReferenceDataSeeder::class);

        $this->actingAs($user)
            ->get(route('modules.index'))
            ->assertOk()
            ->assertSee('Community')
            ->assertSee('HIV Awareness & Service Uptake');
    }

    public function test_module_library_index_shows_empty_state_when_no_modules(): void
    {
        [$user] = $this->userWithWorkspace();

        $this->actingAs($user)
            ->get(route('modules.index'))
            ->assertOk()
            ->assertSee('No modules available');
    }

    // ---- Show ----

    public function test_module_show_renders_for_seeded_module(): void
    {
        [$user] = $this->userWithWorkspace();
        $this->seed(ReferenceDataSeeder::class);

        $module = AssessmentModule::where('module_code', 'OPD')->first();

        $this->actingAs($user)
            ->get(route('modules.show', $module))
            ->assertOk()
            ->assertSee('Outpatient Department')
            ->assertSee('OPD')
            ->assertSee('Health Facility');
    }

    public function test_module_show_displays_domains_and_questions_from_hivaw_seeder(): void
    {
        [$user] = $this->userWithWorkspace();
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        $module = AssessmentModule::where('module_code', 'HIVAW')->first();

        $this->actingAs($user)
            ->get(route('modules.show', $module))
            ->assertOk()
            ->assertSee('HIVAW')
            ->assertSee('AWARENESS & KNOWLEDGE')
            ->assertSee('STIGMA & SOCIAL NORMS')
            ->assertSee('SERVICE ACCESS & UTILIZATION')
            ->assertSee('HIVAW.D1.Q1');
    }

    public function test_module_show_displays_sub_indices(): void
    {
        [$user] = $this->userWithWorkspace();
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        $module = AssessmentModule::where('module_code', 'HIVAW')->first();

        $this->actingAs($user)
            ->get(route('modules.show', $module))
            ->assertOk()
            ->assertSee('CHKI')
            ->assertSee('Community HIV Knowledge Index')
            ->assertSee('HTAB');
    }

    public function test_module_show_returns_404_for_nonexistent_module(): void
    {
        [$user] = $this->userWithWorkspace();

        $this->actingAs($user)
            ->get(route('modules.show', 99999))
            ->assertNotFound();
    }

    // ---- Relationships ----

    public function test_assessment_module_has_correct_question_count_after_seeding(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        $module = AssessmentModule::where('module_code', 'HIVAW')->first();

        $this->assertEquals(9, $module->questions()->count());
        $this->assertEquals(3, $module->moduleDomains()->count());
        $this->assertEquals(4, $module->subIndices()->count());
    }

    public function test_module_domains_have_questions_with_options(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        $domain = ModuleDomain::where('domain_label', 'AWARENESS & KNOWLEDGE')->first();

        $this->assertNotNull($domain);
        $this->assertEquals(3, $domain->questions()->count());
        $this->assertTrue($domain->questions->first()->options->isNotEmpty());
    }

    public function test_sub_index_belongs_to_global_domain(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);

        $module = AssessmentModule::where('module_code', 'HIVAW')->first();
        $subIndex = $module->subIndices()->with('domain')->where('acronym', 'CHKI')->first();

        $this->assertNotNull($subIndex);
        $this->assertNotNull($subIndex->domain);
        $this->assertEquals('CQ', $subIndex->domain->domain_code);
    }
}
