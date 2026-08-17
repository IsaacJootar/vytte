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
use App\Services\Reporting\CustomSectionScoringService;
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

    public function test_the_author_page_renders_all_seven_answer_formats_in_plain_language(): void
    {
        [$user, $assessment] = $this->setup_assessment();

        $this->actingAs($user)->get(route('assessments.custom.edit', $assessment))
            ->assertOk()
            ->assertSee('Add local questions')
            ->assertSee('Local questions')
            ->assertSee('Yes or no')
            ->assertSee('Yes, no or not applicable')
            ->assertSee('Choose one option')
            ->assertSee('Choose all that apply')
            ->assertSee('Rating scale (1 to 5)')
            ->assertSee('Number')
            ->assertSee('Written answer')
            ->assertSee('is the better score')
            ->assertDontSee('reverse the scale');
    }

    public function test_saving_persists_each_answer_format_with_its_own_configuration(): void
    {
        [$user, $assessment] = $this->setup_assessment();

        $this->actingAs($user)->post(route('assessments.custom.save', $assessment), [
            'section_title' => 'Our extras',
            'questions' => [
                ['text' => 'Is there a working referral vehicle?', 'type' => 'YES_NO', 'good' => 'YES', 'is_scored' => '1'],
                ['text' => 'Does this apply here?', 'type' => 'YES_NO_NA', 'good' => 'YES', 'is_scored' => '1'],
                ['text' => 'Which service is available?', 'type' => 'SINGLE_SELECT', 'choices' => ['Referral', 'Ambulance', 'Referral']],
                ['text' => 'Which supplies are stocked?', 'type' => 'MULTI_SELECT', 'choices' => ['Gloves', 'Masks', 'Gowns']],
                ['text' => 'Rate the cleanliness', 'type' => 'SCALE_5', 'direction' => 'HIGHER_IS_BETTER', 'is_scored' => '1'],
                ['text' => 'How many staff are on duty?', 'type' => 'NUMERIC', 'numeric_min' => '0', 'numeric_max' => '50', 'numeric_unit' => 'staff'],
                ['text' => 'Any other observations?', 'type' => 'OPEN_ENDED'],
            ],
        ])->assertRedirect(route('assessments.custom.edit', $assessment));

        $section = LocalCustomSection::where('assessment_id', $assessment->assessment_id)->firstOrFail();
        $this->assertSame('Our extras', $section->section_title);
        $this->assertCount(7, $section->questions);

        [$yesNo, $yesNoNa, $singleSelect, $multiSelect, $scale, $numeric, $openEnded] = $section->questions;

        $this->assertSame('YES_NO', $yesNo['response_type']);
        $this->assertSame('YES', $yesNo['good_answer']);
        $this->assertTrue($yesNo['is_scored']);

        $this->assertSame('YES_NO_NA', $yesNoNa['response_type']);
        $this->assertTrue($yesNoNa['is_scored']);

        $this->assertSame('SINGLE_SELECT', $singleSelect['response_type']);
        $this->assertSame(['Referral', 'Ambulance'], $singleSelect['choices']);
        $this->assertFalse($singleSelect['is_scored']);

        $this->assertSame('MULTI_SELECT', $multiSelect['response_type']);
        $this->assertSame(['Gloves', 'Masks', 'Gowns'], $multiSelect['choices']);
        $this->assertFalse($multiSelect['is_scored']);

        $this->assertSame('SCALE_5', $scale['response_type']);
        $this->assertSame('HIGHER_IS_BETTER', $scale['score_direction']);
        $this->assertTrue($scale['is_scored']);

        $this->assertSame('NUMERIC', $numeric['response_type']);
        $this->assertEquals('0', $numeric['numeric_min']);
        $this->assertEquals('50', $numeric['numeric_max']);
        $this->assertSame('staff', $numeric['numeric_unit']);
        $this->assertFalse($numeric['is_scored']);

        $this->assertSame('OPEN_ENDED', $openEnded['response_type']);
        $this->assertFalse($openEnded['is_scored']);

        foreach ($section->questions as $question) {
            $this->assertNotEmpty($question['id']);
        }
    }

    public function test_unscorable_formats_can_never_be_forced_into_the_optional_local_score(): void
    {
        [$user, $assessment] = $this->setup_assessment();

        $this->actingAs($user)->post(route('assessments.custom.save', $assessment), [
            'questions' => [
                ['text' => 'Which service is available?', 'type' => 'SINGLE_SELECT', 'choices' => ['A', 'B'], 'is_scored' => '1'],
            ],
        ]);

        $section = LocalCustomSection::where('assessment_id', $assessment->assessment_id)->firstOrFail();
        $this->assertFalse($section->questions[0]['is_scored']);
    }

    public function test_selection_formats_require_at_least_two_unique_choices(): void
    {
        [$user, $assessment] = $this->setup_assessment();

        $this->actingAs($user)->post(route('assessments.custom.save', $assessment), [
            'questions' => [
                ['text' => 'Which service is available?', 'type' => 'SINGLE_SELECT', 'choices' => ['Only one']],
            ],
        ])->assertSessionHasErrors('questions.0.choices');

        $this->assertNull(LocalCustomSection::where('assessment_id', $assessment->assessment_id)->first());
    }

    public function test_numeric_minimum_cannot_exceed_maximum(): void
    {
        [$user, $assessment] = $this->setup_assessment();

        $this->actingAs($user)->post(route('assessments.custom.save', $assessment), [
            'questions' => [
                ['text' => 'How many?', 'type' => 'NUMERIC', 'numeric_min' => '10', 'numeric_max' => '1'],
            ],
        ])->assertSessionHasErrors('questions.0.numeric_min');
    }

    public function test_answering_scores_the_section_as_an_optional_local_score(): void
    {
        [$user, $assessment] = $this->setup_assessment();

        $this->actingAs($user)->post(route('assessments.custom.save', $assessment), [
            'questions' => [
                ['text' => 'Is there a vehicle?', 'type' => 'YES_NO', 'good' => 'YES', 'is_scored' => '1'],
                ['text' => 'Rate cleanliness', 'type' => 'SCALE_5', 'is_scored' => '1'],
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

    public function test_unscored_questions_stay_visible_and_never_distort_the_optional_local_score(): void
    {
        [$user, $assessment] = $this->setup_assessment();

        $this->actingAs($user)->post(route('assessments.custom.save', $assessment), [
            'questions' => [
                ['text' => 'Is there a vehicle?', 'type' => 'YES_NO', 'good' => 'YES', 'is_scored' => '1'],
                ['text' => 'How many staff are on duty?', 'type' => 'NUMERIC', 'numeric_min' => '0', 'numeric_max' => '50'],
            ],
        ]);

        $section = LocalCustomSection::where('assessment_id', $assessment->assessment_id)->firstOrFail();
        $scoredId = $section->questions[0]['id'];
        $unscoredId = $section->questions[1]['id'];

        $this->actingAs($user)->post(route('assessments.custom.finish', $assessment), [
            'answers' => [$scoredId => 'YES', $unscoredId => '12'],
        ]);

        $section->refresh();
        // Only the scored Yes/No question contributes, so the average is exactly its own score.
        $this->assertEquals(100.0, (float) $section->custom_score);

        $scorer = app(CustomSectionScoringService::class);
        $result = $scorer->score($section->questions, $section->answers);
        $numericRow = collect($result['questions'])->firstWhere('id', $unscoredId);
        $this->assertSame('12', $numericRow['answer']);
        $this->assertNull($numericRow['score']);
    }

    public function test_finish_normalizes_every_answer_shape_including_arrays_and_not_applicable(): void
    {
        [$user, $assessment] = $this->setup_assessment();

        $this->actingAs($user)->post(route('assessments.custom.save', $assessment), [
            'questions' => [
                ['text' => 'Does this apply?', 'type' => 'YES_NO_NA', 'good' => 'YES', 'is_scored' => '1'],
                ['text' => 'Which supplies are stocked?', 'type' => 'MULTI_SELECT', 'choices' => ['Gloves', 'Masks', 'Gowns']],
                ['text' => 'How many staff?', 'type' => 'NUMERIC', 'numeric_min' => '0', 'numeric_max' => '10'],
                ['text' => 'Notes?', 'type' => 'OPEN_ENDED'],
            ],
        ]);

        $section = LocalCustomSection::where('assessment_id', $assessment->assessment_id)->firstOrFail();
        [$naQuestion, $multiQuestion, $numericQuestion, $openQuestion] = $section->questions;

        $this->actingAs($user)->post(route('assessments.custom.finish', $assessment), [
            'answers' => [
                $naQuestion['id'] => 'NOT_APPLICABLE',
                $multiQuestion['id'] => ['Gloves', 'Gowns', 'Not-a-real-choice'],
                $numericQuestion['id'] => '99',
                $openQuestion['id'] => '  Some notes with trailing space  ',
            ],
        ]);

        $section->refresh();
        $this->assertSame('NOT_APPLICABLE', $section->answers[$naQuestion['id']]);
        $this->assertSame(['Gloves', 'Gowns'], $section->answers[$multiQuestion['id']]);
        // 99 is outside the 0-10 numeric range, so it normalizes to null and is dropped entirely.
        $this->assertArrayNotHasKey($numericQuestion['id'], $section->answers);
        $this->assertSame('Some notes with trailing space', $section->answers[$openQuestion['id']]);

        // Not-applicable is excluded from scoring rather than counted as a failure.
        $this->assertNull($section->custom_score);
    }

    public function test_submitting_with_local_questions_routes_to_the_answer_step(): void
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

        // With a local section attached, submit sends the user to the local answer step.
        $this->actingAs($user)->post(route('assessments.submit', $assessment))
            ->assertRedirect(route('assessments.custom.answer', $assessment));
        $this->assertSame(Assessment::STATUS_IN_PROGRESS, $assessment->fresh()->status);
    }

    public function test_aggregate_averages_each_respondents_local_score(): void
    {
        $scorer = new CustomSectionScoringService;
        $questions = [
            ['id' => 'q1', 'text' => 'Vehicle?', 'response_type' => 'YES_NO', 'good_answer' => 'YES'],
            ['id' => 'q2', 'text' => 'Cleanliness', 'response_type' => 'SCALE_5'],
        ];

        // R1: YES(100)+5(100)=100. R2: NO(0)+1(0)=0. R3: YES(100)+3(50)=75.
        $result = $scorer->aggregate($questions, [
            ['q1' => 'YES', 'q2' => '5'],
            ['q1' => 'NO', 'q2' => '1'],
            ['q1' => 'YES', 'q2' => '3'],
        ]);

        $this->assertEquals(58.3, $result['overall']);
        $this->assertSame(3, $result['respondents']);
        $this->assertEquals(66.7, $result['questions'][0]['score']); // q1 mean of 100,0,100
    }

    public function test_legacy_reversed_scale_questions_continue_to_render_and_score_correctly(): void
    {
        // Old data saved before `score_direction` existed used a boolean `reversed` flag.
        $scorer = new CustomSectionScoringService;
        $questions = [
            ['id' => 'q1', 'text' => 'Legacy reversed scale', 'response_type' => 'SCALE_5', 'reversed' => true],
        ];

        $result = $scorer->score($questions, ['q1' => '1']);

        // 1 is best when reversed, so it should score as 100, not 0.
        $this->assertEquals(100.0, $result['questions'][0]['score']);
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

    public function test_local_question_definitions_are_locked_when_collection_opens(): void
    {
        [$user, $assessment] = $this->setup_assessment();

        $this->actingAs($user)->post(route('assessments.custom.save', $assessment), [
            'section_title' => 'Local context',
            'questions' => [['text' => 'Original question?', 'type' => 'YES_NO', 'good' => 'YES']],
        ])->assertRedirect();

        $section = LocalCustomSection::where('assessment_id', $assessment->assessment_id)->firstOrFail();
        $assessment->markPublished($user->user_id);

        $this->actingAs($user)
            ->get(route('assessments.custom.edit', $assessment))
            ->assertRedirect(route('assessments.respondent-collection', $assessment))
            ->assertSessionHas('info', 'Local questions are locked because response collection has opened.');

        $this->actingAs($user)->post(route('assessments.custom.save', $assessment), [
            'questions' => [['text' => 'Changed question?', 'type' => 'YES_NO', 'good' => 'YES']],
        ])->assertForbidden();

        $this->expectException(\LogicException::class);
        $section->update(['section_title' => 'Changed outside the workflow']);
    }
}
