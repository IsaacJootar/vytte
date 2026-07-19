<?php

namespace Tests\Feature;

use App\Models\AssessmentModule;
use App\Models\Domain;
use App\Models\FrameworkQuestionPlacement;
use App\Models\SubIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scores are the setup surface that removes a dead end: a department with no score cannot
 * have any scored questions, and publication rejects a scored question that belongs to none.
 */
class PlatformScoresTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);
    }

    private function module(): AssessmentModule
    {
        return AssessmentModule::where('is_active', true)->orderBy('module_id')->firstOrFail();
    }

    public function test_the_page_lists_scores_and_warns_about_departments_without_one(): void
    {
        $this->actingAs($this->admin());
        SubIndex::where('module_id', $this->module()->module_id)->delete();

        $this->get(route('admin.scores.index'))
            ->assertOk()
            ->assertSee('Scores')
            ->assertSee('cannot score questions yet')
            ->assertSee('Add a score');
    }

    public function test_a_score_can_be_created_for_a_department(): void
    {
        $this->actingAs($this->admin());
        $module = $this->module();

        $this->post(route('admin.scores.store'), [
            'module_id' => $module->module_id,
            'full_name' => 'Outpatient Readiness',
            'domain_id' => Domain::orderBy('domain_id')->firstOrFail()->domain_id,
            'description' => 'How ready outpatient services are.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sub_indices', [
            'module_id' => $module->module_id,
            'full_name' => 'Outpatient Readiness',
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'platform.score.created']);
    }

    public function test_creating_a_score_requires_a_department_name_and_area(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.scores.store'), ['module_id' => '', 'full_name' => '', 'domain_id' => ''])
            ->assertSessionHasErrors(['module_id', 'full_name', 'domain_id']);
    }

    public function test_an_acronym_is_derived_rather_than_asked_for(): void
    {
        $this->actingAs($this->admin());
        $module = $this->module();
        $domainId = Domain::orderBy('domain_id')->firstOrFail()->domain_id;

        foreach (['Outpatient Readiness', 'Outpatient Reliability'] as $name) {
            $this->post(route('admin.scores.store'), [
                'module_id' => $module->module_id,
                'full_name' => $name,
                'domain_id' => $domainId,
            ])->assertSessionHasNoErrors();
        }

        $acronyms = SubIndex::where('module_id', $module->module_id)->pluck('acronym');

        $this->assertSame($acronyms->unique()->count(), $acronyms->count(), 'Derived acronyms collided.');
        $this->get(route('admin.scores.index'))->assertOk()->assertDontSee('Acronym');
    }

    public function test_a_score_in_use_cannot_be_removed(): void
    {
        $this->actingAs($this->admin());
        // Any seeded score: only the demonstration departments carry one, not every
        // active department.
        $score = SubIndex::orderBy('sub_index_id')->firstOrFail();

        $placement = FrameworkQuestionPlacement::orderBy('framework_question_placement_id')->firstOrFail();
        $placement->update(['sub_index_id' => $score->sub_index_id]);

        $this->delete(route('admin.scores.destroy', $score))->assertSessionHasErrors('score');
        $this->assertDatabaseHas('sub_indices', ['sub_index_id' => $score->sub_index_id]);
    }

    public function test_an_unused_score_can_be_removed(): void
    {
        $this->actingAs($this->admin());
        $score = SubIndex::create([
            'module_id' => $this->module()->module_id,
            'domain_id' => Domain::orderBy('domain_id')->firstOrFail()->domain_id,
            'acronym' => 'UNU',
            'full_name' => 'Unused Score',
        ]);

        $this->delete(route('admin.scores.destroy', $score))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sub_indices', ['sub_index_id' => $score->sub_index_id]);
    }

    public function test_a_workspace_user_cannot_reach_or_change_scores(): void
    {
        $score = SubIndex::orderBy('sub_index_id')->firstOrFail();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.scores.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.scores.store'), [
            'module_id' => $this->module()->module_id,
            'full_name' => 'Sneaky score',
            'domain_id' => Domain::orderBy('domain_id')->firstOrFail()->domain_id,
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.scores.destroy', $score))->assertForbidden();
    }
}
