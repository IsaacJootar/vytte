<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentModule;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentTier;
use App\Models\PlatformSetting;
use App\Models\Project;
use App\Models\Question;
use App\Models\Response;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Notifications\AssessmentCompletedNotification;
use App\Notifications\MemberJoinedNotification;
use Database\Seeders\HivawQuestionsSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
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

    private function createCompletedAssessment(Workspace $workspace, User $user): Assessment
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);
        $project = Project::create(['name' => 'Notif Test Project', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Test Community',
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
            'assessor_name' => 'Tester',
            'started_at' => now()->subHour(),
        ]);

        AssessmentModuleScope::create([
            'assessment_id' => $assessment->assessment_id,
            'module_id' => $module->module_id,
            'in_scope' => true,
            'is_category_default' => true,
            'status' => 'PENDING',
        ]);

        Question::where('module_id', $module->module_id)
            ->where('is_active', true)
            ->where('is_scored', true)
            ->with('options')
            ->get()
            ->each(function (Question $question) use ($assessment) {
                Response::create([
                    'assessment_id' => $assessment->assessment_id,
                    'question_id' => $question->question_id,
                    'value_option_id' => $question->options->firstOrFail()->option_id,
                    'answered_at' => now(),
                ]);
            });

        return $assessment;
    }

    // ---- Channel selection based on platform toggle ----

    public function test_notification_uses_only_database_channel_when_email_disabled(): void
    {
        PlatformSetting::set('email.notifications_enabled', 'false', 'boolean');

        [$owner] = $this->makeOwner();
        $assessment = new Assessment(['assessment_id' => 'test-uuid']);
        $notification = new AssessmentCompletedNotification($assessment);

        $this->assertNotContains('mail', $notification->via($owner));
        $this->assertContains('database', $notification->via($owner));
    }

    public function test_notification_adds_mail_channel_when_email_enabled(): void
    {
        PlatformSetting::set('email.notifications_enabled', 'true', 'boolean');

        [$owner] = $this->makeOwner();
        $assessment = new Assessment(['assessment_id' => 'test-uuid']);
        $notification = new AssessmentCompletedNotification($assessment);

        $this->assertContains('mail', $notification->via($owner));
        $this->assertContains('database', $notification->via($owner));
    }

    // ---- Assessment completed notification dispatch ----

    public function test_assessment_submit_creates_database_notification_for_admins(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $assessment = $this->createCompletedAssessment($workspace, $owner);

        $this->actingAs($owner)
            ->post(route('assessments.submit', $assessment))
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $owner->user_id,
            'type' => AssessmentCompletedNotification::class,
        ]);
    }

    public function test_assessment_completed_notification_not_sent_to_members(): void
    {
        [$owner, $workspace] = $this->makeOwner();

        $member = User::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $member->user_id,
            'role' => 'MEMBER',
        ]);

        $assessment = $this->createCompletedAssessment($workspace, $owner);

        $this->actingAs($owner)
            ->post(route('assessments.submit', $assessment));

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $member->user_id,
            'type' => AssessmentCompletedNotification::class,
        ]);
    }

    public function test_email_not_queued_when_platform_toggle_is_off(): void
    {
        Notification::fake();
        PlatformSetting::set('email.notifications_enabled', 'false', 'boolean');

        [$owner, $workspace] = $this->makeOwner();
        $assessment = $this->createCompletedAssessment($workspace, $owner);

        $this->actingAs($owner)
            ->post(route('assessments.submit', $assessment));

        Notification::assertSentTo($owner, AssessmentCompletedNotification::class, function ($n) {
            return ! in_array('mail', $n->via($this->makeOwner()[0]));
        });
    }

    // ---- Member joined notification ----

    public function test_member_joined_notification_sent_to_workspace_admins(): void
    {
        [$owner, $workspace] = $this->makeOwner();

        $newMember = User::factory()->create();
        $notification = new MemberJoinedNotification($newMember, $workspace);

        $owner->notify($notification);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $owner->user_id,
            'type' => MemberJoinedNotification::class,
        ]);
    }

    public function test_member_joined_notification_data_contains_expected_fields(): void
    {
        [$owner, $workspace] = $this->makeOwner();

        $newMember = User::factory()->create(['name' => 'Jane Doe']);
        $notification = new MemberJoinedNotification($newMember, $workspace);

        $data = $notification->toDatabase($owner);

        $this->assertEquals('member_joined', $data['type']);
        $this->assertStringContainsString('Jane Doe', $data['body']);
        $this->assertArrayHasKey('url', $data);
    }

    // ---- Notifications page ----

    public function test_notifications_page_requires_auth(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_notifications_page_renders(): void
    {
        [$user] = $this->makeOwner();

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Notifications');
    }

    public function test_notifications_page_shows_notification_content(): void
    {
        [$user, $workspace] = $this->makeOwner();

        $newMember = User::factory()->create(['name' => 'Alice']);
        $user->notify(new MemberJoinedNotification($newMember, $workspace));

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Alice');
    }

    // ---- Mark as read ----

    public function test_mark_as_read_redirects_to_notification_url(): void
    {
        [$user, $workspace] = $this->makeOwner();

        $newMember = User::factory()->create();
        $user->notify(new MemberJoinedNotification($newMember, $workspace));

        $notification = $user->notifications()->first();

        $this->actingAs($user)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('team.index'));

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        [$owner, $workspace] = $this->makeOwner();
        $otherUser = User::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $otherUser->user_id,
            'role' => 'MEMBER',
        ]);

        $newMember = User::factory()->create();
        $owner->notify(new MemberJoinedNotification($newMember, $workspace));
        $notification = $owner->notifications()->first();

        app()->instance('current.workspace', $workspace);
        $otherUser->update(['active_workspace_id' => $workspace->workspace_id]);

        $this->actingAs($otherUser)
            ->post(route('notifications.read', $notification->id))
            ->assertForbidden();
    }

    public function test_mark_all_read_clears_unread_count(): void
    {
        [$user, $workspace] = $this->makeOwner();

        $m1 = User::factory()->create();
        $m2 = User::factory()->create();
        $user->notify(new MemberJoinedNotification($m1, $workspace));
        $user->notify(new MemberJoinedNotification($m2, $workspace));

        $this->assertEquals(2, $user->unreadNotifications()->count());

        $this->actingAs($user)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertEquals(0, $user->fresh()->unreadNotifications()->count());
    }
}
