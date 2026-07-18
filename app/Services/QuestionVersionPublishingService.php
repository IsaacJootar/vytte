<?php

namespace App\Services;

use App\Models\QuestionVersion;
use App\Support\ResponseInputContract;
use Illuminate\Validation\ValidationException;

class QuestionVersionPublishingService
{
    public function __construct(private readonly FrameworkContentService $content) {}

    public function publish(QuestionVersion $version, ?string $publisherId = null): QuestionVersion
    {
        $version->load('questionType');
        $errors = [];
        $type = $version->questionType?->type_code;

        if ($version->status !== QuestionVersion::STATUS_APPROVED) {
            $errors['status'][] = 'Only approved question versions can be published.';
        }

        if (! $type || ! ResponseInputContract::supports($type)) {
            $errors['response_type'][] = 'Question version uses an unsupported response type.';
        }

        $options = collect($version->options ?? []);
        if (in_array($type, ResponseInputContract::OPTION_TYPES, true) && $options->isEmpty()) {
            $errors['options'][] = 'Option-based question versions require at least one selectable answer.';
        }

        $numericConfig = $version->numeric_config ?? [];
        if ($type === 'NUMERIC'
            && array_key_exists('min', $numericConfig)
            && array_key_exists('max', $numericConfig)
            && $numericConfig['min'] !== null
            && $numericConfig['max'] !== null
            && (float) $numericConfig['min'] > (float) $numericConfig['max']) {
            $errors['numeric_config'][] = 'Numeric question version minimum cannot exceed maximum.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $payload = $this->versionPayload($version);

        $version->update([
            'status' => QuestionVersion::STATUS_PUBLISHED,
            'content_hash' => $this->content->hash($payload),
            'published_at' => now(),
            'published_by' => $publisherId,
        ]);

        app(AuditService::class)->record(
            'question.version.published',
            $version->fresh(),
            ['status' => QuestionVersion::STATUS_APPROVED],
            ['status' => QuestionVersion::STATUS_PUBLISHED, 'content_hash' => $version->content_hash],
            userId: $publisherId,
        );

        return $version->fresh(['question', 'questionType']);
    }

    public function versionPayload(QuestionVersion $version): array
    {
        $version->loadMissing(['question', 'questionType']);

        return [
            'question_id' => $version->question_id,
            'question_code' => $version->question?->question_code,
            'question_version_id' => $version->question_version_id,
            'question_version_number' => (int) $version->version_number,
            'question_text' => $version->question_text,
            'response_type' => $version->questionType?->type_code,
            'requires_observation' => (bool) $version->requires_observation,
            'respondent_role_hint' => $version->respondent_role_hint,
            'options' => collect($version->options ?? [])->values()->all(),
            'numeric_config' => $version->numeric_config,
            'numeric_bands' => collect($version->numeric_bands ?? [])->values()->all(),
            'methodology_notes' => $version->methodology_notes,
            'source_summary' => $version->source_summary,
            'content_hash' => $version->content_hash,
        ];
    }
}
