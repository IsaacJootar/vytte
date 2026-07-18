<?php

namespace App\Services;

use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;

class FrameworkContentService
{
    public function frameworkPayload(DepartmentFrameworkVersion $version, int $displayOrder = 1, ?string $areaLabel = null): array
    {
        $version->loadMissing([
            'module',
            'sections',
            'indicators.section',
            'indicators.domainMappings.domainDefinition.taxonomyVersion.taxonomy',
            'indicators.domainMappings.domainDefinition.domain',
            'questionPlacements.section',
            'questionPlacements.indicator.domainMappings.domainDefinition.taxonomyVersion.taxonomy',
            'questionPlacements.indicator.domainMappings.domainDefinition.domain',
            'questionPlacements.domainOverrides.domainDefinition.taxonomyVersion.taxonomy',
            'questionPlacements.domainOverrides.domainDefinition.domain',
            'questionPlacements.question',
            'questionPlacements.questionVersion.questionType',
            'questionPlacements.subIndex.domain',
        ]);

        $placements = $version->questionPlacements
            ->sortBy('display_order')
            ->values();

        $questions = $placements->map(function ($placement) {
            $questionVersion = $placement->questionVersion;
            $responseType = $questionVersion->questionType?->type_code;
            $renderedText = $placement->local_display_text ?: $questionVersion->question_text;
            $analyticalDomains = $this->analyticalDomainsForPlacement($placement);

            return [
                'framework_question_placement_id' => $placement->framework_question_placement_id,
                'question_id' => $placement->question_id,
                'question_code' => $placement->question?->question_code,
                'question_version_id' => $placement->question_version_id,
                'question_version_number' => (int) $questionVersion->version_number,
                'question_version_hash' => $questionVersion->content_hash,
                'question_text' => $renderedText,
                'canonical_question_text' => $questionVersion->question_text,
                'response_type' => $responseType,
                'display_order' => (int) $placement->display_order,
                'section_id' => $placement->framework_section_id,
                'section_code' => $placement->section?->section_code,
                'section_name' => $placement->section?->section_name,
                'section_display_order' => $placement->section?->display_order,
                'indicator_id' => $placement->framework_indicator_id,
                'indicator_code' => $placement->indicator?->indicator_code,
                'indicator_name' => $placement->indicator?->indicator_name,
                'indicator_display_order' => $placement->indicator?->display_order,
                'analytical_domains' => $analyticalDomains,
                'primary_analytical_domain' => collect($analyticalDomains)->firstWhere('is_primary', true),
                'section_label' => $placement->section?->section_name,
                'section_number' => $placement->section?->display_order,
                'is_required' => (bool) $placement->is_required,
                'is_scored' => (bool) $placement->scoring_contribution,
                'requires_observation' => (bool) $questionVersion->requires_observation,
                'evidence_expectation' => $placement->evidence_expectation,
                'applicability' => $placement->applicability,
                'weight' => (float) $placement->weight,
                'criticality' => $placement->criticality,
                'help_text' => $placement->help_text,
                'respondent_role_hint' => $questionVersion->respondent_role_hint,
                'methodology_notes' => $questionVersion->methodology_notes,
                'source_summary' => $questionVersion->source_summary,
                'numeric_config' => $responseType === 'NUMERIC' ? $questionVersion->numeric_config : null,
                'numeric_bands' => collect($questionVersion->numeric_bands ?? [])->values()->all(),
                'options' => collect($questionVersion->options ?? [])->values()->all(),
            ];
        })->values();

        $scoringProfile = $placements
            ->filter(fn ($placement) => $placement->scoring_contribution && $placement->subIndex)
            ->groupBy('sub_index_id')
            ->map(function ($group) {
                $first = $group->first();
                $subIndex = $first->subIndex;

                return [
                    'sub_index_id' => $subIndex->sub_index_id,
                    'acronym' => $subIndex->acronym,
                    'full_name' => $subIndex->full_name,
                    'domain_id' => $subIndex->domain_id,
                    'domain_code' => $subIndex->domain?->domain_code,
                    'domain_name' => $subIndex->domain?->domain_name,
                    'domain_display_order' => $subIndex->domain?->display_order,
                    'questions' => $group->map(fn ($placement) => [
                        'question_id' => $placement->question_id,
                        'question_version_id' => $placement->question_version_id,
                        'framework_question_placement_id' => $placement->framework_question_placement_id,
                        'analytical_domains' => $this->analyticalDomainsForPlacement($placement),
                        'weight' => (float) $placement->weight,
                    ])->values()->all(),
                ];
            })->values();

        return [
            'module_id' => $version->module_id,
            'module_code' => $version->module?->module_code,
            'module_name' => $version->module?->module_name,
            'framework_version_id' => $version->framework_version_id,
            'framework_type' => $version->framework_type,
            'framework_version_number' => (int) $version->version_number,
            'framework_display_name' => $version->display_name,
            'purpose' => $version->purpose,
            'methodology_notes' => $version->methodology_notes,
            'source_summary' => $version->source_summary,
            'requires_consent' => (bool) $version->module?->requires_consent,
            'display_order' => $displayOrder,
            'area_label' => $areaLabel,
            'sections' => $version->sections->map(fn ($section) => [
                'framework_section_id' => $section->framework_section_id,
                'section_code' => $section->section_code,
                'section_name' => $section->section_name,
                'purpose' => $section->purpose,
                'display_order' => (int) $section->display_order,
            ])->values()->all(),
            'indicators' => $version->indicators->map(fn ($indicator) => [
                'framework_indicator_id' => $indicator->framework_indicator_id,
                'framework_section_id' => $indicator->framework_section_id,
                'indicator_code' => $indicator->indicator_code,
                'indicator_name' => $indicator->indicator_name,
                'description' => $indicator->description,
                'display_order' => (int) $indicator->display_order,
                'analytical_domains' => $indicator->domainMappings
                    ->sortByDesc('is_primary')
                    ->map(fn ($mapping) => $this->domainMappingPayload($mapping))
                    ->values()
                    ->all(),
            ])->values()->all(),
            'questions' => $questions->all(),
            'scoring_profile' => $scoringProfile->all(),
        ];
    }

    private function analyticalDomainsForPlacement($placement): array
    {
        $mappings = $placement->domainOverrides->isNotEmpty()
            ? $placement->domainOverrides
            : $placement->indicator?->domainMappings ?? collect();

        return collect($mappings)
            ->sortByDesc('is_primary')
            ->map(fn ($mapping) => $this->domainMappingPayload($mapping))
            ->values()
            ->all();
    }

    private function domainMappingPayload($mapping): array
    {
        $definition = $mapping->domainDefinition;
        $version = $definition?->taxonomyVersion;

        return [
            'domain_definition_id' => $definition?->domain_definition_id,
            'domain_taxonomy_version_id' => $definition?->domain_taxonomy_version_id,
            'domain_taxonomy_code' => $version?->taxonomy?->taxonomy_code,
            'domain_taxonomy_version_number' => $version?->version_number !== null ? (int) $version->version_number : null,
            'domain_taxonomy_content_hash' => $version?->content_hash,
            'domain_id' => $definition?->domain_id !== null ? (int) $definition->domain_id : null,
            'domain_code' => $definition?->domain_code,
            'domain_name' => $definition?->domain_name,
            'definition' => $definition?->definition,
            'is_primary' => (bool) $mapping->is_primary,
            'contribution_weight' => (float) $mapping->contribution_weight,
            'mapping_source' => method_exists($mapping, 'placement') ? 'PLACEMENT_OVERRIDE' : 'INDICATOR',
        ];
    }

    public function modulePayload(AssessmentModule $module, int $displayOrder = 1, ?string $areaLabel = null): array
    {
        $version = $module->frameworkVersions()
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->orderByDesc('version_number')
            ->firstOrFail();

        return $this->frameworkPayload($version, $displayOrder, $areaLabel);
    }

    public function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
