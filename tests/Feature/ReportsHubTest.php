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
use App\Services\ReportSnapshotService;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsHubTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        WorkspaceMember::create(['workspace_id' => $this->workspace->workspace_id, 'user_id' => $this->user->user_id, 'role' => 'OWNER']);
        $this->user->update(['active_workspace_id' => $this->workspace->workspace_id]);
        app()->instance('current.workspace', $this->workspace);
    }

    /**
     * A completed, scored assessment on its own target, with the report snapshot frozen so the
     * hub can read its intelligence.
     */
    private function scoredProject(string $name, string $answerMode): Project
    {
        $project = Project::create(['name' => $name, 'owner_user_id' => $this->user->user_id]);
        $target = Target::create(['target_type_code' => 'COMMUNITY', 'name' => $name.' Target', 'owner_workspace_id' => $this->workspace->workspace_id]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release);

        $questions = collect($assessment->snapshot->payload)->flatMap(fn ($m) => $m['questions'] ?? [])->where('is_scored', true);
        foreach ($questions as $question) {
            $options = collect($question['options'])->whereNotNull('score_weight');
            $optionId = ($answerMode === 'worst' ? $options->sortBy('score_weight') : $options->sortByDesc('score_weight'))->first()['option_id'];
            Response::updateOrCreate(
                ['assessment_id' => $assessment->assessment_id, 'question_id' => $question['question_id'], 'respondent_id' => null],
                ['value_option_id' => $optionId, 'answered_at' => now()]
            );
        }

        app(ScoringService::class)->calculate($assessment);
        $assessment->update(['status' => Assessment::STATUS_COMPLETE, 'completed_at' => now()]);
        // Freeze the report so the hub reads its intelligence from the snapshot.
        app(ReportSnapshotService::class)->createFor($assessment->fresh());

        return $project;
    }

    public function test_hub_previews_report_intelligence(): void
    {
        $this->scoredProject('Alheri Clinic', 'worst'); // weak → has findings + risks

        $this->actingAs($this->user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Alheri Clinic')
            ->assertSee('Biggest issue')   // the intelligence preview on the card
            ->assertSee('Top risk');
    }
}
