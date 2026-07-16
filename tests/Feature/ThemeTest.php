<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create(['theme' => 'light']);
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

    public function test_unauthenticated_user_cannot_set_theme(): void
    {
        $this->post(route('preferences.theme'), ['theme' => 'dark'])
            ->assertRedirect(route('login'));
    }

    public function test_user_can_switch_to_dark_theme(): void
    {
        [$user] = $this->userWithWorkspace();

        $this->actingAs($user)
            ->post(route('preferences.theme'), ['theme' => 'dark'])
            ->assertRedirect();

        $this->assertEquals('dark', $user->fresh()->theme);
    }

    public function test_user_can_switch_to_light_theme(): void
    {
        [$user] = $this->userWithWorkspace();
        $user->update(['theme' => 'dark']);

        $this->actingAs($user)
            ->post(route('preferences.theme'), ['theme' => 'light'])
            ->assertRedirect();

        $this->assertEquals('light', $user->fresh()->theme);
    }

    public function test_invalid_theme_value_is_rejected(): void
    {
        [$user] = $this->userWithWorkspace();

        $this->actingAs($user)
            ->post(route('preferences.theme'), ['theme' => 'purple'])
            ->assertSessionHasErrors('theme');
    }
}
