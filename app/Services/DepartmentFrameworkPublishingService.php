<?php

namespace App\Services;

use App\Models\DepartmentFrameworkVersion;
use App\Models\QuestionVersion;
use App\Support\ResponseInputContract;
use Illuminate\Validation\ValidationException;

class DepartmentFrameworkPublishingService
{
    public function __construct(private readonly FrameworkContentService $content) {}

    public function publish(DepartmentFrameworkVersion $version, ?string $publisherId = null): DepartmentFrameworkVersion
    {
        $version->load([
            'module',
            'questionPlacements.questionVersion.questionType',
            'questionPlacements.section',
            'questionPlacements.indicator',
            'questionPlacements.subIndex',
        ]);

        $module = $version->module;
        $placements = $version->questionPlacements;
        $errors = [];

        if ($version->status !== DepartmentFrameworkVersion::STATUS_DRAFT) {
            $errors['status'][] = 'Only draft framework versions can be published.';
        }

        if (! $module || ! $module->is_active) {
            $errors['module'][] = 'Only active Vytte departments or focused scopes can be published.';
        }

        if (! $version->source_authority || ! $version->license_code) {
            $errors['methodology'][] = 'Source authority and license metadata are required before publishing.';
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

            return collect($placement->questionVersion?->options ?? [])
                ->contains(fn ($option) => ! array_key_exists('score_weight', $option) || $option['score_weight'] === null);
        });
        if ($scoredOptionsWithoutWeight) {
            $errors['scoring'][] = 'Every option on a scored placement must have a score weight.';
        }

        $invalidNumericBands = $placements->contains(function ($placement): bool {
            return $placement->scoring_contribution
                && $placement->questionVersion?->questionType?->type_code === 'NUMERIC'
                && collect($placement->questionVersion?->numeric_bands ?? [])->isEmpty();
        });
        if ($invalidNumericBands) {
            $errors['scoring'][] = 'Scored numeric placements must define frozen scoring bands.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $payload = $this->content->frameworkPayload($version);

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
