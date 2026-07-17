<?php

namespace Tests\Feature;

use App\Models\AssessmentModule;
use App\Models\AssessmentTemplate;
use App\Models\AssessmentTemplateVersion;
use App\Models\HealthDomain;
use App\Models\Question;
use App\Models\User;
use App\Services\TemplatePublishingService;
use Database\Seeders\HivawQuestionsSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TemplatePublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(HivawQuestionsSeeder::class);
    }

    private function focusedTemplate(): array
    {
        $template = AssessmentTemplate::create([
            'template_code' => 'FOCUSED_HIV',
            'template_name' => 'Focused HIV Health Assessment',
            'creation_path' => 'FOCUSED',
            'health_domain_id' => HealthDomain::where('domain_code', 'HIV')->value('health_domain_id'),
            'source_authority' => 'Vytte curated from approved sources',
            'license_code' => 'INTERNAL-CURATED',
        ]);
        $version = AssessmentTemplateVersion::create([
            'template_id' => $template->template_id,
            'version_number' => 1,
        ]);
        $version->modules()->attach(
            AssessmentModule::where('module_code', 'HIVAW')->value('module_id'),
            ['display_order' => 1, 'is_default' => true, 'area_label' => 'HIV']
        );

        return [$template, $version];
    }

    public function test_valid_focused_template_can_be_published_with_content_hash(): void
    {
        [$template, $version] = $this->focusedTemplate();

        $published = app(TemplatePublishingService::class)->publish($version);

        $this->assertSame('PUBLISHED', $published->status);
        $this->assertSame(64, strlen($published->content_hash));
        $this->assertIsArray($published->published_payload);
        $this->assertSame(
            $published->content_hash,
            hash('sha256', json_encode($published->published_payload, JSON_THROW_ON_ERROR))
        );
        $this->assertNotNull($published->published_at);
        $this->assertSame('PUBLISHED', $template->fresh()->status);
    }

    public function test_published_template_version_is_immutable(): void
    {
        [, $version] = $this->focusedTemplate();
        $published = app(TemplatePublishingService::class)->publish($version);

        $this->expectException(\LogicException::class);
        $published->update(['version_number' => 2]);
    }

    public function test_focused_template_rejects_grouped_modules(): void
    {
        [, $version] = $this->focusedTemplate();
        $version->modules()->attach(
            AssessmentModule::where('module_code', 'MNH')->value('module_id'),
            ['display_order' => 2, 'is_default' => true]
        );

        $this->expectException(ValidationException::class);
        app(TemplatePublishingService::class)->publish($version);
    }

    public function test_template_with_unsupported_response_type_cannot_publish(): void
    {
        $module = AssessmentModule::create([
            'target_type_code' => 'COMMUNITY',
            'module_code' => 'TIME',
            'module_name' => 'Time Estimate Draft',
            'is_active' => true,
        ]);
        Question::create([
            'module_id' => $module->module_id,
            'question_code' => 'TIME.Q1',
            'question_text' => 'How long?',
            'type_id' => DB::table('question_types')->where('type_code', 'TIME_ESTIMATE')->value('type_id'),
            'display_order' => 1,
            'is_active' => true,
            'is_scored' => true,
        ]);

        $template = AssessmentTemplate::create([
            'template_code' => 'TIME_DRAFT',
            'template_name' => 'Time Estimate Draft',
            'creation_path' => 'FOCUSED',
            'health_domain_id' => HealthDomain::where('domain_code', 'GENERAL_HEALTH_SYSTEMS')->value('health_domain_id'),
            'source_authority' => 'Internal',
            'license_code' => 'INTERNAL',
        ]);
        $version = AssessmentTemplateVersion::create([
            'template_id' => $template->template_id,
            'version_number' => 1,
        ]);
        $version->modules()->attach($module->module_id);

        try {
            app(TemplatePublishingService::class)->publish($version);
            $this->fail('Publishing should reject unsupported response types.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('response_types', $exception->errors());
        }
    }

    public function test_scored_numeric_question_without_bands_cannot_publish(): void
    {
        $module = AssessmentModule::create([
            'target_type_code' => 'COMMUNITY',
            'module_code' => 'NUM',
            'module_name' => 'Numeric Draft',
            'is_active' => true,
        ]);
        Question::create([
            'module_id' => $module->module_id,
            'question_code' => 'NUM.Q1',
            'question_text' => 'What is the occupancy rate?',
            'type_id' => DB::table('question_types')->where('type_code', 'NUMERIC')->value('type_id'),
            'display_order' => 1,
            'is_active' => true,
            'is_scored' => true,
            'numeric_unit' => '%',
            'numeric_min' => 0,
            'numeric_max' => 100,
            'numeric_step' => 0.1,
        ]);
        $template = AssessmentTemplate::create([
            'template_code' => 'NUMERIC_DRAFT',
            'template_name' => 'Numeric Draft',
            'creation_path' => 'FOCUSED',
            'health_domain_id' => HealthDomain::where('domain_code', 'GENERAL_HEALTH_SYSTEMS')->value('health_domain_id'),
            'source_authority' => 'Internal',
            'license_code' => 'INTERNAL',
        ]);
        $version = AssessmentTemplateVersion::create(['template_id' => $template->template_id, 'version_number' => 1]);
        $version->modules()->attach($module->module_id);

        try {
            app(TemplatePublishingService::class)->publish($version);
            $this->fail('Publishing should require numeric scoring bands.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('scoring', $exception->errors());
        }
    }

    public function test_unscored_numeric_question_publishes_frozen_input_configuration(): void
    {
        $module = AssessmentModule::create([
            'target_type_code' => 'COMMUNITY',
            'module_code' => 'MEASURE',
            'module_name' => 'Facility Measures',
            'is_active' => true,
        ]);
        Question::create([
            'module_id' => $module->module_id,
            'question_code' => 'MEASURE.Q1',
            'question_text' => 'Average length of stay',
            'type_id' => DB::table('question_types')->where('type_code', 'NUMERIC')->value('type_id'),
            'display_order' => 1,
            'is_active' => true,
            'is_scored' => false,
            'numeric_unit' => 'days',
            'numeric_min' => 0,
            'numeric_step' => 0.1,
        ]);
        $template = AssessmentTemplate::create([
            'template_code' => 'MEASURE_TEMPLATE',
            'template_name' => 'Facility Measures',
            'creation_path' => 'FOCUSED',
            'health_domain_id' => HealthDomain::where('domain_code', 'GENERAL_HEALTH_SYSTEMS')->value('health_domain_id'),
            'source_authority' => 'Internal',
            'license_code' => 'INTERNAL',
        ]);
        $version = AssessmentTemplateVersion::create(['template_id' => $template->template_id, 'version_number' => 1]);
        $version->modules()->attach($module->module_id);

        $published = app(TemplatePublishingService::class)->publish($version);
        $question = $published->published_payload[0]['questions'][0];

        $this->assertSame('NUMERIC', $question['response_type']);
        $this->assertSame('days', $question['numeric_config']['unit']);
        $this->assertSame(0.1, $question['numeric_config']['step']);
    }

    public function test_scored_numeric_question_with_complete_bands_can_publish(): void
    {
        $module = AssessmentModule::create([
            'target_type_code' => 'COMMUNITY',
            'module_code' => 'OCCUPANCY',
            'module_name' => 'Occupancy',
            'is_active' => true,
        ]);
        $moduleDomainId = DB::table('module_domains')->insertGetId([
            'module_id' => $module->module_id,
            'domain_number' => 1,
            'domain_label' => 'Capacity',
        ]);
        $question = Question::create([
            'module_id' => $module->module_id,
            'module_domain_id' => $moduleDomainId,
            'question_number' => 1,
            'question_code' => 'OCCUPANCY.Q1',
            'question_text' => 'Average bed occupancy rate',
            'type_id' => DB::table('question_types')->where('type_code', 'NUMERIC')->value('type_id'),
            'display_order' => 1,
            'is_active' => true,
            'is_scored' => true,
            'numeric_unit' => '%',
            'numeric_min' => 0,
            'numeric_max' => 100,
            'numeric_step' => 0.1,
        ]);
        DB::table('question_numeric_bands')->insert([
            ['question_id' => $question->question_id, 'min_value' => 0, 'max_value' => 50, 'score_weight' => 0, 'band_order' => 1],
            ['question_id' => $question->question_id, 'min_value' => 50, 'max_value' => 80, 'score_weight' => 100, 'band_order' => 2],
            ['question_id' => $question->question_id, 'min_value' => 80, 'max_value' => 100, 'score_weight' => 50, 'band_order' => 3],
        ]);
        $subIndexId = DB::table('sub_indices')->insertGetId([
            'module_id' => $module->module_id,
            'domain_id' => DB::table('domains')->where('domain_code', 'CQ')->value('domain_id'),
            'acronym' => 'OCC',
            'full_name' => 'Occupancy Index',
        ]);
        DB::table('sub_index_questions')->insert([
            'sub_index_id' => $subIndexId,
            'question_id' => $question->question_id,
            'weight' => 1,
        ]);
        $template = AssessmentTemplate::create([
            'template_code' => 'OCCUPANCY_TEMPLATE',
            'template_name' => 'Occupancy',
            'creation_path' => 'FOCUSED',
            'health_domain_id' => HealthDomain::where('domain_code', 'GENERAL_HEALTH_SYSTEMS')->value('health_domain_id'),
            'source_authority' => 'Internal',
            'license_code' => 'INTERNAL',
        ]);
        $version = AssessmentTemplateVersion::create(['template_id' => $template->template_id, 'version_number' => 1]);
        $version->modules()->attach($module->module_id);

        $published = app(TemplatePublishingService::class)->publish($version);

        $this->assertSame('vytte-4.0-numeric-bands', $published->scoring_version);
        $this->assertCount(3, $published->published_payload[0]['questions'][0]['numeric_bands']);
    }

    public function test_template_without_source_and_license_cannot_publish(): void
    {
        [, $version] = $this->focusedTemplate();
        $version->template->update(['source_authority' => null, 'license_code' => null]);

        try {
            app(TemplatePublishingService::class)->publish($version);
            $this->fail('Publishing should require provenance.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('provenance', $exception->errors());
        }
    }

    public function test_curator_can_publish_through_governed_route_and_action_is_audited(): void
    {
        [, $version] = $this->focusedTemplate();
        $curator = User::factory()->create(['platform_role' => 'CURATOR']);

        $this->actingAs($curator)
            ->post(route('curation.template-versions.publish', $version))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('PUBLISHED', $version->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $curator->user_id,
            'event' => 'template.version.published',
            'auditable_id' => $version->template_version_id,
        ]);
    }

    public function test_non_curator_cannot_publish_template(): void
    {
        [, $version] = $this->focusedTemplate();
        $user = User::factory()->create(['platform_role' => null]);

        $this->actingAs($user)
            ->post(route('curation.template-versions.publish', $version))
            ->assertForbidden();

        $this->assertSame('DRAFT', $version->fresh()->status);
    }
}
