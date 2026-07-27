<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\LocalCustomSection;
use App\Models\Project;
use App\Models\Response;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AssessmentCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomSectionTest extends TestCase
{
    use RefreshDatabase;

    private function setup_assessment(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->workspace_id, 'user_id' => $user->user_id, 'role' => 'OWNER']);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);
        app()->instance('current.workspace', $workspace);

        $project = Project::create(['name' => 'Custom Project', 'owner_user_id' => $user->user_id]);
        $target = Target::create(['target_type_code' => 'COMMUNITY', 'name' => 'Custom Target', 'owner_workspace_id' => $workspace->workspace_id]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release);

        return [$user, $assessment];
    }

    public function test_the_author_page_renders(): void
    {
        [$user, $assessment] = $this->setup_assessment();

        $this->actingAs($user)->get(route('assessments.custom.edit', $assessment))
            ->assertOk()
            ->assertSee('Add your own questions')
            ->assertSee('Tailored by your team');
    }

    public function test_saving_custom_questions_persists_them_in_their_own_lane(): void
    {
        [$user, $assessment] = $this->setup_assessment();

        $this->actingAs($user)->post(route('assessments.custom.save', $assessment), [
            'section_title' => 'Our extras',
            'questions' => [
                ['text' => 'Is there a working referral vehicle?', 'type' => 'YES_NO', 'good' => 'YES'],
                ['text' => 'Rate the cleanliness', 'type' => 'SCALE_5', 'reversed' => '0'],
            ],
        ])->assertRedirect(route('assessments.custom.edit', $assessment));

        $section = LocalCustomSection::where('assessment_id', $assessment->assessment_id)->firstOrFail();
        $this->assertSame('Our extras', $section->section_title);
        $this->assertCount(2, $section->questions);
        $this->assertSame('YES_NO', $section->questions[0]['response_type']);
        $this->assertSame('YES', $section->questions[0]['good_answer']);
        $this->assertSame('SCALE_5', $section->questions[1]['response_type']);
        $this->assertNotEmpty($section->questions[0]['id']);
    }

    public function test_answering_scores_the_section_in_its_own_0_to_100_lane(): void
    {
        [$user, $assessment] = $this->setup_assessment();

        $this->actingAs($user)->post(route('assessments.custom.save', $assessment), [
            'questions' => [
                ['text' => 'Is there a vehicle?', 'type' => 'YES_NO', 'good' => 'YES'],
                ['text' => 'Rate cleanliness', 'type' => 'SCALE_5'],
            ],
        ]);

        $section = LocalCustomSection::where('assessment_id', $assessment->assessment_id)->firstOrFail();
        $q1 = $section->questions[0]['id'];
        $q2 = $section->questions[1]['id'];

        // Yes (good) = 100; scale 3 = 50; average = 75. Finishing also completes the assessment.
        $this->actingAs($user)->post(route('assessments.custom.finish', $assessment), [
            'answers' => [$q1 => 'YES', $q2 => '3'],
        ])->assertRedirect(route('assessments.results', $assessment));

        $section->refresh();
        $this->assertEquals(75.0, (float) $section->custom_score);
        $this->assertNotNull($section->scored_at);
        $this->assertSame(Assessment::STATUS_COMPLETE, $assessment->fresh()->status);
    }

    public function test_submitting_with_tailored_questions_routes_to_the_answer_step(): void
    {
        [$user, $assessment] = $this->setup_assessment();

        // Answer the governed questions so submit passes its own check.
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

        $this->actingAs($user)->post(route('assessments.custom.save', $assessment), [
            'questions' => [['text' => 'Extra?', 'type' => 'YES_NO', 'good' => 'YES']],
        ]);

        // With a tailored section attached, submit sends the user to the tailored answer step.
        $this->actingAs($user)->post(route('assessments.submit', $assessment))
            ->assertRedirect(route('assessments.custom.answer', $assessment));
        $this->assertSame(Assessment::STATUS_IN_PROGRESS, $assessment->fresh()->status);
    }

    public function test_another_workspace_cannot_edit_the_custom_section(): void
    {
        [, $assessment] = $this->setup_assessment();

        $outsider = User::factory()->create();
        $otherWorkspace = Workspace::factory()->create();
        WorkspaceMember::create(['workspace_id' => $otherWorkspace->workspace_id, 'user_id' => $outsider->user_id, 'role' => 'OWNER']);
        $outsider->update(['active_workspace_id' => $otherWorkspace->workspace_id]);

        // Tenant scoping hides the assessment from another workspace entirely.
        $this->actingAs($outsider)->get(route('assessments.custom.edit', $assessment))->assertNotFound();
    }
}
