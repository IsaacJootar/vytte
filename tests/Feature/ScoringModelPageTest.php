<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringModelPageTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceUser(): User
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['active_workspace_id' => $workspace->workspace_id]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
        app()->instance('current.workspace', $workspace);

        return $user;
    }

    public function test_a_regular_workspace_user_can_view_how_scoring_works(): void
    {
        $user = $this->workspaceUser();

        $this->actingAs($user)->get(route('scoring-model.index'))
            ->assertOk()
            ->assertSee('Two kinds of score, never mixed')
            ->assertSee('official score')
            ->assertSee('Your optional local score')
            ->assertSee('Yes or no')
            ->assertSee('Rating scale (1 to 5)')
            ->assertSee('Can be scored')
            ->assertSee('Context only')
            ->assertSee('Contribute Questions');
    }

    public function test_a_platform_admin_can_also_view_how_scoring_works(): void
    {
        $admin = User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);

        $this->actingAs($admin)->get(route('scoring-model.index'))
            ->assertOk()
            ->assertSee('How scoring works');
    }

    public function test_worked_examples_match_the_real_scoring_service(): void
    {
        $user = $this->workspaceUser();

        // Yes is the good answer, so it must score 100; No must score 0 (the same rule
        // CustomSectionScoringService applies everywhere else).
        $this->actingAs($user)->get(route('scoring-model.index'))
            ->assertSee('Yes → 100')
            ->assertSee('No → 0')
            ->assertSee('1 → 0')
            ->assertSee('5 → 100');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('scoring-model.index'))->assertRedirect(route('login'));
    }
}
