<?php

namespace Tests\Feature;

use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\DomainDefinition;
use App\Models\DomainTaxonomy;
use App\Models\DomainTaxonomyVersion;
use App\Models\FrameworkQuestionPlacement;
use App\Models\FrameworkQuestionPlacementDomainOverride;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\DomainTaxonomyPublishingService;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\PlatformGovernedDemoSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DomainArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PlatformGovernedDemoSeeder::class);
    }

    public function test_demo_taxonomy_is_published_without_old_phsai_domain_names(): void
    {
        $this->assertDatabaseHas('domain_taxonomies', ['taxonomy_code' => 'VYTTE_HEALTH_ANALYTICAL_DOMAINS']);
        $this->assertDatabaseHas('domain_taxonomy_versions', ['version_number' => 1, 'status' => DomainTaxonomyVersion::STATUS_PUBLISHED]);

        $names = DB::table('domains')->pluck('domain_name')->all();
        $this->assertContains('Governance and Accountability', $names);
        $this->assertNotContains('Workflow Efficiency', $names);
        $this->assertNotContains('Documentation Burden', $names);
        $this->assertNotContains('Operational Pain', $names);
    }

    public function test_published_taxonomy_version_is_immutable(): void
    {
        $version = DomainTaxonomyVersion::where('status', DomainTaxonomyVersion::STATUS_PUBLISHED)->firstOrFail();

        $this->expectException(\LogicException::class);

        $version->update(['methodology_notes' => 'Edited after publication']);
    }

    public function test_taxonomy_version_can_be_superseded_by_new_published_version(): void
    {
        $taxonomy = DomainTaxonomy::firstOrFail();
        $old = DomainTaxonomyVersion::where('status', DomainTaxonomyVersion::STATUS_PUBLISHED)->firstOrFail();
        $new = DomainTaxonomyVersion::create([
            'domain_taxonomy_id' => $taxonomy->domain_taxonomy_id,
            'version_number' => 2,
            'status' => DomainTaxonomyVersion::STATUS_DRAFT,
            'methodology_notes' => 'Demonstration supersession test.',
            'parent_version_id' => $old->domain_taxonomy_version_id,
        ]);

        foreach ($old->definitions as $definition) {
            DomainDefinition::create([
                'domain_taxonomy_version_id' => $new->domain_taxonomy_version_id,
                'domain_id' => $definition->domain_id,
                'domain_code' => $definition->domain_code,
                'domain_name' => $definition->domain_name,
                'definition' => $definition->definition,
                'rationale' => $definition->rationale,
                'display_order' => $definition->display_order,
            ]);
        }

        $publisher = app(DomainTaxonomyPublishingService::class);
        $new = $publisher->publish($new);
        $publisher->supersede($old, $new);

        $this->assertSame(DomainTaxonomyVersion::STATUS_SUPERSEDED, $old->fresh()->status);
        $this->assertSame(DomainTaxonomyVersion::STATUS_PUBLISHED, $new->fresh()->status);
    }

    public function test_indicator_mappings_are_frozen_into_published_framework_payload(): void
    {
        $framework = DepartmentFrameworkVersion::where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)->firstOrFail();
        $question = collect($framework->published_payload['questions'])->first();

        $this->assertNotEmpty($question['analytical_domains']);
        $this->assertSame('INDICATOR', $question['primary_analytical_domain']['mapping_source']);
        $this->assertNotEmpty($framework->published_payload['indicators'][0]['analytical_domains']);
    }

    public function test_placement_level_override_is_supported_without_editing_question_identity(): void
    {
        $placement = FrameworkQuestionPlacement::with('indicator.domainMappings.domainDefinition')->firstOrFail();
        $definition = DomainDefinition::where('domain_code', 'SAFE')->firstOrFail();

        FrameworkQuestionPlacementDomainOverride::create([
            'framework_question_placement_id' => $placement->framework_question_placement_id,
            'domain_definition_id' => $definition->domain_definition_id,
            'is_primary' => true,
            'contribution_weight' => 1,
            'rationale' => 'Test override.',
        ]);

        $payload = app(\App\Services\FrameworkContentService::class)->frameworkPayload($placement->frameworkVersion);
        $question = collect($payload['questions'])->firstWhere('framework_question_placement_id', $placement->framework_question_placement_id);

        $this->assertSame('SAFE', $question['primary_analytical_domain']['domain_code']);
        $this->assertSame('PLACEMENT_OVERRIDE', $question['primary_analytical_domain']['mapping_source']);
    }

    public function test_comprehensive_and_focused_content_use_applicable_domains_only(): void
    {
        $comprehensive = AssessmentCatalogueRelease::where('creation_path', 'COMPREHENSIVE')->firstOrFail();
        $focused = AssessmentCatalogueRelease::where('creation_path', 'FOCUSED')->firstOrFail();

        $comprehensiveDomains = $comprehensive->departmentFrameworkVersions
            ->flatMap(fn ($framework) => collect($framework->published_payload['questions'])->flatMap(fn ($question) => $question['analytical_domains'] ?? []))
            ->pluck('domain_code')
            ->unique()
            ->values();
        $focusedDomains = $focused->departmentFrameworkVersions
            ->flatMap(fn ($framework) => collect($framework->published_payload['questions'])->flatMap(fn ($question) => $question['analytical_domains'] ?? []))
            ->pluck('domain_code')
            ->unique()
            ->values();

        $this->assertContains('GOV', $comprehensiveDomains);
        $this->assertContains('RES', $comprehensiveDomains);
        $this->assertSame(['GOV', 'RES', 'PCOM', 'INFO'], $focusedDomains->all());
        $this->assertNotContains('WORK', $focusedDomains);
    }

    public function test_one_domain_spans_departments_and_one_department_contributes_to_multiple_domains(): void
    {
        $resourceMappings = DB::table('framework_indicator_domain_mappings as m')
            ->join('domain_definitions as d', 'd.domain_definition_id', '=', 'm.domain_definition_id')
            ->join('framework_indicators as i', 'i.framework_indicator_id', '=', 'm.framework_indicator_id')
            ->join('department_framework_versions as f', 'f.framework_version_id', '=', 'i.framework_version_id')
            ->where('d.domain_code', 'RES')
            ->distinct()
            ->count('f.module_id');

        $opdDomains = DepartmentFrameworkVersion::whereHas('module', fn ($query) => $query->where('module_code', 'DOPD'))
            ->where('framework_type', DepartmentFrameworkVersion::TYPE_DEPARTMENT)
            ->firstOrFail()
            ->published_payload['questions'];

        $this->assertGreaterThan(1, $resourceMappings);
        $this->assertGreaterThan(1, collect($opdDomains)->flatMap(fn ($question) => $question['analytical_domains'] ?? [])->pluck('domain_code')->unique()->count());
    }

    public function test_assessment_snapshot_and_report_freeze_taxonomy_version_and_domain_results(): void
    {
        $this->seed(DemoAccountSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $assessment = \App\Models\Assessment::where('status', \App\Models\Assessment::STATUS_COMPLETE)->with('snapshot', 'reportSnapshot')->firstOrFail();

        $this->assertNotEmpty($assessment->snapshot->composition_manifest['domain_taxonomy_versions']);
        $this->assertNotEmpty($assessment->reportSnapshot->payload['domain_scores']);
        $this->assertSame(
            $assessment->snapshot->composition_manifest['domain_taxonomy_versions'][0]['domain_taxonomy_content_hash'],
            $assessment->reportSnapshot->payload['domain_scores'][0]['domain_taxonomy_content_hash']
        );
    }

    public function test_local_and_workspace_custom_content_do_not_create_official_domain_scores(): void
    {
        $this->seed(DemoAccountSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $officialDomainScoreCount = DB::table('domain_scores')->count();
        $this->assertGreaterThan(0, $officialDomainScoreCount);
        $this->assertDatabaseCount('workspace_custom_assessment_designs', 0);
    }

    public function test_platform_admin_can_inspect_taxonomies_but_workspace_user_cannot(): void
    {
        $admin = User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->workspace_id,
            'user_id' => $user->user_id,
            'role' => 'OWNER',
        ]);
        $user->update(['active_workspace_id' => $workspace->workspace_id]);

        $this->actingAs($admin)
            ->get(route('admin.domain-taxonomies.index'))
            ->assertOk()
            ->assertSee('Vytte Health Analytical Domains');

        $this->actingAs($user)
            ->get(route('admin.domain-taxonomies.index'))
            ->assertForbidden();
    }

    public function test_seeded_sub_indices_use_service_delivery_lens_not_removed_phsai_code(): void
    {
        $module = AssessmentModule::where('module_code', 'DMNH')->firstOrFail();
        $subIndex = $module->subIndices()->with('domain')->firstOrFail();

        $this->assertSame('SERV', $subIndex->domain->domain_code);
        $this->assertNotSame('CQ', $subIndex->domain->domain_code);
    }
}
