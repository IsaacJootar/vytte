<?php

namespace Tests\Feature;

use App\Models\AssessmentCatalogueRelease;
use App\Models\LocalCustomSection;
use App\Models\Project;
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
