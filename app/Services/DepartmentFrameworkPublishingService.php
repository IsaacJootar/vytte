<?php

namespace App\Services;

use App\Models\DepartmentFrameworkVersion;
use App\Models\QuestionVersion;
use App\Support\ResponseInputContract;
use Illuminate\Validation\ValidationException;

class DepartmentFrameworkPublishingService
{
    public function __construct(
        private readonly FrameworkContentService $content,
        private readonly ScoringModelService $scoringModels,
        private readonly AssessmentLogicService $logic,
    ) {}

    public function publish(DepartmentFrameworkVersion $version, ?string $publisherId = null): DepartmentFrameworkVersion
    {
        $version->load([
            'module',
            'contentPublisher',
            'questionPlacements.questionVersion.questionType',
            'questionPlacements.scoringItemRule',
            'questionPlacements.section',
            'questionPlacements.indicator.domainMappings.domainDefinition.taxonomyVersion',
            'questionPlacements.domainOverrides.domainDefinition.taxonomyVersion',
            'questionPlacements.subIndex',
        ]);

        $module = $version->module;
        $placements = $version->questionPlacements;
        $errors = [];

        if ($version->status !== DepartmentFrameworkVersion::STATUS_DRAFT) {
            $errors['status'][] = 'Only draft framework versions can be published.';
        } else {
            $this->scoringModels->ensureForFramework($version);
            foreach ($placements as $placement) {
                if (! $placement->scoringItemRule) {
                    $this->scoringModels->syncRuleFromPlacement($version, $placement);
                }
            }
            $version->load('questionPlacements.scoringItemRule');
            $placements = $version->questionPlacements;
        }

        if (! $module || ! $module->is_active) {
            $errors['module'][] = 'Only active Vytte departments or focused scopes can be published.';
        }

        if (! $version->source_authority || ! $version->license_code) {
            $errors['methodology'][] = 'Source authority and license metadata are required before publishing.';
        }

        if (! $version->contentPublisher || $version->contentPublisher->verification_status === 'SUSPENDED') {
            $errors['publisher'][] = 'Choose an active accountable publisher before publishing.';
        }

        if ($placements->isEmpty()) {
            $errors['placements'][] = 'A framework version must place at least one exact question version.';
        }

        $unpublished = $placements->first(
            fn ($placement) => $placement->questionVersion?->status !== QuestionVersion::STATUS_PUBLISHED
        );
        if ($unpublished) {
            $errors['question_versions'][] = 'Frameworks can only publish exact published question versions.';
        }

        $unsupportedTypes = $placements
            ->map(fn ($placement) => $placement->questionVersion?->questionType?->type_code)
            ->filter()
            ->reject(fn ($type) => ResponseInputContract::supports($type))
            ->unique()
            ->values();
        if ($unsupportedTypes->isNotEmpty()) {
            $errors['response_types'][] = 'Unsupported response types: '.$unsupportedTypes->join(', ').'.';
        }

        $optionQuestionWithoutOptions = $placements->contains(function ($placement): bool {
            $type = $placement->questionVersion?->questionType?->type_code;

            return in_array($type, ResponseInputContract::OPTION_TYPES, true)
                && collect($placement->questionVersion?->options ?? [])->isEmpty();
        });
        if ($optionQuestionWithoutOptions) {
            $errors['response_inputs'][] = 'Every option-based question version must contain at least one selectable answer.';
        }

        foreach ($placements->filter(fn ($placement) => ($placement->applicability['type'] ?? null) === 'response_rule') as $placement) {
            try {
                $this->logic->saveRule($version, $placement, $placement->applicability);
            } catch (ValidationException $exception) {
                $errors['logic'][] = 'A question has invalid branching logic: '.collect($exception->errors())->flatten()->first();
            }
        }

        $unscorableOpenText = $placements->contains(function ($placement): bool {
            return $placement->scoring_contribution
                && $placement->questionVersion?->questionType?->type_code === 'OPEN_ENDED';
        });
        if ($unscorableOpenText) {
            $errors['scoring'][] = 'Open-text placements must be unscored supporting context.';
        }

        $scoredPlacementWithoutProfile = $placements->contains(
            fn ($placement) => $placement->scoring_contribution && $placement->sub_index_id === null
        );
        if ($scoredPlacementWithoutProfile) {
            $errors['scoring'][] = 'Every scored placement must belong to the Vytte scoring profile.';
        }

        $scoredOptionsWithoutWeight = $placements->contains(function ($placement): bool {
            $type = $placement->questionVersion?->questionType?->type_code;
            if (! $placement->scoring_contribution || ! in_array($type, ResponseInputContract::OPTION_TYPES, true)) {
                return false;
            }

            $rules = collect($placement->scoringItemRule?->rule_config['option_scores'] ?? []);

            return $rules->isEmpty() || $rules->contains(fn ($option) => ! array_key_exists('score', $option) || $option['score'] === null);
        });
        if ($scoredOptionsWithoutWeight) {
            $errors['scoring'][] = 'Every option on a scored placement must have a score weight.';
        }

        $invalidNumericBands = $placements->contains(function ($placement): bool {
            return $placement->scoring_contribution
                && $placement->questionVersion?->questionType?->type_code === 'NUMERIC'
                && collect($placement->scoringItemRule?->rule_config['numeric_bands'] ?? [])->isEmpty();
        });
        if ($invalidNumericBands) {
            $errors['scoring'][] = 'Scored numeric placements must define frozen scoring bands.';
        }

        $domainMappings = $placements->flatMap(function ($placement) {
            return $placement->domainOverrides->isNotEmpty()
                ? $placement->domainOverrides
                : $placement->indicator?->domainMappings ?? collect();
        });
        $unpublishedDomainMapping = $domainMappings->first(
            fn ($mapping) => $mapping->domainDefinition?->taxonomyVersion?->status !== 'PUBLISHED'
        );
        if ($unpublishedDomainMapping) {
            $errors['domains'][] = 'Analytical domain mappings must reference a published domain taxonomy version.';
        }
        $multiplePrimaryMappings = $placements->first(function ($placement): bool {
            $mappings = $placement->domainOverrides->isNotEmpty()
                ? $placement->domainOverrides
                : $placement->indicator?->domainMappings ?? collect();

            return $mappings->where('is_primary', true)->count() > 1;
        });
        if ($multiplePrimaryMappings) {
            $errors['domains'][] = 'A placement can have only one primary analytical domain.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $scoringModel = $this->scoringModels->publishForFramework($version, $publisherId);
        $version->forceFill(['scoring_model_version_id' => $scoringModel->scoring_model_version_id])->save();
        $payload = $this->content->frameworkPayload($version->fresh());

        $version->update([
            'status' => DepartmentFrameworkVersion::STATUS_PUBLISHED,
            'scoring_version' => ScoringService::ALGORITHM_VERSION,
            'content_hash' => $this->content->hash($payload),
            'published_payload' => $payload,
            'published_at' => now(),
            'published_by' => $publisherId,
        ]);

        app(AuditService::class)->record(
            'department.framework.published',
            $version->fresh(),
            ['status' => DepartmentFrameworkVersion::STATUS_DRAFT],
            ['status' => DepartmentFrameworkVersion::STATUS_PUBLISHED, 'content_hash' => $version->content_hash],
            userId: $publisherId,
        );

        return $version->fresh(['module']);
    }
}
