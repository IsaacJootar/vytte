<?php

namespace Tests\Feature;

use App\Livewire\AssessmentRunner;
use App\Models\AssessmentModule;
use App\Models\AssessmentTemplate;
use App\Models\AssessmentTemplateVersion;
use App\Models\HealthDomain;
use App\Models\Project;
use App\Models\Question;
use App\Models\Target;
use App\Models\TargetCategory;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AssessmentCreationService;
use App\Services\TemplatePublishingService;
use Database\Seeders\HivawQuestionsSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AssessmentSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);
    }

    private function projectContext(): array
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
        $this->actingAs($user);

        $project = Project::create(['name' => 'Snapshot Project', 'owner_user_id' => $user->user_id]);
        $target = Target::create([
            'owner_workspace_id' => $workspace->workspace_id,
            'target_type_code' => 'COMMUNITY',
            'category_id' => TargetCategory::where('category_code', 'GENERAL_COMMUNITY')->value('category_id'),
            'name' => 'Snapshot Community',
        ]);
        $project->targets()->attach($target->target_id, ['added_at' => now()]);

        return [$user, $project];
    }

    private function publishedFocusedVersion(): AssessmentTemplateVersion
    {
        $template = AssessmentTemplate::create([
            'template_code' => 'SNAPSHOT_HIV',
            'template_name' => 'HIV Focused Assessment',
            'creation_path' => 'FOCUSED',
            'health_domain_id' => HealthDomain::where('domain_code', 'HIV')->value('health_domain_id'),
            'source_authority' => 'Vytte approved content',
            'license_code' => 'INTERNAL-CURATED',
        ]);
        $version = AssessmentTemplateVersion::create([
            'template_id' => $template->template_id,
            'version_number' => 1,
        ]);
        $version->modules()->attach(
            AssessmentModule::where('module_code', 'HIVAW')->value('module_id'),
            ['display_order' => 1, 'area_label' => 'HIV']
        );

        return app(TemplatePublishingService::class)->publish($version);
    }

    public function test_focused_creation_materializes_scope_and_immutable_snapshot(): void
    {
        [$user, $project] = $this->projectContext();
        $version = $this->publishedFocusedVersion();

        $assessment = app(AssessmentCreationService::class)->create(
            $project,
            $version,
            creatorId: $user->user_id,
        );

        $this->assertSame('FOCUSED', $assessment->creation_path);
        $this->assertSame('FOCUSED', $assessment->scope_type);
        $this->assertSame($version->template_version_id, $assessment->template_version_id);
        $this->assertCount(1, $assessment->moduleScope->where('in_scope', true));
        $this->assertNotNull($assessment->snapshot);
        $this->assertSame($assessment->composition_hash, $assessment->snapshot->content_hash);
        $this->assertSame('HIVAW', $assessment->snapshot->payload[0]['module_code']);
    }

    public function test_snapshot_content_does_not_change_when_master_question_is_edited(): void
    {
        [$user, $project] = $this->projectContext();
        $assessment = app(AssessmentCreationService::class)->create(
            $project,
            $this->publishedFocusedVersion(),
            creatorId: $user->user_id,
        );

        $originalText = $assessment->snapshot->payload[0]['questions'][0]['question_text'];
        Question::where('question_id', $assessment->snapshot->payload[0]['questions'][0]['question_id'])
            ->update(['question_text' => 'Changed master question']);

        $this->assertSame($originalText, $assessment->snapshot->fresh()->payload[0]['questions'][0]['question_text']);
        $this->assertNotSame('Changed master question', $originalText);
    }

    public function test_template_runner_reads_frozen_snapshot_content(): void
    {
        [$user, $project] = $this->projectContext();
        $assessment = app(AssessmentCreationService::class)->create(
            $project,
            $this->publishedFocusedVersion(),
            creatorId: $user->user_id,
        );
        $firstSnapshotQuestion = $assessment->snapshot->payload[0]['questions'][0];
        Question::where('question_id', $firstSnapshotQuestion['question_id'])
            ->update(['question_text' => 'Changed after assessment creation']);

        $component = Livewire::actingAs($user)
            ->test(AssessmentRunner::class, ['assessment' => $assessment]);

        $this->assertSame(
            $firstSnapshotQuestion['question_text'],
            $component->get('questionData')[0]['question_text']
        );
    }

    public function test_unpublished_template_cannot_create_assessment(): void
    {
        [, $project] = $this->projectContext();
        $template = AssessmentTemplate::create([
            'template_code' => 'DRAFT_ONLY',
            'template_name' => 'Draft Only',
            'creation_path' => 'FOCUSED',
            'health_domain_id' => HealthDomain::where('domain_code', 'HIV')->value('health_domain_id'),
        ]);
        $version = AssessmentTemplateVersion::create([
            'template_id' => $template->template_id,
            'version_number' => 1,
        ]);

        $this->expectException(ValidationException::class);
        app(AssessmentCreationService::class)->create($project, $version);
    }
}
