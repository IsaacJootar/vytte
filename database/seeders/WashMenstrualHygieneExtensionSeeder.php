<?php

namespace Database\Seeders;

use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\DomainDefinition;
use App\Models\DomainTaxonomyVersion;
use App\Models\FrameworkIndicator;
use App\Models\FrameworkIndicatorDomainMapping;
use App\Models\FrameworkQuestionPlacement;
use App\Models\FrameworkSection;
use App\Models\HealthDomain;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\SubIndex;
use App\Services\AssessmentPublicationService;
use App\Services\AssessmentVersionService;
use App\Services\CatalogueReleaseAdvancementService;
use App\Services\DepartmentFrameworkPublishingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Publishes the menstrual hygiene management indicators (WASH.017–WASH.021) everywhere the
 * WASH department already appears.
 *
 * OfficialQuestionLibrarySeeder already published the five question versions as part of the
 * ordinary official library. This seeder does the framework-versioning work that follows:
 * a new version of the focused "WASH in Health Care Facilities" framework, a new version of
 * the reusable "Water, Sanitation & Hygiene Department Framework", and a successor to every
 * comprehensive catalogue release that pins the department framework, so the new indicators
 * reach the standalone WASH assessment and every whole-facility assessment at once.
 *
 * Runs after OfficialFrameworkSeeder and OfficialCatalogueSeeder, on top of their published
 * output, using the same governed publishing services Governance Studio and Advanced Tools
 * use — no published row is ever edited in place, only superseded by a new version.
 * Idempotent: each phase checks what is already live and skips it, so a partial or repeated
 * run finishes cleanly rather than duplicating content.
 */
class WashMenstrualHygieneExtensionSeeder extends Seeder
{
    private const DEPARTMENT_DISPLAY_NAME = 'Water, Sanitation & Hygiene Department Framework';

    private const FOCUSED_DISPLAY_NAME = 'WASH in Health Care Facilities';

    private const NEW_QUESTION_CODES = ['WASH.017', 'WASH.018', 'WASH.019', 'WASH.020', 'WASH.021'];

    public function run(): void
    {
        $module = AssessmentModule::where('module_code', 'WSHF')
            ->where('target_type_code', 'HEALTH_FACILITY')
            ->first();

        if (! $module) {
            $this->command?->warn('WASH department module missing; menstrual hygiene extension skipped.');

            return;
        }

        $firstNewQuestion = Question::where('question_code', self::NEW_QUESTION_CODES[0])->first();
        $questionsReady = $firstNewQuestion
            && $firstNewQuestion->versions()->where('status', QuestionVersion::STATUS_PUBLISHED)->exists();

        if (! $questionsReady) {
            $this->command?->warn('Menstrual hygiene questions are not published yet; run OfficialQuestionLibrarySeeder first.');

            return;
        }

        $this->publishFocusedVersion($module);
        $departmentVersion = $this->publishDepartmentVersion($module);

        if ($departmentVersion) {
            $this->advanceComprehensiveReleases($module, $departmentVersion);
        }

        $this->command?->info('WASH menstrual hygiene management extension applied.');
    }

    private function publishFocusedVersion(AssessmentModule $module): void
    {
        $published = DepartmentFrameworkVersion::where('module_id', $module->module_id)
            ->where('display_name', self::FOCUSED_DISPLAY_NAME)
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->first();

        if (! $published) {
            $this->command?->warn('Focused WASH framework not published yet; focused version skipped.');

            return;
        }

        // Not a version_number check: WASH_FRAMEWORK and WASH_DEPARTMENT share one
        // module_id, and cloneToDraft()/draftFramework() number versions per module, not
        // per framework identity — so version_number alone cannot signal "already carries
        // the menstrual hygiene section" for either framework.
        if ($this->hasMenstrualHygieneSection($published)) {
            return;
        }

        DB::transaction(function () use ($module, $published): void {
            $draft = app(AssessmentVersionService::class)->startNewVersion($published, null);
            $this->addMenstrualHygieneSection($draft, $module);

            $healthDomainId = HealthDomain::where('domain_code', 'WASH')->value('health_domain_id');
            app(AssessmentPublicationService::class)->publish($draft->fresh(), $healthDomainId, null);
        });
    }

    private function publishDepartmentVersion(AssessmentModule $module): ?DepartmentFrameworkVersion
    {
        $published = DepartmentFrameworkVersion::where('module_id', $module->module_id)
            ->where('display_name', self::DEPARTMENT_DISPLAY_NAME)
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->first();

        if (! $published) {
            $this->command?->warn('WASH department framework not published yet; department version skipped.');

            return null;
        }

        if ($this->hasMenstrualHygieneSection($published)) {
            return $published;
        }

        return DB::transaction(function () use ($module, $published): DepartmentFrameworkVersion {
            // Mirrors FrameworkVersionController::supersede(): DEPARTMENT-type frameworks
            // are retired immediately at clone time, not deferred to successor-publish
            // time like the focused/builder path. No catalogue release is touched here —
            // that is handled separately in advanceComprehensiveReleases(), which replaces
            // each release with a successor rather than simply retiring it.
            $draft = app(AssessmentVersionService::class)->cloneToDraft(
                $published,
                'Adds granular menstrual hygiene management indicators (WASH.017-WASH.021).'
            );
            $this->addMenstrualHygieneSection($draft, $module);

            $publishedVersion = app(DepartmentFrameworkPublishingService::class)->publish($draft->fresh());

            $published->update(['status' => DepartmentFrameworkVersion::STATUS_SUPERSEDED]);

            return $publishedVersion;
        });
    }

    private function advanceComprehensiveReleases(AssessmentModule $module, DepartmentFrameworkVersion $newDepartmentVersion): void
    {
        $advancement = app(CatalogueReleaseAdvancementService::class);

        $oldFrameworkVersionIds = DepartmentFrameworkVersion::where('module_id', $module->module_id)
            ->where('framework_version_id', '!=', $newDepartmentVersion->framework_version_id)
            ->pluck('framework_version_id');

        if ($oldFrameworkVersionIds->isEmpty()) {
            return;
        }

        $advanced = 0;

        AssessmentCatalogueRelease::where('creation_path', 'COMPREHENSIVE')
            ->where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)
            ->whereHas('departmentFrameworkVersions', fn ($query) => $query
                ->whereIn('department_framework_versions.framework_version_id', $oldFrameworkVersionIds))
            ->get()
            ->each(function (AssessmentCatalogueRelease $release) use ($module, $newDepartmentVersion, $advancement, &$advanced): void {
                $advancement->advance($release, $module->module_id, $newDepartmentVersion->framework_version_id);
                $advanced++;
            });

        $this->command?->info("WASH department framework advanced in {$advanced} comprehensive catalogue release(s).");
    }

    /**
     * Adds the "Menstrual Hygiene Management" section, indicator and five placements to a
     * draft framework, in the exact shape OfficialFrameworkComposer uses for every other
     * official section: one indicator mapped to its measurement domain, one shared sub-index
     * per module/domain pair (reused via firstOrCreate, so these questions roll into the
     * same "Water, Sanitation & Hygiene" score as WASH.001-WASH.016), and placements scored
     * directly from each question version's baked-in option weights.
     */
    private function addMenstrualHygieneSection(DepartmentFrameworkVersion $draft, AssessmentModule $module): void
    {
        $taxonomyVersion = DomainTaxonomyVersion::where('status', DomainTaxonomyVersion::STATUS_PUBLISHED)
            ->orderByDesc('version_number')
            ->firstOrFail();

        $definition = DomainDefinition::where('domain_taxonomy_version_id', $taxonomyVersion->domain_taxonomy_version_id)
            ->where('domain_code', 'RES')
            ->firstOrFail();

        $sectionOrder = ((int) $draft->sections()->max('display_order')) + 1;
        $placementOrder = ((int) $draft->questionPlacements()->max('display_order')) + 1;

        $section = FrameworkSection::create([
            'framework_version_id' => $draft->framework_version_id,
            'section_code' => 'RES_S'.$sectionOrder,
            'section_name' => 'Menstrual Hygiene Management',
            'purpose' => 'Menstrual Hygiene Management — grouped for scoring against the '.$definition->domain_name.' measurement domain.',
            'display_order' => $sectionOrder,
        ]);

        $indicator = FrameworkIndicator::create([
            'framework_version_id' => $draft->framework_version_id,
            'framework_section_id' => $section->framework_section_id,
            'indicator_code' => 'RES_I'.$sectionOrder,
            'indicator_name' => 'Menstrual Hygiene Management',
            'description' => 'Contributes to the '.$definition->domain_name.' measurement domain.',
            'display_order' => 1,
        ]);

        FrameworkIndicatorDomainMapping::create([
            'framework_indicator_id' => $indicator->framework_indicator_id,
            'domain_definition_id' => $definition->domain_definition_id,
            'is_primary' => true,
            'contribution_weight' => 1,
            'rationale' => 'Section maps to its measurement domain so scores roll up across subjects.',
        ]);

        $subIndex = SubIndex::firstOrCreate(
            ['module_id' => $module->module_id, 'acronym' => $module->module_code.'-'.$definition->domain_code],
            [
                'domain_id' => $definition->domain_id,
                'full_name' => 'Water, Sanitation & Hygiene',
                'description' => 'Scores this framework against the '.$definition->domain_name.' measurement domain.',
                'calculation_method' => 'MEAN',
            ]
        );

        foreach (self::NEW_QUESTION_CODES as $questionCode) {
            $version = $this->publishedVersion($questionCode);

            if (! $version) {
                $this->command?->warn("Question {$questionCode} not found; placement skipped.");

                continue;
            }

            FrameworkQuestionPlacement::create([
                'framework_version_id' => $draft->framework_version_id,
                'framework_section_id' => $section->framework_section_id,
                'framework_indicator_id' => $indicator->framework_indicator_id,
                'question_id' => $version->question_id,
                'question_version_id' => $version->question_version_id,
                'sub_index_id' => $subIndex->sub_index_id,
                'display_order' => $placementOrder++,
                'is_required' => true,
                'evidence_expectation' => 'Supporting evidence may be attached to explain or substantiate the answer.',
                'weight' => 1,
                'scoring_contribution' => true,
                'criticality' => 'STANDARD',
                'local_display_text' => $version->question_text,
                'metadata' => ['content_kind' => 'official'],
            ]);

            DB::table('sub_index_questions')->insertOrIgnore([
                'sub_index_id' => $subIndex->sub_index_id,
                'question_id' => $version->question_id,
                'weight' => 1,
            ]);
        }
    }

    private function hasMenstrualHygieneSection(DepartmentFrameworkVersion $framework): bool
    {
        return FrameworkQuestionPlacement::where('framework_version_id', $framework->framework_version_id)
            ->whereHas('question', fn ($query) => $query->where('question_code', self::NEW_QUESTION_CODES[0]))
            ->exists();
    }

    private function publishedVersion(string $questionCode): ?QuestionVersion
    {
        $question = Question::where('question_code', $questionCode)->first();

        if (! $question) {
            return null;
        }

        return QuestionVersion::where('question_id', $question->question_id)
            ->where('status', QuestionVersion::STATUS_PUBLISHED)
            ->orderByDesc('version_number')
            ->first();
    }
}
