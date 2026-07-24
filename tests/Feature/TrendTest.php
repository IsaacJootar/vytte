<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAction;
use App\Models\AssessmentCatalogueRelease;
use App\Models\Project;
use App\Models\Response;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AssessmentCreationService;
use App\Services\Reporting\TrendService;
use App\Services\ScoringService;
use Database\Seeders\PlanFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrendTest extends TestCase
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
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->workspace_id,
            'user_id' => $this->user->user_id,
            'role' => 'OWNER',
        ]);
        $this->user->update(['active_workspace_id' => $this->workspace->workspace_id]);
        app()->instance('current.workspace', $this->workspace);

        $this->project = Project::create(['name' => 'Trend Project', 'owner_user_id' => $this->user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Trend Community',
            'owner_workspace_id' => $this->workspace->workspace_id,
        ]);
        $this->project->targets()->attach($target->target_id, ['added_at' => now()]);
    }

    /**
     * Run the same catalogue on the project, answered at a chosen level, completed at a
     * given date — one point on the trend line.
     */
    private function runAssessment(string $answerMode, string $completedAt): Assessment
    {
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($this->project, $release);

        $questions = collect($assessment->snapshot->payload)
            ->flatMap(fn ($module) => $module['questions'] ?? [])
            ->where('is_scored', true);
        foreach ($questions as $question) {
            $options = collect($question['options'])->whereNotNull('score_weight');
            $optionId = ($answerMode === 'worst' ? $options->sortBy('score_weight') : $options->sortByDesc('score_weight'))
                ->first()['option_id'];
            Response::updateOrCreate(
                ['assessment_id' => $assessment->assessment_id, 'question_id' => $question['question_id'], 'respondent_id' => null],
                ['value_option_id' => $optionId, 'answered_at' => now()]
            );
        }

        app(ScoringService::class)->calculate($assessment);
        $assessment->update(['status' => Assessment::STATUS_COMPLETE, 'completed_at' => $completedAt]);

        return $assessment->fresh(['snapshot', 'score']);
    }

    public function test_single_run_is_not_comparable(): void
    {
        $this->runAssessment('best', '2026-01-01');

        $summary = app(TrendService::class)->summary($this->project);

        $this->assertFalse($summary['comparable']);
        $this->assertSame(1, $summary['runs']);
    }

    public function test_improvement_between_two_runs_reads_as_upward(): void
    {
        // Worst first, then best — the score must rise.
        $this->runAssessment('worst', '2026-01-01');
        $this->runAssessment('best', '2026-02-01');

        $summary = app(TrendService::class)->summary($this->project);

        $this->assertTrue($summary['comparable']);
        $this->assertSame(2, $summary['runs']);
        $this->assertSame('UP', $summary['direction']);
        $this->assertGreaterThan(0, $summary['overall_delta']);
        $this->assertNotEmpty($summary['domain_movements']);
    }

    public function test_decline_between_two_runs_reads_as_downward(): void
    {
        $this->runAssessment('best', '2026-01-01');
        $this->runAssessment('worst', '2026-02-01');

        $summary = app(TrendService::class)->summary($this->project);

        $this->assertSame('DOWN', $summary['direction']);
        $this->assertLessThan(0, $summary['overall_delta']);
    }

    public function test_action_follow_through_counts_completion(): void
    {
        $assessment = $this->runAssessment('worst', '2026-01-01');

        AssessmentAction::factory()->create([
            'assessment_id' => $assessment->assessment_id,
            'project_id' => $this->project->project_id,
            'created_by' => $this->user->user_id,
            'status' => AssessmentAction::STATUS_VERIFIED,
        ]);
        AssessmentAction::factory()->create([
            'assessment_id' => $assessment->assessment_id,
            'project_id' => $this->project->project_id,
            'created_by' => $this->user->user_id,
            'status' => AssessmentAction::STATUS_OPEN,
        ]);

        $followThrough = app(TrendService::class)->actionFollowThrough($this->project);

        $this->assertSame(2, $followThrough['total']);
        $this->assertSame(1, $followThrough['completed']);
        $this->assertSame(50.0, $followThrough['completion_rate']);
    }

    public function test_progress_page_shows_trend_and_follow_through(): void
    {
        $this->runAssessment('worst', '2026-01-01');
        $this->runAssessment('best', '2026-02-01');

        $this->actingAs($this->user)
            ->get(route('projects.progress', $this->project))
            ->assertOk()
            ->assertSee('Trend')
            ->assertSee('Action follow-through');
    }
}
