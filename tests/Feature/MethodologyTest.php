<?php

namespace Tests\Feature;

use App\Models\AnalysisLens;
use App\Models\AssessmentObjective;
use App\Models\HealthArea;
use App\Models\InsightCategory;
use App\Models\MethodologyVersion;
use App\Models\ObjectiveRecommendation;
use App\Models\User;
use App\Services\MethodologyPublishingService;
use Database\Seeders\MethodologyCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The methodology layer (P4).
 *
 * Two things matter most here. The layer must sit above the platform without changing
 * it, and the catalogue must be internally consistent, because a recommendation pointing
 * at something that does not exist would show a reader an empty suggestion and no reason.
 */
class MethodologyTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);
    }

    private function seedCatalogue(): MethodologyVersion
    {
        $this->seed(MethodologyCatalogueSeeder::class);

        return MethodologyVersion::orderByDesc('version_number')->firstOrFail();
    }

    // ─── The catalogue ───────────────────────────────────────────

    public function test_the_official_catalogue_seeds_a_usable_knowledge_library(): void
    {
        $version = $this->seedCatalogue();

        $this->assertGreaterThanOrEqual(25, $version->objectives()->count());
        $this->assertGreaterThanOrEqual(50, $version->healthAreas()->count());
        $this->assertGreaterThanOrEqual(15, $version->analysisLenses()->count());
        $this->assertGreaterThanOrEqual(12, $version->insightCategories()->count());
        $this->assertGreaterThanOrEqual(20, $version->templates()->count());
        $this->assertGreaterThanOrEqual(10, $version->presets()->count());
    }

    public function test_the_catalogue_is_idempotent(): void
    {
        $this->seedCatalogue();
        $before = AssessmentObjective::count();

        $this->seed(MethodologyCatalogueSeeder::class);

        $this->assertSame($before, AssessmentObjective::count());
        $this->assertSame(1, MethodologyVersion::count());
    }

    public function test_no_objective_duplicates_a_health_domain(): void
    {
        $version = $this->seedCatalogue();

        // Objectives are purposes. If a subject such as Malaria appeared as an objective
        // it would also exist as a health domain, and a user could not tell which to pick.
        $subjects = ['MALARIA', 'HIV', 'TUBERCULOSIS', 'NUTRITION', 'WASH', 'IMMUNIZATION'];
        $codes = $version->objectives()->pluck('objective_code')->all();

        foreach ($subjects as $subject) {
            $this->assertNotContains($subject, $codes,
                "{$subject} is a health domain, not an objective. Keeping subjects out of the objective catalogue is what stops the two colliding.");
        }
    }

    public function test_every_health_area_belongs_to_a_real_health_domain(): void
    {
        $this->seedCatalogue();

        $orphans = HealthArea::whereDoesntHave('healthDomain')->count();

        $this->assertSame(0, $orphans);
    }

    // ─── Publication ─────────────────────────────────────────────

    public function test_publishing_freezes_the_methodology_and_records_a_content_hash(): void
    {
        $version = $this->seedCatalogue();

        $published = app(MethodologyPublishingService::class)->publish($version);

        $this->assertSame(MethodologyVersion::STATUS_PUBLISHED, $published->status);
        $this->assertNotNull($published->content_hash);
        $this->assertNotNull($published->published_at);
    }

    public function test_a_published_methodology_cannot_be_edited(): void
    {
        $version = $this->seedCatalogue();
        app(MethodologyPublishingService::class)->publish($version);

        $this->expectException(\LogicException::class);

        $version->fresh()->update(['methodology_notes' => 'rewritten after publication']);
    }

    public function test_publication_refuses_a_recommendation_pointing_at_a_missing_lens(): void
    {
        $version = $this->seedCatalogue();
        $objective = $version->objectives()->firstOrFail();

        ObjectiveRecommendation::create([
            'assessment_objective_id' => $objective->assessment_objective_id,
            'recommends_type' => 'ANALYSIS_LENS',
            'recommends_ref' => 'LENS_THAT_DOES_NOT_EXIST',
        ]);

        $this->expectException(ValidationException::class);

        app(MethodologyPublishingService::class)->publish($version);
    }

    public function test_the_official_catalogue_publishes_without_a_broken_reference(): void
    {
        $version = $this->seedCatalogue();

        // Proves every recommendation in the curated catalogue resolves.
        $published = app(MethodologyPublishingService::class)->publish($version);

        $this->assertSame(MethodologyVersion::STATUS_PUBLISHED, $published->status);
    }

    public function test_publishing_supersedes_the_previous_version(): void
    {
        $first = $this->seedCatalogue();
        app(MethodologyPublishingService::class)->publish($first);

        $second = MethodologyVersion::create([
            'version_number' => 2,
            'status' => MethodologyVersion::STATUS_DRAFT,
        ]);
        app(MethodologyPublishingService::class)->publish($second);

        $this->assertSame(MethodologyVersion::STATUS_SUPERSEDED, $first->fresh()->status);
    }

    // ─── The layer changes nothing beneath it ────────────────────

    public function test_the_methodology_layer_adds_no_column_to_the_assessment_platform(): void
    {
        // P4 sits above the platform. If it had reached down into these tables, an
        // assessment that never references an objective would behave differently.
        foreach (['assessments', 'questions', 'question_versions', 'assessment_snapshots'] as $table) {
            $this->assertFalse(
                Schema::hasColumn($table, 'assessment_objective_id'),
                "The methodology layer must not add columns to {$table}."
            );
            $this->assertFalse(
                Schema::hasColumn($table, 'analysis_lens_id'),
                "The methodology layer must not add columns to {$table}."
            );
        }
    }

    public function test_the_retired_recommendation_tables_are_gone(): void
    {
        foreach (['recommendations', 'recommendation_rules', 'root_causes'] as $table) {
            $this->assertFalse(
                Schema::hasTable($table),
                "{$table} was retired in P4; leaving it would mean two recommendation systems with no stated authority."
            );
        }
    }

    // ─── Administration ──────────────────────────────────────────

    public function test_admin_can_browse_the_methodology(): void
    {
        $this->seedCatalogue();
        $admin = $this->makeAdmin();

        foreach ([
            route('admin.methodology.index') => 'Health Methodology',
            route('admin.methodology.objectives') => 'Assessment Objectives',
            route('admin.methodology.health-areas') => 'Health Areas',
            route('admin.methodology.lenses') => 'Analysis Lenses',
            route('admin.methodology.insight-categories') => 'Insight Categories',
            route('admin.methodology.templates') => 'Template Catalogue',
            route('admin.methodology.presets') => 'Starting Points',
        ] as $url => $text) {
            $this->actingAs($admin)->get($url)->assertOk()->assertSee($text);
        }
    }

    public function test_objectives_can_be_searched(): void
    {
        $this->seedCatalogue();

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.methodology.objectives', ['search' => 'accreditation']))
            ->assertOk()
            ->assertSee('Accreditation Readiness')
            ->assertDontSee('Health Financing');
    }

    public function test_health_areas_can_be_filtered_to_one_domain(): void
    {
        $version = $this->seedCatalogue();
        $hivArea = HealthArea::where('methodology_version_id', $version->methodology_version_id)
            ->where('area_code', 'ART')->firstOrFail();

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.methodology.health-areas', ['health_domain_id' => $hivArea->health_domain_id]))
            ->assertOk()
            ->assertSee('Antiretroviral Treatment')
            ->assertDontSee('Cold Chain and Vaccine Management');
    }

    public function test_a_customer_cannot_reach_the_methodology(): void
    {
        $this->seedCatalogue();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.methodology.index'))
            ->assertForbidden();
    }

    public function test_admin_can_publish_the_methodology_from_the_screen(): void
    {
        $version = $this->seedCatalogue();

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.methodology.publish', $version))
            ->assertSessionHas('success');

        $this->assertSame(MethodologyVersion::STATUS_PUBLISHED, $version->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'methodology.published']);
    }

    // ─── Vocabulary ──────────────────────────────────────────────

    public function test_analysis_lenses_and_measurement_domains_are_separate_concepts(): void
    {
        $version = $this->seedCatalogue();

        $lensCodes = AnalysisLens::where('methodology_version_id', $version->methodology_version_id)
            ->pluck('lens_code')->all();
        $measurementCodes = DB::table('domains')->pluck('domain_code')->all();

        // A lens holds no score; a measurement domain is what scores roll up into.
        // Executive Summary is a valid lens and could never be a measurement domain.
        $this->assertContains('EXECUTIVE', $lensCodes);
        $this->assertSame([], array_intersect($lensCodes, $measurementCodes));
    }

    public function test_pain_points_are_recorded_as_a_diagnostic_insight(): void
    {
        $version = $this->seedCatalogue();

        $painPoint = InsightCategory::where('methodology_version_id', $version->methodology_version_id)
            ->where('category_code', 'PAIN_POINT')->firstOrFail();

        $this->assertTrue($painPoint->is_diagnostic);
        $this->assertSame(InsightCategory::POLARITY_NEGATIVE, $painPoint->polarity);
    }
}
