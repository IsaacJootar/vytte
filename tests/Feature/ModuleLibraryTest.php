<?php

namespace Tests\Feature;

use App\Models\AssessmentModule;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\PlatformGovernedDemoSeeder;
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
        $this->seed(PlatformGovernedDemoSeeder::class);

        $this->actingAs($user)
            ->get(route('modules.index'))
            ->assertOk()
            ->assertSee('Module Library');
    }

    public function test_module_library_index_shows_modules_by_target_type(): void
    {
        [$user] = $this->userWithWorkspace();
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        $this->actingAs($user)
            ->get(route('modules.index'))
            ->assertOk()
            ->assertSee('Health Facility')
            ->assertSee('DOPD')
            ->assertSee('Outpatient');
    }

    public function test_community_voice_is_not_a_separate_module_subsystem(): void
    {
        [$user] = $this->userWithWorkspace();
        $this->seed(ReferenceDataSeeder::class);

        $this->actingAs($user)
            ->get(route('modules.index'))
            ->assertOk()
            ->assertDontSee('Separate community reporting');
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
        $this->seed(PlatformGovernedDemoSeeder::class);

        $module = AssessmentModule::where('module_code', 'DOPD')->first();

        $this->actingAs($user)
            ->get(route('modules.show', $module))
            ->assertOk()
            ->assertSee('Outpatient')
            ->assertSee('DOPD')
            ->assertSee('Health Facility');
    }

    public function test_module_show_displays_question_groups_and_questions_from_governed_demo_seeder(): void
    {
        [$user] = $this->userWithWorkspace();
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        $module = AssessmentModule::where('module_code', 'DMNH')->first();

        $this->actingAs($user)
            ->get(route('modules.show', $module))
            ->assertOk()
            ->assertSee('DMNH')
            ->assertSee('DEMONSTRATION READINESS')
            ->assertSee('DMNH.DEMO.Q1');
    }

    public function test_module_show_displays_sub_indices(): void
    {
        [$user] = $this->userWithWorkspace();
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        $module = AssessmentModule::where('module_code', 'DMNH')->first();

        $this->actingAs($user)
            ->get(route('modules.show', $module))
            ->assertOk()
            ->assertSee('DMNHR')
            ->assertSee('Mental Health Readiness Score');
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
        $this->seed(PlatformGovernedDemoSeeder::class);

        $module = AssessmentModule::where('module_code', 'DMNH')->first();

        $this->assertEquals(4, $module->questions()->count());
        $this->assertEquals(1, $module->questionGroups()->count());
        $this->assertEquals(1, $module->subIndices()->count());
    }

    public function test_module_question_groups_have_questions_with_options(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        $group = QuestionGroup::where('group_label', 'DEMONSTRATION READINESS')->first();

        $this->assertNotNull($group);
        $this->assertEquals(4, $group->questions()->count());
        $this->assertTrue($group->questions->first()->options->isNotEmpty());
    }

    public function test_sub_index_uses_service_delivery_as_internal_analytical_lens(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);

        $module = AssessmentModule::where('module_code', 'DMNH')->first();
        $subIndex = $module->subIndices()->with('domain')->where('acronym', 'DMNHR')->first();

        $this->assertNotNull($subIndex);
        $this->assertNotNull($subIndex->domain);
        $this->assertEquals('SERV', $subIndex->domain->domain_code);
    }
}
