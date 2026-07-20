<?php

namespace App\Console\Commands;

use App\Models\AnalysisLens;
use App\Models\AssessmentObjective;
use App\Models\AssessmentTemplate;
use App\Models\Domain;
use App\Models\DomainTaxonomyVersion;
use App\Models\HealthArea;
use App\Models\HealthDomain;
use App\Models\InsightCategory;
use App\Models\ObjectivePreset;
use App\Models\ObjectiveRecommendation;
use App\Services\MethodologyPublishingService;
use Illuminate\Console\Command;

/**
 * Checks that nothing in the methodology exists only because it was seeded.
 *
 * Every entity must be reachable: directly selectable by someone, recommended through a
 * relationship, or a participant in the diagnostics pipeline. An entry that is none of
 * those is dead weight that an administrator will eventually find, fail to understand,
 * and not trust.
 *
 * Run before the master seed, and after any catalogue change.
 */
class ValidateMethodology extends Command
{
    protected $signature = 'methodology:validate';

    protected $description = 'Check every methodology entity is reachable and every reference resolves';

    /** @var array<int, string> */
    private array $problems = [];

    /** @var array<int, string> */
    private array $warnings = [];

    public function handle(): int
    {
        $version = MethodologyPublishingService::currentVersion();

        if (! $version) {
            $this->error('No methodology version exists. Run MethodologyCatalogueSeeder first.');

            return self::FAILURE;
        }

        $this->info('Validating methodology version '.$version->version_number.' ('.$version->status.')');
        $this->newLine();

        $this->checkMeasurementDomains();
        $this->checkHealthDomains($version->methodology_version_id);
        $this->checkHealthAreas($version->methodology_version_id);
        $this->checkObjectives($version->methodology_version_id);
        $this->checkLenses($version->methodology_version_id);
        $this->checkTemplates($version->methodology_version_id);
        $this->checkInsightCategories($version->methodology_version_id);
        $this->checkPresets($version->methodology_version_id);
        $this->checkRecommendations($version->methodology_version_id);

        foreach ($this->warnings as $warning) {
            $this->warn('  ~ '.$warning);
        }

        foreach ($this->problems as $problem) {
            $this->error('  x '.$problem);
        }

        $this->newLine();

        if ($this->problems !== []) {
            $this->error(count($this->problems).' problem(s) found. The methodology is not ready to seed.');

            return self::FAILURE;
        }

        $this->info('Every entity is reachable and every reference resolves.'
            .($this->warnings === [] ? '' : ' '.count($this->warnings).' advisory note(s) above.'));

        return self::SUCCESS;
    }

    /**
     * A measurement domain with no definition in the taxonomy in force carries no scores
     * and appears in no report, while still looking active in the domain list.
     */
    private function checkMeasurementDomains(): void
    {
        $inForce = DomainTaxonomyVersion::where('status', DomainTaxonomyVersion::STATUS_PUBLISHED)
            ->orderByDesc('version_number')
            ->with('definitions')
            ->first();

        if (! $inForce) {
            $this->problems[] = 'No published measurement domain taxonomy. Nothing would roll up into a domain score.';

            return;
        }

        $undefined = Domain::whereNotIn('domain_id', $inForce->definitions->pluck('domain_id'))->pluck('domain_code');

        foreach ($undefined as $code) {
            $this->problems[] = "Measurement domain {$code} is not defined in the taxonomy in force, so it carries no scores.";
        }

        $this->line('  Measurement domains: '.Domain::count().' defined in taxonomy v'.$inForce->version_number);
    }

    private function checkHealthDomains(string $versionId): void
    {
        $withoutAreas = HealthDomain::whereNotIn(
            'health_domain_id',
            HealthArea::where('methodology_version_id', $versionId)->pluck('health_domain_id')
        )->pluck('domain_code');

        foreach ($withoutAreas as $code) {
            $this->problems[] = "Health domain {$code} has no areas, so selecting it narrows nothing.";
        }

        $this->line('  Health domains: '.HealthDomain::count());
    }

    private function checkHealthAreas(string $versionId): void
    {
        $orphans = HealthArea::where('methodology_version_id', $versionId)
            ->whereDoesntHave('healthDomain')
            ->pluck('area_code');

        foreach ($orphans as $code) {
            $this->problems[] = "Health area {$code} belongs to no health domain and is unreachable.";
        }

        $this->line('  Health areas: '.HealthArea::where('methodology_version_id', $versionId)->count());
    }

    /**
     * Objectives are always directly selectable, so none can be orphaned. One carrying no
     * suggestions still works but gives the user nothing to start from.
     */
    private function checkObjectives(string $versionId): void
    {
        $objectives = AssessmentObjective::where('methodology_version_id', $versionId)->get();

        $recommended = ObjectiveRecommendation::whereIn('assessment_objective_id', $objectives->pluck('assessment_objective_id'))
            ->pluck('assessment_objective_id')
            ->unique();

        $inPresets = ObjectivePreset::where('methodology_version_id', $versionId)
            ->pluck('assessment_objective_id')
            ->unique();

        $bare = $objectives->reject(
            fn ($objective) => $recommended->contains($objective->assessment_objective_id)
                || $inPresets->contains($objective->assessment_objective_id)
        );

        foreach ($bare as $objective) {
            $this->warnings[] = "Objective {$objective->objective_code} suggests nothing and appears in no starting point. Selectable, but the user starts from a blank page.";
        }

        $this->line('  Objectives: '.$objectives->count());
    }

    private function checkLenses(string $versionId): void
    {
        $lenses = AnalysisLens::where('methodology_version_id', $versionId)->get();

        $referenced = ObjectiveRecommendation::where('recommends_type', 'ANALYSIS_LENS')
            ->pluck('recommends_ref')
            ->merge(ObjectivePreset::where('methodology_version_id', $versionId)
                ->get()
                ->flatMap(fn ($preset) => $preset->analysis_lens_codes ?? []))
            ->unique();

        foreach ($lenses->whereNotIn('lens_code', $referenced) as $lens) {
            $this->warnings[] = "Analysis lens {$lens->lens_code} is recommended by no objective and used in no starting point. Selectable once lens-driven reporting exists, but nothing points at it.";
        }

        $this->line('  Analysis lenses: '.$lenses->count());
    }

    private function checkTemplates(string $versionId): void
    {
        $templates = AssessmentTemplate::where('methodology_version_id', $versionId)->get();

        $referenced = ObjectiveRecommendation::where('recommends_type', 'TEMPLATE')
            ->pluck('recommends_ref')
            ->merge(ObjectivePreset::where('methodology_version_id', $versionId)->pluck('template_code'))
            ->filter()
            ->unique();

        foreach ($templates->whereNotIn('template_code', $referenced) as $template) {
            $this->warnings[] = "Template {$template->template_code} is recommended by no objective and used in no starting point. Browsable, but nobody is led to it.";
        }

        $this->line('  Templates: '.$templates->count());
    }

    /**
     * Insight categories are consumed by the reporting layer as a whole rather than
     * referenced individually, so the check is that the vocabulary is complete enough to
     * describe both good and bad news, and to admit when the data is too thin.
     */
    private function checkInsightCategories(string $versionId): void
    {
        $categories = InsightCategory::where('methodology_version_id', $versionId)->get();

        foreach (['POSITIVE', 'NEGATIVE', 'NEUTRAL'] as $polarity) {
            if ($categories->where('polarity', $polarity)->isEmpty()) {
                $this->problems[] = "No insight category reads as {$polarity}, so a report could not express that kind of finding.";
            }
        }

        if ($categories->where('is_diagnostic', true)->isEmpty()) {
            $this->problems[] = 'No diagnostic insight category, so no finding could point at a cause rather than a symptom.';
        }

        if ($categories->where('category_code', 'DATA_GAP')->isEmpty()) {
            $this->problems[] = 'No Data Gaps category, so an assessment answered too thinly would still produce a confident-looking report.';
        }

        $this->line('  Insight categories: '.$categories->count());
    }

    private function checkPresets(string $versionId): void
    {
        $presets = ObjectivePreset::where('methodology_version_id', $versionId)->with('objective')->get();
        $domainCodes = HealthDomain::pluck('domain_code')->all();
        $templateCodes = AssessmentTemplate::where('methodology_version_id', $versionId)->pluck('template_code')->all();
        $lensCodes = AnalysisLens::where('methodology_version_id', $versionId)->pluck('lens_code')->all();

        foreach ($presets as $preset) {
            if (! $preset->objective) {
                $this->problems[] = "Starting point {$preset->preset_code} points at an objective that does not exist.";
            }

            foreach ($preset->health_domain_codes ?? [] as $code) {
                if (! in_array($code, $domainCodes, true)) {
                    $this->problems[] = "Starting point {$preset->preset_code} names health domain {$code}, which does not exist.";
                }
            }

            if ($preset->template_code && ! in_array($preset->template_code, $templateCodes, true)) {
                $this->problems[] = "Starting point {$preset->preset_code} names template {$preset->template_code}, which does not exist.";
            }

            foreach ($preset->analysis_lens_codes ?? [] as $code) {
                if (! in_array($code, $lensCodes, true)) {
                    $this->problems[] = "Starting point {$preset->preset_code} names analysis lens {$code}, which does not exist.";
                }
            }
        }

        $this->line('  Starting points: '.$presets->count());
    }

    private function checkRecommendations(string $versionId): void
    {
        $objectiveIds = AssessmentObjective::where('methodology_version_id', $versionId)->pluck('assessment_objective_id');
        $recommendations = ObjectiveRecommendation::whereIn('assessment_objective_id', $objectiveIds)->with('objective')->get();

        $known = [
            'ANALYSIS_LENS' => AnalysisLens::where('methodology_version_id', $versionId)->pluck('lens_code')->all(),
            'TEMPLATE' => AssessmentTemplate::where('methodology_version_id', $versionId)->pluck('template_code')->all(),
            'HEALTH_AREA' => HealthArea::where('methodology_version_id', $versionId)->pluck('area_code')->all(),
            'HEALTH_DOMAIN' => HealthDomain::pluck('domain_code')->all(),
            'MEASUREMENT_DOMAIN' => Domain::pluck('domain_code')->all(),
        ];

        foreach ($recommendations as $recommendation) {
            $valid = $known[$recommendation->recommends_type] ?? null;

            // Evidence types are not a table yet; they are validated when evidence
            // capture is built rather than guessed at here.
            if ($valid === null) {
                continue;
            }

            if (! in_array($recommendation->recommends_ref, $valid, true)) {
                $this->problems[] = "Objective {$recommendation->objective?->objective_code} recommends "
                    .strtolower(str_replace('_', ' ', $recommendation->recommends_type))
                    ." {$recommendation->recommends_ref}, which does not exist.";
            }
        }

        $this->line('  Objective suggestions: '.$recommendations->count());
    }
}
