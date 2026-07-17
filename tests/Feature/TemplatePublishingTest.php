<?php

namespace Tests\Feature;

use App\Models\AssessmentModule;
use App\Models\AssessmentTemplate;
use App\Models\AssessmentTemplateVersion;
use App\Models\HealthDomain;
use App\Models\Question;
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
            'module_code' => 'NUMERIC',
            'module_name' => 'Numeric Draft',
            'is_active' => true,
        ]);
        Question::create([
            'module_id' => $module->module_id,
            'question_code' => 'NUMERIC.Q1',
            'question_text' => 'How many?',
            'type_id' => DB::table('question_types')->where('type_code', 'NUMERIC')->value('type_id'),
            'display_order' => 1,
            'is_active' => true,
            'is_scored' => true,
        ]);

        $template = AssessmentTemplate::create([
            'template_code' => 'NUMERIC_DRAFT',
            'template_name' => 'Numeric Draft',
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
            $this->assertArrayHasKey('scoring', $exception->errors());
        }
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
}
