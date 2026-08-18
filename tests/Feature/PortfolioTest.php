<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\Project;
use App\Models\Response;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AssessmentCreationService;
use App\Services\Reporting\PortfolioService;
use App\Services\ReportSnapshotService;
use App\Services\ScoringService;
use Database\Seeders\PlanFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PortfolioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanFeatureSeeder::class);
    }

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
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

    private function completedProject(User $user, Workspace $workspace, string $name): Project
    {
        $project = Project::create(['name' => $name, 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => $name.' Target',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release);
        $questions = collect($assessment->snapshot->payload)
            ->flatMap(fn ($module) => $module['questions'] ?? [])
            ->where('is_scored', true);
        foreach ($questions as $question) {
            $optionId = collect($question['options'])->sortByDesc('score_weight')->first()['option_id'];
            Response::updateOrCreate(
                ['assessment_id' => $assessment->assessment_id, 'question_id' => $question['question_id'], 'respondent_id' => null],
                ['value_option_id' => $optionId, 'answered_at' => now()]
            );
        }
        app(ScoringService::class)->calculate($assessment);
        $assessment->update(['status' => Assessment::STATUS_COMPLETE, 'completed_at' => now()]);
        app(ReportSnapshotService::class)->createFor($assessment->fresh());

        return $project;
    }

    /**
     * A second (or later) run on an existing project, at a chosen answer level and date — a
     * point on that setting's compatible series. Freezes a real report snapshot, matching
     * App\Actions\CompleteSelfAssessment, so Progress/Domain/Issue rollups have real data to
     * read.
     */
    private function runOnProject(Project $project, string $answerMode, string $completedAt): Assessment
    {
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release);
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
        app(ReportSnapshotService::class)->createFor($assessment->fresh());

        return $assessment->fresh(['snapshot', 'reportSnapshot', 'score']);
    }

    public function test_portfolio_requires_auth(): void
    {
        $this->get(route('portfolio.index'))->assertRedirect(route('login'));
    }

    public function test_portfolio_shows_empty_state_without_completed_assessments(): void
    {
        [$user] = $this->userWithWorkspace();

        $this->actingAs($user)->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('No completed results yet');
    }

    public function test_overall_results_groups_only_like_for_like_completed_assessments(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $this->completedProject($user, $workspace, 'Alpha Clinic');
        $this->completedProject($user, $workspace, 'Beta Clinic');

        $this->actingAs($user)->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Assessment Portfolio')
            ->assertSee('Group average')
            ->assertSee('same assessment and scoring rules')
            ->assertSee('Alpha Clinic')
            ->assertSee('Beta Clinic')
            ->assertDontSee('Program score')
            ->assertDontSee('maturity ladder');
    }

    public function test_unlike_assessments_are_never_averaged_or_ranked_together(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $first = $this->completedProject($user, $workspace, 'Alpha Clinic');
        $second = $this->completedProject($user, $workspace, 'Beta Clinic');
        $assessmentIds = [$first->assessments()->firstOrFail()->assessment_id, $second->assessments()->firstOrFail()->assessment_id];

        // ComparisonSeriesService reads the report snapshot's signature first (it is the
        // authoritative frozen copy once a report exists), falling back to the assessment
        // snapshot only when no report snapshot exists — so both must be overridden here.
        DB::table('assessment_snapshots')->where('assessment_id', $assessmentIds[0])->update(['comparison_signature' => str_repeat('a', 64)]);
        DB::table('assessment_snapshots')->where('assessment_id', $assessmentIds[1])->update(['comparison_signature' => str_repeat('b', 64)]);
        DB::table('assessment_report_snapshots')->where('assessment_id', $assessmentIds[0])->update(['comparison_signature' => str_repeat('a', 64)]);
        DB::table('assessment_report_snapshots')->where('assessment_id', $assessmentIds[1])->update(['comparison_signature' => str_repeat('b', 64)]);

        $this->actingAs($user)->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Shown on its own', false)
            ->assertDontSee('Group average')
            ->assertDontSee('compared with this group');
    }

    public function test_portfolio_only_shows_current_workspace(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $this->completedProject($user, $workspace, 'Mine Clinic');

        [$otherUser, $otherWorkspace] = $this->userWithWorkspace();
        $this->completedProject($otherUser, $otherWorkspace, 'Their Clinic');

        // Acting as the second user, only their workspace's target should appear.
        $this->actingAs($otherUser)->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Their Clinic')
            ->assertDontSee('Mine Clinic');

        $summary = app(PortfolioService::class)->build();
        $this->assertSame(1, $summary['headline']['total_settings']);
        $this->assertSame(1, $summary['headline']['assessed_settings']);
        $this->assertSame(['COMMUNITY' => 1], $summary['coverage']['by_type']);
        $this->assertCount(1, $summary['comparison_groups']);
        $this->assertSame('Their Clinic Target', $summary['comparison_groups'][0]['rows'][0]['setting_name']);
    }

    public function test_a_setting_with_a_repeated_compatible_run_appears_as_progress(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $project = Project::create(['name' => 'Improving Clinic', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Improving Clinic Target',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        // Worst, then best — the setting genuinely improves.
        $this->runOnProject($project, 'worst', '2026-01-01');
        $this->runOnProject($project, 'best', '2026-02-01');

        $summary = app(PortfolioService::class)->build();

        $this->assertSame(1, $summary['headline']['repeated_compatible_assessments']);
        $this->assertSame(1, $summary['headline']['improving_settings']);
        $this->assertSame(0, $summary['headline']['declining_settings']);
        $this->assertCount(1, $summary['progress']);
        $this->assertSame('UP', $summary['progress'][0]['direction']);

        // Resolved issues and no not-comparable issues: same compatible series throughout.
        $this->assertGreaterThan(0, $summary['issue_movement']['resolved']);
        $this->assertSame(0, $summary['issue_movement']['not_comparable']);
        $this->assertSame(1, $summary['issue_movement']['tracked_settings']);

        // At least one domain should read as improving.
        $this->assertNotEmpty($summary['domain_trends']);
        $this->assertTrue(collect($summary['domain_trends'])->contains(fn ($d) => $d['improving'] > 0));

        $this->actingAs($user)->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Progress over time')
            ->assertSee('Domain trends')
            ->assertSee('Issue movement');
    }

    public function test_coverage_flags_unassessed_and_baseline_only_settings(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        // A project with no assessment at all — genuinely uncovered.
        $unassessedTarget = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Waiting Clinic Target',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $unassessed = Project::create(['name' => 'Waiting Clinic', 'owner_user_id' => $user->user_id]);
        $unassessed->targets()->attach($unassessedTarget->target_id, ['added_at' => now()]);

        // A project with exactly one completed run — a baseline, not enough for a trend yet.
        $this->completedProject($user, $workspace, 'Baseline Clinic');

        $summary = app(PortfolioService::class)->build();

        $this->assertCount(1, $summary['coverage']['not_yet_assessed']);
        $this->assertSame('Waiting Clinic', $summary['coverage']['not_yet_assessed'][0]['name']);
        $this->assertSame(1, $summary['coverage']['insufficient_history']);

        $this->actingAs($user)->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Where to focus next')
            ->assertSee('Waiting Clinic')
            ->assertSee('only a baseline result');
    }
}
