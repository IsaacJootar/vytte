<?php

namespace App\Services;

use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\DomainDefinition;
use App\Models\DomainTaxonomyVersion;
use App\Models\FrameworkIndicator;
use App\Models\FrameworkIndicatorDomainMapping;
use App\Models\FrameworkQuestionPlacement;
use App\Models\FrameworkSection;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\SubIndex;
use Illuminate\Support\Facades\DB;

/**
 * Composes an official framework from the published question library and publishes it.
 *
 * This is the engine behind the official framework catalogue. A framework does not carry
 * its own questions; it places published question versions already in the library, which
 * is what lets one hand hygiene question serve infection prevention, hospital readiness
 * and maternal care at once.
 *
 * A framework belongs to one department but may place questions authored against any
 * department, because reuse across subjects is the whole point. Every framework is built
 * as a draft and published through DepartmentFrameworkPublishingService, so it passes the
 * same validation and receives the same content hash as one built by hand in the builder.
 *
 * Sections are organised by measurement domain. Each section carries one indicator mapped
 * to that domain, and the questions in the section score into a sub-index for that domain.
 * This is what makes cross-subject domain scoring work: a governance weakness in a malaria
 * framework and a governance weakness in a hospital framework roll up to the same domain.
 */
class OfficialFrameworkComposer
{
    /** @var array<string, DomainDefinition> */
    private array $definitions;

    private DomainTaxonomyVersion $taxonomyVersion;

    public function __construct(private readonly DepartmentFrameworkPublishingService $publishing)
    {
        $this->taxonomyVersion = DomainTaxonomyVersion::where('status', DomainTaxonomyVersion::STATUS_PUBLISHED)
            ->orderByDesc('version_number')
            ->firstOrFail();

        $this->definitions = DomainDefinition::where('domain_taxonomy_version_id', $this->taxonomyVersion->domain_taxonomy_version_id)
            ->get()
            ->keyBy('domain_code')
            ->all();
    }

    /**
     * @param  array{module: string, code: string, name: string, description: string, type: string, sections: array<int, array{domain: string, name: string, questions: array<int, string>}>}  $spec
     * @return array{status: string, framework: ?DepartmentFrameworkVersion, placed: int, missing: array<int, string>}
     */
    public function compose(array $spec): array
    {
        return DB::transaction(function () use ($spec): array {
            $module = AssessmentModule::where('module_code', $spec['module'])
                ->where('target_type_code', 'HEALTH_FACILITY')
                ->first();

            if (! $module) {
                return ['status' => 'no_department', 'framework' => null, 'placed' => 0, 'missing' => []];
            }

            // Idempotent: a published framework of this name is left untouched, because a
            // published framework version is immutable.
            $existing = DepartmentFrameworkVersion::where('module_id', $module->module_id)
                ->where('display_name', $spec['name'])
                ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
                ->first();

            if ($existing) {
                return ['status' => 'already_published', 'framework' => $existing, 'placed' => 0, 'missing' => []];
            }

            $framework = $this->draftFramework($module, $spec);
            [$placed, $missing] = $this->buildSections($framework, $module, $spec['sections']);

            if ($placed === 0) {
                return ['status' => 'no_questions', 'framework' => null, 'placed' => 0, 'missing' => $missing];
            }

            $published = $this->publishing->publish($framework->fresh());

            return ['status' => 'published', 'framework' => $published, 'placed' => $placed, 'missing' => $missing];
        });
    }

    private function draftFramework(AssessmentModule $module, array $spec): DepartmentFrameworkVersion
    {
        $nextVersion = ((int) DepartmentFrameworkVersion::where('module_id', $module->module_id)->max('version_number')) + 1;

        return DepartmentFrameworkVersion::create([
            'module_id' => $module->module_id,
            'version_number' => $nextVersion,
            'status' => DepartmentFrameworkVersion::STATUS_DRAFT,
            'framework_type' => $spec['type'],
            'display_name' => $spec['name'],
            'description' => $spec['description'],
            'purpose' => $spec['description'],
            'source_authority' => 'Vytte Official Methodology',
            'license_code' => 'VYTTE-OFFICIAL',
            'scoring_version' => 'vytte-4.0-numeric-bands',
            'methodology_notes' => 'Composed from the official Vytte question library, informed by recognised international health assessment practice.',
            'source_summary' => 'Vytte official framework. No copyrighted instrument is reproduced.',
            'provenance' => ['content_kind' => 'official', 'code' => $spec['code']],
            'critical_failure_rules' => ['uses_flagged_options' => true],
            'effective_date' => now()->toDateString(),
        ]);
    }

    /**
     * @param  array<int, array{domain: string, name: string, questions: array<int, string>}>  $sections
     * @return array{0: int, 1: array<int, string>}
     */
    private function buildSections(DepartmentFrameworkVersion $framework, AssessmentModule $module, array $sections): array
    {
        $placed = 0;
        $missing = [];
        $sectionOrder = 0;
        $placementOrder = 0;

        foreach ($sections as $sectionSpec) {
            $definition = $this->definitions[$sectionSpec['domain']] ?? null;

            if (! $definition) {
                continue;
            }

            // Section and indicator codes must be unique within a framework. Two sections
            // can legitimately map to the same measurement domain — general safety and
            // infection control both roll up to Safety — so the order disambiguates the
            // codes while both still contribute to the one domain and one sub-index.
            $sectionOrder++;

            $section = FrameworkSection::create([
                'framework_version_id' => $framework->framework_version_id,
                'section_code' => $sectionSpec['domain'].'_S'.$sectionOrder,
                'section_name' => $sectionSpec['name'],
                'purpose' => $sectionSpec['name'].' — grouped for scoring against the '.$definition->domain_name.' measurement domain.',
                'display_order' => $sectionOrder,
            ]);

            $indicator = FrameworkIndicator::create([
                'framework_version_id' => $framework->framework_version_id,
                'framework_section_id' => $section->framework_section_id,
                'indicator_code' => $sectionSpec['domain'].'_I'.$sectionOrder,
                'indicator_name' => $sectionSpec['name'],
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

            $subIndex = $this->subIndexFor($module, $definition, $sectionSpec['name']);

            foreach ($sectionSpec['questions'] as $questionCode) {
                $version = $this->publishedVersion($questionCode);

                if (! $version) {
                    $missing[] = $questionCode;

                    continue;
                }

                // A question is scored unless it cannot be. Open text is context by
                // definition, and a numeric question with no scoring bands is a raw count
                // — how many staff, how many vacancies — which is meaningful context but
                // not a judgement on a 0–100 scale. Both are placed as unscored context
                // so they inform analysis without distorting the score.
                $type = $version->questionType?->type_code;
                $numericWithoutBands = $type === 'NUMERIC' && empty($version->numeric_bands);
                $scored = $type !== 'OPEN_ENDED' && ! $numericWithoutBands;

                FrameworkQuestionPlacement::create([
                    'framework_version_id' => $framework->framework_version_id,
                    'framework_section_id' => $section->framework_section_id,
                    'framework_indicator_id' => $indicator->framework_indicator_id,
                    'question_id' => $version->question_id,
                    'question_version_id' => $version->question_version_id,
                    'sub_index_id' => $scored ? $subIndex->sub_index_id : null,
                    'display_order' => ++$placementOrder,
                    'is_required' => $scored,
                    'evidence_expectation' => 'Supporting evidence may be attached to explain or substantiate the answer.',
                    'weight' => 1,
                    'scoring_contribution' => $scored,
                    'criticality' => 'STANDARD',
                    'local_display_text' => $version->question_text,
                    'metadata' => ['content_kind' => 'official'],
                ]);

                if ($scored) {
                    DB::table('sub_index_questions')->insertOrIgnore([
                        'sub_index_id' => $subIndex->sub_index_id,
                        'question_id' => $version->question_id,
                        'weight' => 1,
                    ]);
                }

                $placed++;
            }
        }

        return [$placed, array_values(array_unique($missing))];
    }

    private function subIndexFor(AssessmentModule $module, DomainDefinition $definition, string $name): SubIndex
    {
        $acronym = $module->module_code.'-'.$definition->domain_code;

        return SubIndex::firstOrCreate(
            ['module_id' => $module->module_id, 'acronym' => $acronym],
            [
                'domain_id' => $definition->domain_id,
                'full_name' => $name,
                'description' => 'Scores this framework against the '.$definition->domain_name.' measurement domain.',
                'calculation_method' => 'MEAN',
            ]
        );
    }

    private function publishedVersion(string $questionCode): ?QuestionVersion
    {
        $question = Question::where('question_code', $questionCode)->first();

        if (! $question) {
            return null;
        }

        return QuestionVersion::with('questionType')
            ->where('question_id', $question->question_id)
            ->where('status', QuestionVersion::STATUS_PUBLISHED)
            ->orderByDesc('version_number')
            ->first();
    }
}
