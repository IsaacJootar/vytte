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
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportExportTest extends TestCase
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

    private function completedAssessment(): Assessment
    {
        $project = Project::create(['name' => 'Export Project', 'owner_user_id' => $this->user->user_id]);
        $target = Target::create([
            'target_type_code' => 'COMMUNITY',
            'name' => 'Export Community',
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

    /**
     * docx/xlsx/pptx are all Office Open XML — ZIP containers, so a valid file starts with
     * the ZIP magic bytes "PK".
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function formats(): array
    {
        return [
            'word' => ['assessments.export.word', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'excel' => ['assessments.export.excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'ppt' => ['assessments.export.ppt', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        ];
    }

    #[DataProvider('formats')]
    public function test_export_downloads_a_valid_office_document(string $route, string $mime): void
    {
        $assessment = $this->completedAssessment();

        $response = $this->actingAs($this->user)->get(route($route, $assessment));

        $response->assertOk();
        $response->assertHeader('content-type', $mime);
        $this->assertStringStartsWith('PK', $response->getContent());
    }

    public function test_exports_require_a_completed_assessment(): void
    {
        $project = Project::create(['name' => 'Draft Project', 'owner_user_id' => $this->user->user_id]);
        $target = Target::create(['target_type_code' => 'COMMUNITY', 'name' => 'Draft', 'owner_workspace_id' => $this->workspace->workspace_id]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);
        $release = AssessmentCatalogueRelease::where('release_code', 'DEMO_MENTAL_HEALTH_FOCUSED_V1')->firstOrFail();
        $assessment = app(AssessmentCreationService::class)->createFromCatalogue($project, $release);

        $this->actingAs($this->user)
            ->get(route('assessments.export.word', $assessment))
            ->assertNotFound();
    }

    public function test_another_workspace_cannot_export(): void
    {
        $assessment = $this->completedAssessment();

        $outsider = User::factory()->create();
        $otherWorkspace = Workspace::factory()->create();
        WorkspaceMember::create(['workspace_id' => $otherWorkspace->workspace_id, 'user_id' => $outsider->user_id, 'role' => 'OWNER']);
        $outsider->update(['active_workspace_id' => $otherWorkspace->workspace_id]);
        app()->instance('current.workspace', $otherWorkspace);

        $this->actingAs($outsider)
            ->get(route('assessments.export.excel', $assessment))
            ->assertNotFound();
    }

    public function test_results_page_offers_all_export_formats(): void
    {
        $assessment = $this->completedAssessment();

        $this->actingAs($this->user)
            ->get(route('assessments.results', $assessment))
            ->assertOk()
            ->assertSee('Word')
            ->assertSee('Excel')
            ->assertSee('Slides');
    }
}
