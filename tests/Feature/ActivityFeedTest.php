<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityFeedTest extends TestCase
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

    public function test_activity_feed_shows_this_workspaces_events_in_plain_language(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        AuditLog::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'event' => 'assessment.action.updated',
            'new_values' => ['status_to' => 'DONE'],
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('activity.index'))
            ->assertOk()
            ->assertSee('moved an action to done');
    }

    public function test_activity_feed_does_not_show_another_workspaces_events(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        $other = Workspace::factory()->create();
        AuditLog::create([
            'workspace_id' => $other->workspace_id,
            'user_id' => null,
            'event' => 'assessment.completed',
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('activity.index'))
            ->assertOk()
            ->assertDontSee('completed an assessment');
    }
}
