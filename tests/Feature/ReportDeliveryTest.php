<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentShareLink;
use App\Models\Project;
use App\Models\ReportSchedule;
use App\Models\Response;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Notifications\ReportSharedNotification;
use App\Services\AssessmentCreationService;
use App\Services\ScoringService;
use Database\Seeders\PlanFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReportDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanFeatureSeeder::class);

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create(['plan' => 'PRO']);
        WorkspaceMember::create(['workspace_id' => $this->workspace->workspace_id, 'user_id' => $this->user->user_id, 'role' => 'OWNER']);
        $this->user->update(['active_workspace_id' => $this->workspace->workspace_id]);
        app()->instance('current.workspace', $this->workspace);

        $this->project = Project::create(['name' => 'Delivery Project', 'owner_user_id' => $this->user->user_id]);
        $target = Target::create(['target_type_code' => 'COMMUNITY', 'name' => 'Delivery Community', 'owner_workspace_id' => $this->workspace->workspace_id]);
        $this->project->targets()->attach($target->target_id, ['added_at' => now()]);
    }

    private function completedAssessment(): Assessment
    {
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($this->project, $release);

        $questions = collect($assessment->snapshot->payload)->flatMap(fn ($m) => $m['questions'] ?? [])->where('is_scored', true);
        foreach ($questions as $question) {
            $optionId = collect($question['options'])->whereNotNull('score_weight')->sortByDesc('score_weight')->first()['option_id'];
            Response::updateOrCreate(
                ['assessment_id' => $assessment->assessment_id, 'question_id' => $question['question_id'], 'respondent_id' => null],
                ['value_option_id' => $optionId, 'answered_at' => now()]
            );
        }
        app(ScoringService::class)->calculate($assessment);
        $assessment->update(['status' => Assessment::STATUS_COMPLETE, 'completed_at' => now()]);

        return $assessment->fresh(['snapshot', 'score', 'target']);
    }

    public function test_emailing_a_report_sends_a_link_and_creates_a_share_link(): void
    {
        Notification::fake();
        $assessment = $this->completedAssessment();

        $this->actingAs($this->user)
            ->post(route('assessments.email', $assessment), ['recipient_email' => 'donor@example.com', 'message' => 'Please review'])
            ->assertRedirect();

        $this->assertSame(1, AssessmentShareLink::where('assessment_id', $assessment->assessment_id)->count());
        Notification::assertSentOnDemand(ReportSharedNotification::class, function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'donor@example.com';
        });
    }

    public function test_emailing_requires_a_completed_assessment(): void
    {
        Notification::fake();
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($this->project, $release);

        $this->actingAs($this->user)
            ->post(route('assessments.email', $assessment), ['recipient_email' => 'x@example.com'])
            ->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_a_schedule_can_be_created_and_removed(): void
    {
        $this->actingAs($this->user)
            ->post(route('report-schedules.store', $this->project), ['recipient_email' => 'ceo@example.com', 'frequency' => 'MONTHLY'])
            ->assertRedirect();

        $schedule = ReportSchedule::where('project_id', $this->project->project_id)->firstOrFail();
        $this->assertSame('ceo@example.com', $schedule->recipient_email);
        $this->assertTrue($schedule->next_run_at->isFuture());

        $this->actingAs($this->user)
            ->delete(route('report-schedules.destroy', [$this->project, $schedule]))
            ->assertRedirect();
        $this->assertSame(0, ReportSchedule::where('project_id', $this->project->project_id)->count());
    }

    public function test_the_scheduler_sends_due_reports_and_advances_the_cadence(): void
    {
        Notification::fake();
        $this->completedAssessment();

        $schedule = ReportSchedule::factory()->due()->create([
            'project_id' => $this->project->project_id,
            'recipient_email' => 'board@example.com',
            'frequency' => 'MONTHLY',
            'created_by' => $this->user->user_id,
        ]);

        $this->artisan('reports:send-scheduled')->assertSuccessful();

        Notification::assertSentOnDemand(ReportSharedNotification::class);
        $schedule->refresh();
        $this->assertNotNull($schedule->last_run_at);
        $this->assertTrue($schedule->next_run_at->isFuture());
    }

    public function test_the_scheduler_leaves_future_schedules_alone(): void
    {
        Notification::fake();
        $this->completedAssessment();

        ReportSchedule::factory()->create([
            'project_id' => $this->project->project_id,
            'recipient_email' => 'later@example.com',
            'next_run_at' => now()->addWeek(),
            'created_by' => $this->user->user_id,
        ]);

        $this->artisan('reports:send-scheduled')->assertSuccessful();

        Notification::assertNothingSent();
    }
}
