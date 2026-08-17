<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\Project;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AssessmentCreationService;
use App\Services\ScoringService;
use Database\Seeders\PlanFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The assessment publish → collect → close lifecycle.
 *
 * Publishing is the deliberate act that opens an assessment for responses. Until it
 * happens the assessment is a draft: it cannot generate respondent links and the public
 * runner will not accept answers. Closing ends the collection window. These are the hinges
 * the response-collection platform turns on, and before this pass none of them was wired.
 */
class AssessmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanFeatureSeeder::class);
    }

    private function ownerWithWorkspace(): array
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

    private function multiRespondentAssessment(Workspace $workspace, User $user): Assessment
    {
        $project = Project::create(['name' => 'Lagos PHC', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Test Community',
            'owner_workspace_id' => $workspace->workspace_id,
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release);

        $snapshot = $assessment->snapshot;
        $snapshot->setRawAttributes(array_merge($snapshot->getAttributes(), [
            'collection_config' => json_encode([
                'allows_multi_respondent' => true,
                'minimum_completed_respondents' => 1,
                'aggregation_method' => 'ARITHMETIC_MEAN',
                'respondent_eligibility_rules' => [],
                'scoring_profile_version' => ScoringService::ALGORITHM_VERSION,
            ]),
        ]));
        $snapshot->saveQuietly();

        return $assessment->fresh();
    }

    public function test_an_assessment_is_born_a_draft(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $assessment = $this->multiRespondentAssessment($workspace, $user);

        $this->assertTrue($assessment->isDraft());
        $this->assertFalse($assessment->isPublished());
        $this->assertFalse($assessment->isCollecting());
    }

    public function test_publishing_opens_the_assessment_for_collection(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $assessment = $this->multiRespondentAssessment($workspace, $user);

        $this->actingAs($user)
            ->post(route('assessments.publish', $assessment))
            ->assertSessionHas('success');

        $assessment->refresh();
        $this->assertTrue($assessment->isPublished());
        $this->assertTrue($assessment->isCollecting());
        $this->assertNotNull($assessment->published_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'assessment.published']);
    }

    public function test_a_draft_cannot_generate_a_respondent_link(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $assessment = $this->multiRespondentAssessment($workspace, $user);

        $this->actingAs($user)
            ->post(route('assessments.respondent-link', $assessment))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('assessment_respondent_tokens', ['assessment_id' => $assessment->assessment_id]);
    }

    public function test_a_published_assessment_can_generate_a_respondent_link(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $assessment = $this->multiRespondentAssessment($workspace, $user);
        $assessment->markPublished($user->user_id);

        $this->actingAs($user)
            ->post(route('assessments.respondent-link', $assessment))
            ->assertSessionHas('respondent_link');

        $this->assertDatabaseHas('assessment_respondent_tokens', ['assessment_id' => $assessment->assessment_id]);
    }

    public function test_opening_for_shared_responses_publishes_and_creates_the_first_link_in_one_action(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $assessment = $this->multiRespondentAssessment($workspace, $user);

        $this->actingAs($user)
            ->post(route('assessments.open-and-create-link', $assessment))
            ->assertRedirect(route('assessments.respondent-collection', $assessment))
            ->assertSessionHas('success', 'Assessment opened and respondent link created. Copy it below to start collecting responses.')
            ->assertSessionHas('respondent_link');

        $this->assertTrue($assessment->fresh()->isCollecting());
        $this->assertDatabaseCount('assessment_respondent_tokens', 1);
        $this->assertDatabaseHas('audit_logs', ['event' => 'assessment.published']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'assessment.respondent_link.created']);
    }

    public function test_opening_for_shared_responses_is_idempotent_and_reuses_the_active_link(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $assessment = $this->multiRespondentAssessment($workspace, $user);

        $this->actingAs($user)->post(route('assessments.open-and-create-link', $assessment));
        $firstLink = session('respondent_link');

        $this->actingAs($user)
            ->post(route('assessments.open-and-create-link', $assessment))
            ->assertSessionHas('respondent_link', $firstLink);

        $this->assertDatabaseCount('assessment_respondent_tokens', 1);
    }

    public function test_shared_setup_explains_the_single_open_and_create_action(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $assessment = $this->multiRespondentAssessment($workspace, $user);

        $this->actingAs($user)
            ->get(route('assessments.setup', ['assessment' => $assessment, 'step' => 4, 'mode' => 'share']))
            ->assertOk()
            ->assertSee('Open for responses & create link', false)
            ->assertSee('Opening locks the assessment questions.')
            ->assertSee(route('assessments.open-and-create-link', $assessment), false);
    }

    public function test_local_question_step_uses_one_positive_explanation(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $assessment = $this->multiRespondentAssessment($workspace, $user);

        $this->actingAs($user)
            ->get(route('assessments.setup', ['assessment' => $assessment, 'step' => 2]))
            ->assertOk()
            ->assertSee('Local to this assessment')
            ->assertSee('eligible answer types can contribute to an optional local score')
            ->assertDontSee("Vytte's official score")
            ->assertDontSee('cannot alter the published assessment score');
    }

    public function test_closing_stops_new_links_and_reopening_restores_them(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $assessment = $this->multiRespondentAssessment($workspace, $user);
        $assessment->markPublished($user->user_id);

        $this->actingAs($user)->post(route('assessments.close', $assessment))->assertSessionHas('success');
        $this->assertTrue($assessment->refresh()->isClosed());

        $this->actingAs($user)
            ->post(route('assessments.respondent-link', $assessment))
            ->assertSessionHas('error');

        $this->actingAs($user)->post(route('assessments.reopen', $assessment))->assertSessionHas('success');
        $this->assertTrue($assessment->refresh()->isCollecting());

        $this->actingAs($user)
            ->post(route('assessments.respondent-link', $assessment))
            ->assertSessionHas('respondent_link');
    }

    public function test_the_monitor_view_renders_for_a_published_assessment(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $assessment = $this->multiRespondentAssessment($workspace, $user);
        $assessment->markPublished($user->user_id);

        $this->actingAs($user)
            ->get(route('assessments.monitor', $assessment))
            ->assertOk()
            ->assertSee('Live response monitoring')
            ->assertSee('No responses yet');
    }

    public function test_the_dashboard_shows_operational_counts(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $this->multiRespondentAssessment($workspace, $user); // a draft awaiting publication

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Awaiting publication')
            ->assertSee('Collecting now')
            ->assertSee('Respondent submissions');
    }
}
