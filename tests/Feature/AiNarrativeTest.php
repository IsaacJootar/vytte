<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAiNarrative;
use App\Models\AssessmentCatalogueRelease;
use App\Models\Project;
use App\Models\Response;
use App\Models\Target;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Ai\AiChatClient;
use App\Services\AssessmentCreationService;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiNarrativeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->workspace_id,
            'user_id' => $this->user->user_id,
            'role' => 'OWNER',
        ]);
        $this->user->update(['active_workspace_id' => $this->workspace->workspace_id]);
        app()->instance('current.workspace', $this->workspace);
    }

    private function configureAi(?string $key): void
    {
        config()->set('services.openai.api_key', $key);
        config()->set('services.openai.model', 'gpt-4o');
        // The client is a singleton built from config; rebuild it so the new key takes hold.
        app()->forgetInstance(AiChatClient::class);
    }

    private function completedAssessment(): Assessment
    {
        $project = Project::create(['name' => 'AI Test Project', 'owner_user_id' => $this->user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'AI Community',
            'owner_workspace_id' => $this->workspace->workspace_id,
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

    public function test_narrative_is_generated_and_stored_when_configured(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'The assessment shows serious gaps in governance. Address them first.']]],
            ]),
        ]);
        $this->configureAi('test-key');
        $assessment = $this->completedAssessment();

        $this->actingAs($this->user)
            ->post(route('assessments.narrative', $assessment), ['lens' => 'EXECUTIVE'])
            ->assertRedirect();

        $narrative = AssessmentAiNarrative::where('assessment_id', $assessment->assessment_id)->first();
        $this->assertNotNull($narrative);
        $this->assertSame('EXECUTIVE', $narrative->lens);
        $this->assertSame('gpt-4o', $narrative->model);
        $this->assertStringContainsString('governance', $narrative->body);
    }

    public function test_only_frozen_findings_are_sent_to_the_model(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Summary text.']]],
            ]),
        ]);
        $this->configureAi('test-key');
        $assessment = $this->completedAssessment();

        $this->actingAs($this->user)->post(route('assessments.narrative', $assessment), ['lens' => 'EXECUTIVE']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            // The rule must be present (system message), and only the frozen findings sent
            // (user message). The model is the configured OpenAI model.
            return str_contains($request->url(), '/v1/chat/completions')
                && $body['model'] === 'gpt-4o'
                && $body['messages'][0]['role'] === 'system'
                && str_contains($body['messages'][0]['content'], 'Never introduce a finding')
                && str_contains($body['messages'][1]['content'], 'FINDINGS:');
        });
    }

    public function test_generation_degrades_gracefully_when_not_configured(): void
    {
        $this->configureAi(null);
        $assessment = $this->completedAssessment();

        $this->actingAs($this->user)
            ->post(route('assessments.narrative', $assessment), ['lens' => 'EXECUTIVE'])
            ->assertRedirect();

        $this->assertSame(0, AssessmentAiNarrative::where('assessment_id', $assessment->assessment_id)->count());
    }

    public function test_api_failure_does_not_break_the_report(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'overloaded'], 529)]);
        $this->configureAi('test-key');
        $assessment = $this->completedAssessment();

        $this->actingAs($this->user)
            ->post(route('assessments.narrative', $assessment), ['lens' => 'EXECUTIVE'])
            ->assertRedirect();

        $this->assertSame(0, AssessmentAiNarrative::where('assessment_id', $assessment->assessment_id)->count());

        // The report itself still renders.
        $this->actingAs($this->user)
            ->get(route('assessments.results', $assessment))
            ->assertOk();
    }

    public function test_results_page_offers_generation_when_configured(): void
    {
        $this->configureAi('test-key');
        $assessment = $this->completedAssessment();

        $this->actingAs($this->user)
            ->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('Generate AI summary');
    }

    public function test_regeneration_replaces_the_existing_narrative_for_a_lens(): void
    {
        Http::fake(['api.openai.com/*' => Http::sequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'First version.']]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Second version.']]]]),
        ]);
        $this->configureAi('test-key');
        $assessment = $this->completedAssessment();

        $this->actingAs($this->user)->post(route('assessments.narrative', $assessment), ['lens' => 'EXECUTIVE']);
        $this->actingAs($this->user)->post(route('assessments.narrative', $assessment), ['lens' => 'EXECUTIVE']);

        $narratives = AssessmentAiNarrative::where('assessment_id', $assessment->assessment_id)->where('lens', 'EXECUTIVE')->get();
        $this->assertCount(1, $narratives);
        $this->assertSame('Second version.', $narratives->first()->body);
    }
}
