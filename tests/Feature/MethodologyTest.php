<?php

namespace Tests\Feature;

use App\Models\AnalysisLens;
use App\Models\AssessmentObjective;
use App\Models\AssessmentTemplate;
use App\Models\Domain;
use App\Models\DomainDefinition;
use App\Models\DomainTaxonomy;
use App\Models\DomainTaxonomyVersion;
use App\Models\HealthArea;
use App\Models\HealthDomain;
use App\Models\InsightCategory;
use App\Models\MethodologyVersion;
use App\Models\ObjectivePreset;
use App\Models\ObjectiveRecommendation;
use App\Models\User;
use App\Services\DomainTaxonomyPublishingService;
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
        $this->assertGreaterThanOrEqual(120, $version->healthAreas()->count());
        $this->assertGreaterThanOrEqual(15, $version->analysisLenses()->count());
        $this->assertGreaterThanOrEqual(20, $version->insightCategories()->count());
        $this->assertGreaterThanOrEqual(35, $version->templates()->count());
        $this->assertGreaterThanOrEqual(35, $version->presets()->count());
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
        // Checked against the whole health domain table rather than a hand-written list,
        // so promoting a new subject to a domain cannot silently reintroduce a collision.
        $objectives = $version->objectives()->pluck('objective_code')->all();
        $healthDomains = HealthDomain::pluck('domain_code')->all();

        $collisions = array_intersect($objectives, $healthDomains);

        $this->assertSame([], array_values($collisions),
            'These codes exist as both an objective and a health domain: '.implode(', ', $collisions)
            .'. Objectives carry purpose; subjects live in health domains.');
    }

    public function test_no_objective_mirrors_a_measurement_domain(): void
    {
        $version = $this->seedCatalogue();

        // The same rule in the other direction. "Health Workforce" is a measurement
        // dimension, not a reason to run an assessment, and carrying it as an objective
        // would put one concept in two places.
        $objectives = $version->objectives()->pluck('objective_code')->all();

        foreach ([
            'HEALTH_WORKFORCE', 'LEADERSHIP_GOVERNANCE', 'HEALTH_FINANCING',
            'HEALTH_INFORMATION', 'INFRASTRUCTURE', 'SUPPLY_CHAIN',
            'COMMUNITY_ENGAGEMENT', 'DIGITAL_HEALTH', 'HEALTH_PROMOTION',
        ] as $dimension) {
            $this->assertNotContains($dimension, $objectives,
                "{$dimension} names a subject or measurement dimension, not a purpose. It is reached through a purpose narrowed by a domain, with an objective preset as the entry point.");
        }
    }

    public function test_every_promoted_subject_is_a_health_domain(): void
    {
        $this->seedCatalogue();

        // These were absorbed as areas under General Health Systems, which mis-filed
        // every assessment about them. Malaria matters most: it is the highest disease
        // burden in the primary market.
        $codes = HealthDomain::pluck('domain_code')->all();

        foreach ([
            'MALARIA', 'NON_COMMUNICABLE_DISEASES', 'NEGLECTED_TROPICAL_DISEASES',
            'LABORATORY', 'PHARMACY', 'EMERGENCY_CARE',
        ] as $subject) {
            $this->assertContains($subject, $codes, "{$subject} must be a health domain in its own right.");
        }
    }

    public function test_general_health_systems_is_no_longer_absorbing_subjects(): void
    {
        $version = $this->seedCatalogue();

        $general = HealthDomain::where('domain_code', 'GENERAL_HEALTH_SYSTEMS')->firstOrFail();
        $areaCount = HealthArea::where('methodology_version_id', $version->methodology_version_id)
            ->where('health_domain_id', $general->health_domain_id)
            ->count();

        // A catch-all domain growing large is the signal that subjects deserving to be
        // first class are being hidden inside it.
        $this->assertLessThanOrEqual(6, $areaCount,
            'General Health Systems has grown to '.$areaCount.' areas. Something in it probably deserves to be a health domain.');
    }

    public function test_the_catalogue_removes_entries_that_were_dropped(): void
    {
        $version = $this->seedCatalogue();

        HealthArea::create([
            'methodology_version_id' => $version->methodology_version_id,
            'health_domain_id' => HealthDomain::value('health_domain_id'),
            'area_code' => 'AREA_REMOVED_FROM_CATALOGUE',
            'area_name' => 'Left over from an earlier catalogue',
        ]);

        // Without pruning the seeder could only ever add, so an entry removed from the
        // catalogue would linger in the database and still be shown to administrators.
        $this->seed(MethodologyCatalogueSeeder::class);

        $this->assertDatabaseMissing('health_areas', ['area_code' => 'AREA_REMOVED_FROM_CATALOGUE']);
    }

    public function test_data_gaps_are_a_reportable_finding(): void
    {
        $version = $this->seedCatalogue();

        // The platform already records NOT_CALIBRATED and PARTIAL on every score. Without
        // a category for it, an assessment that is largely unanswered still produces a
        // confident-looking report.
        $dataGap = InsightCategory::where('methodology_version_id', $version->methodology_version_id)
            ->where('category_code', 'DATA_GAP')->firstOrFail();

        $this->assertTrue($dataGap->is_diagnostic);
    }

    public function test_every_health_area_belongs_to_a_real_health_domain(): void
    {
        $this->seedCatalogue();

        $orphans = HealthArea::whereDoesntHave('healthDomain')->count();

        $this->assertSame(0, $orphans);
    }

    // ─── Nothing exists only because it was seeded ───────────────

    public function test_the_whole_methodology_passes_its_own_reachability_check(): void
    {
        $this->seedCatalogue();

        // The command reports a problem for anything unreachable and an advisory for
        // anything nothing routes to. Both must be clear before the master seed.
        $this->artisan('methodology:validate')
            ->expectsOutputToContain('Every entity is reachable and every reference resolves.')
            ->assertSuccessful();
    }

    public function test_every_objective_leads_somewhere(): void
    {
        $version = $this->seedCatalogue();

        $objectives = AssessmentObjective::where('methodology_version_id', $version->methodology_version_id)->get();
        $withSuggestions = ObjectiveRecommendation::pluck('assessment_objective_id')->unique();
        $inPresets = ObjectivePreset::where('methodology_version_id', $version->methodology_version_id)
            ->pluck('assessment_objective_id')->unique();

        $bare = $objectives
            ->reject(fn ($o) => $withSuggestions->contains($o->assessment_objective_id) || $inPresets->contains($o->assessment_objective_id))
            ->pluck('objective_code');

        $this->assertSame([], $bare->values()->all(),
            'These objectives suggest nothing and appear in no starting point, so choosing one leaves the user at a blank page: '.$bare->implode(', '));
    }

    public function test_every_template_is_reachable_without_browsing_the_whole_catalogue(): void
    {
        $version = $this->seedCatalogue();

        $referenced = ObjectiveRecommendation::where('recommends_type', 'TEMPLATE')->pluck('recommends_ref')
            ->merge(ObjectivePreset::where('methodology_version_id', $version->methodology_version_id)->pluck('template_code'))
            ->filter()->unique();

        $unreachable = AssessmentTemplate::where('methodology_version_id', $version->methodology_version_id)
            ->whereNotIn('template_code', $referenced)
            ->pluck('template_code');

        $this->assertSame([], $unreachable->values()->all(),
            'Nothing routes to these templates: '.$unreachable->implode(', '));
    }

    public function test_every_starting_point_resolves(): void
    {
        $version = $this->seedCatalogue();

        $domains = HealthDomain::pluck('domain_code')->all();
        $templates = AssessmentTemplate::where('methodology_version_id', $version->methodology_version_id)->pluck('template_code')->all();
        $lenses = AnalysisLens::where('methodology_version_id', $version->methodology_version_id)->pluck('lens_code')->all();

        foreach (ObjectivePreset::where('methodology_version_id', $version->methodology_version_id)->with('objective')->get() as $preset) {
            $this->assertNotNull($preset->objective, "Starting point {$preset->preset_code} has no objective.");

            foreach ($preset->health_domain_codes ?? [] as $code) {
                $this->assertContains($code, $domains, "Starting point {$preset->preset_code} names a health domain that does not exist.");
            }

            if ($preset->template_code) {
                $this->assertContains($preset->template_code, $templates, "Starting point {$preset->preset_code} names a template that does not exist.");
            }

            foreach ($preset->analysis_lens_codes ?? [] as $code) {
                $this->assertContains($code, $lenses, "Starting point {$preset->preset_code} names an analysis lens that does not exist.");
            }
        }
    }

    // ─── Measurement domain governance ───────────────────────────

    public function test_a_measurement_domain_cannot_be_left_inert(): void
    {
        // A domain with no definition in the taxonomy in force carries no scores and
        // reports nothing, while still appearing in the domain list as though it works.
        $taxonomy = DomainTaxonomy::firstOrFail();
        $publishing = app(DomainTaxonomyPublishingService::class);

        Domain::create([
            'domain_code' => 'TUND',
            'domain_name' => 'Deliberately Undefined',
            'is_operational' => false,
            'display_order' => 99,
        ]);

        $draft = DomainTaxonomyVersion::create([
            'domain_taxonomy_id' => $taxonomy->domain_taxonomy_id,
            'version_number' => 99,
            'status' => DomainTaxonomyVersion::STATUS_DRAFT,
        ]);

        DomainDefinition::create([
            'domain_taxonomy_version_id' => $draft->domain_taxonomy_version_id,
            'domain_id' => Domain::where('domain_code', 'GOV')->value('domain_id'),
            'domain_code' => 'GOV',
            'domain_name' => 'Governance',
            'definition' => 'x',
            'rationale' => 'x',
            'display_order' => 1,
        ]);

        $this->expectException(ValidationException::class);

        $publishing->publish($draft);
    }

    public function test_starting_a_new_taxonomy_version_carries_forward_and_fills_gaps(): void
    {
        $taxonomy = DomainTaxonomy::firstOrFail();
        $publishing = app(DomainTaxonomyPublishingService::class);

        Domain::create([
            'domain_code' => 'TNEW',
            'domain_name' => 'New Dimension',
            'is_operational' => false,
            'display_order' => 98,
        ]);

        $draft = $publishing->startNewVersion($taxonomy);

        // Everything the published version defined, plus a stub for the new domain, so
        // the draft is publishable rather than immediately invalid.
        $this->assertSame(Domain::count(), $draft->definitions()->count());
        $this->assertTrue($draft->definitions()->where('domain_code', 'TNEW')->exists());
        $this->assertSame(DomainTaxonomyVersion::STATUS_DRAFT, $draft->status);
    }

    public function test_publishing_a_taxonomy_version_retires_the_previous_one(): void
    {
        $taxonomy = DomainTaxonomy::firstOrFail();
        $publishing = app(DomainTaxonomyPublishingService::class);

        $before = DomainTaxonomyVersion::where('status', DomainTaxonomyVersion::STATUS_PUBLISHED)->pluck('domain_taxonomy_version_id');

        $published = $publishing->publish($publishing->startNewVersion($taxonomy));

        // Exactly one taxonomy is ever in force; two would leave new mappings free to
        // point at either with nothing saying which applied.
        $this->assertSame(1, DomainTaxonomyVersion::where('status', DomainTaxonomyVersion::STATUS_PUBLISHED)->count());
        $this->assertSame($published->domain_taxonomy_version_id,
            DomainTaxonomyVersion::where('status', DomainTaxonomyVersion::STATUS_PUBLISHED)->value('domain_taxonomy_version_id'));

        foreach ($before as $old) {
            $this->assertSame(DomainTaxonomyVersion::STATUS_SUPERSEDED,
                DomainTaxonomyVersion::find($old)->status);
        }
    }

    public function test_admin_can_publish_a_taxonomy_version_through_the_screen(): void
    {
        $taxonomy = DomainTaxonomy::firstOrFail();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.domain-taxonomies.versions.store', $taxonomy))
            ->assertRedirect();

        $draft = DomainTaxonomyVersion::where('status', DomainTaxonomyVersion::STATUS_DRAFT)->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.domain-taxonomies.publish', $draft))
            ->assertSessionHas('success');

        $this->assertSame(DomainTaxonomyVersion::STATUS_PUBLISHED, $draft->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'domain.taxonomy.published']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'domain.taxonomy.version_started']);
    }

    public function test_only_one_taxonomy_draft_at_a_time(): void
    {
        $taxonomy = DomainTaxonomy::firstOrFail();
        $publishing = app(DomainTaxonomyPublishingService::class);

        $publishing->startNewVersion($taxonomy);

        // Two open drafts would make it ambiguous which one publication should promote.
        $this->expectException(ValidationException::class);

        $publishing->startNewVersion($taxonomy);
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
