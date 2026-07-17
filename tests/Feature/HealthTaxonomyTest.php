<?php

namespace Tests\Feature;

use App\Models\AssessmentModule;
use App\Models\HealthDomain;
use App\Models\SettingType;
use App\Models\Target;
use App\Models\TargetCategory;
use App\Models\Workspace;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_setting_taxonomy_covers_standard_and_custom_contexts(): void
    {
        $this->assertDatabaseHas('setting_types', [
            'setting_type_code' => 'HEALTH_FACILITY',
            'uses_departments' => true,
        ]);
        $this->assertDatabaseHas('setting_types', [
            'setting_type_code' => 'PLACE_OF_WORSHIP',
            'uses_departments' => false,
        ]);
        $this->assertDatabaseHas('setting_types', [
            'setting_type_code' => 'CUSTOM',
            'uses_departments' => false,
        ]);
        $this->assertSame(10, SettingType::count());
    }

    public function test_health_domains_are_distinct_from_operational_scoring_domains(): void
    {
        $this->assertDatabaseHas('health_domains', ['domain_code' => 'MENTAL_HEALTH']);
        $this->assertDatabaseHas('health_domains', ['domain_code' => 'HIV']);
        $this->assertDatabaseHas('health_domains', ['domain_code' => 'INFECTION_PREVENTION']);
        $this->assertSame(12, HealthDomain::count());
    }

    public function test_modules_can_map_to_more_than_one_health_domain(): void
    {
        $module = AssessmentModule::where('module_code', 'HTB')->firstOrFail();
        $domainCodes = HealthDomain::whereHas('modules', fn ($query) => $query->where('assessment_modules.module_id', $module->module_id))
            ->pluck('domain_code');

        $this->assertEqualsCanonicalizing(['HIV', 'TUBERCULOSIS'], $domainCodes->all());
    }

    public function test_custom_setting_preserves_user_label_without_creating_new_schema(): void
    {
        $workspace = Workspace::factory()->create();
        app()->instance('current.workspace', $workspace);

        $target = Target::create([
            'owner_workspace_id' => $workspace->workspace_id,
            'target_type_code' => 'CUSTOM',
            'category_id' => TargetCategory::where('category_code', 'GENERAL_CUSTOM')->value('category_id'),
            'name' => 'Local Cooperative',
            'custom_setting_label' => 'Agricultural Cooperative',
            'uses_departments' => false,
        ]);

        $this->assertSame('Agricultural Cooperative', $target->fresh()->custom_setting_label);
        $this->assertFalse($target->fresh()->uses_departments);
    }
}
