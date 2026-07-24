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
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionManagementTest extends TestCase
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

    /**
     * A completed assessment answered at the worst level, so it produces weaknesses — and
     * therefore recommendations to draw actions from.
     */
    private function weakCompletedAssessment(Workspace $workspace, User $user): Assessment
    {
        $project = Project::create(['name' => 'Action Test Project', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Test Community',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release);

        $questions = collect($assessment->snapshot->payload)
            ->flatMap(fn ($module) => $module['questions'] ?? [])
            ->where('is_scored', true);
        foreach ($questions as $question) {
            $optionId = collect($question['options'])->whereNotNull('score_weight')
                ->sortBy('score_weight')->first()['option_id'];
            Response::updateOrCreate(
                ['assessment_id' => $assessment->assessment_id, 'question_id' => $question['question_id'], 'respondent_id' => null],
                ['value_option_id' => $optionId, 'answered_at' => now()]
            );
        }

        app(ScoringService::class)->calculate($assessment);
        $assessment->update(['status' => Assessment::STATUS_COMPLETE, 'completed_at' => now()]);

        return $assessment->fresh(['snapshot', 'score']);
    }

    public function test_recommendation_can_be_added_to_the_action_plan(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->weakCompletedAssessment($workspace, $user);

        $this->actingAs($user)
            ->post(route('actions.store', $assessment), ['recommendation_index' => 0])
            ->assertRedirect();

        $action = AssessmentAction::where('assessment_id', $assessment->assessment_id)->first();
        $this->assertNotNull($action);
        $this->assertSame(AssessmentAction::STATUS_OPEN, $action->status);
        // The citation must be carried over from the recommendation's finding.
        $this->assertNotEmpty($action->source_finding_statement);
        $this->assertSame($workspace->workspace_id, $action->workspace_id);
    }

    public function test_storing_an_out_of_range_recommendation_creates_nothing(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->weakCompletedAssessment($workspace, $user);

        $this->actingAs($user)
            ->post(route('actions.store', $assessment), ['recommendation_index' => 999])
            ->assertRedirect();

        $this->assertSame(0, AssessmentAction::where('assessment_id', $assessment->assessment_id)->count());
    }

    public function test_action_can_be_moved_through_its_lifecycle_and_records_history(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->weakCompletedAssessment($workspace, $user);
        $this->actingAs($user)->post(route('actions.store', $assessment), ['recommendation_index' => 0]);
        $action = AssessmentAction::where('assessment_id', $assessment->assessment_id)->firstOrFail();

        $this->actingAs($user)
            ->patch(route('actions.update', $action), ['status' => 'IN_PROGRESS', 'note' => 'Started work'])
            ->assertRedirect();

        $action->refresh();
        $this->assertSame('IN_PROGRESS', $action->status);
        $update = $action->updates()->first();
        $this->assertSame('OPEN', $update->status_from);
        $this->assertSame('IN_PROGRESS', $update->status_to);
        $this->assertSame('Started work', $update->note);
    }

    public function test_verifying_an_action_stamps_who_and_when(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->weakCompletedAssessment($workspace, $user);
        $this->actingAs($user)->post(route('actions.store', $assessment), ['recommendation_index' => 0]);
        $action = AssessmentAction::where('assessment_id', $assessment->assessment_id)->firstOrFail();

        $this->actingAs($user)->patch(route('actions.update', $action), ['status' => 'VERIFIED']);

        $action->refresh();
        $this->assertTrue($action->isVerified());
        $this->assertSame($user->user_id, $action->verified_by);
        $this->assertNotNull($action->verified_at);
    }

    public function test_action_plan_lists_actions_for_the_project(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->weakCompletedAssessment($workspace, $user);
        $this->actingAs($user)->post(route('actions.store', $assessment), ['recommendation_index' => 0]);

        $this->actingAs($user)
            ->get(route('actions.index', $assessment->project_id))
            ->assertOk()
            ->assertSee('Action plan')
            ->assertSee('Because:');
    }

    public function test_owner_from_another_workspace_is_rejected(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $assessment = $this->weakCompletedAssessment($workspace, $user);
        $outsider = User::factory()->create();

        $this->actingAs($user)->post(route('actions.store', $assessment), [
            'recommendation_index' => 0,
            'owner_user_id' => $outsider->user_id,
        ]);

        $action = AssessmentAction::where('assessment_id', $assessment->assessment_id)->firstOrFail();
        // A non-member may not own an action; the assignment is dropped, not honoured.
        $this->assertNull($action->owner_user_id);
    }

    public function test_workspace_b_cannot_see_workspace_a_actions(): void
    {
        [$userA, $workspaceA] = $this->userWithWorkspace();
        $assessment = $this->weakCompletedAssessment($workspaceA, $userA);
        $this->actingAs($userA)->post(route('actions.store', $assessment), ['recommendation_index' => 0]);
        $projectId = $assessment->project_id;

        $userB = User::factory()->create();
        $workspaceB = Workspace::factory()->create();
        WorkspaceMember::create(['workspace_id' => $workspaceB->workspace_id, 'user_id' => $userB->user_id, 'role' => 'OWNER']);
        $userB->update(['active_workspace_id' => $workspaceB->workspace_id]);
        app()->instance('current.workspace', $workspaceB);

        $this->actingAs($userB)
            ->get(route('actions.index', $projectId))
            ->assertNotFound();
    }
}
