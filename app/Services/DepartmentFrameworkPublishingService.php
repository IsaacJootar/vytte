<?php

namespace App\Services;

use App\Models\DepartmentFrameworkVersion;
use App\Support\ResponseInputContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepartmentFrameworkPublishingService
{
    public function __construct(private readonly FrameworkContentService $content) {}

    public function publish(DepartmentFrameworkVersion $version, ?string $publisherId = null): DepartmentFrameworkVersion
    {
        $version->load(['module.questions.options', 'module.questions.numericBands', 'module.questions.questionType']);
        $module = $version->module;
        $errors = [];

        if (! $module || ! $module->is_active) {
            $errors['module'][] = 'Only active Vytte departments can be published.';
        }

        if (! $version->source_authority || ! $version->license_code) {
            $errors['provenance'][] = 'Source authority and license metadata are required before publishing.';
        }

        $activeQuestions = $module?->questions->where('is_active', true) ?? collect();
        if ($activeQuestions->isEmpty()) {
            $errors['questions'][] = 'A department framework version must contain at least one active question.';
        }

        $draftContentExists = $activeQuestions->contains(
            fn ($question) => in_array($question->question_status, ['DRAFT', 'SAMPLE', 'PENDING_REVIEW'], true)
                || in_array($question->source, ['SAMPLE', 'DRAFT'], true)
        );
        if ($draftContentExists) {
            $errors['questions'][] = 'Draft or sample questions cannot be published into official framework versions.';
        }

        $moduleIds = $module ? [$module->module_id] : [];
        $unsupportedTypes = DB::table('questions as q')
            ->join('question_types as qt', 'qt.type_id', '=', 'q.type_id')
            ->whereIn('q.module_id', $moduleIds)
            ->where('q.is_active', true)
            ->whereNotIn('qt.type_code', ResponseInputContract::SUPPORTED_TYPES)
            ->distinct()
            ->pluck('qt.type_code');

        if ($unsupportedTypes->isNotEmpty()) {
            $errors['response_types'][] = 'Unsupported response types: '.$unsupportedTypes->join(', ').'.';
        }

        $questionsWithoutOptions = DB::table('questions as q')
            ->join('question_types as qt', 'qt.type_id', '=', 'q.type_id')
            ->leftJoin('question_options as qo', 'qo.question_id', '=', 'q.question_id')
            ->whereIn('q.module_id', $moduleIds)
            ->where('q.is_active', true)
            ->whereIn('qt.type_code', ResponseInputContract::OPTION_TYPES)
            ->select('q.question_id')
            ->groupBy('q.question_id', 'qt.type_code')
            ->havingRaw('COUNT(qo.option_id) = 0')
            ->exists();

        if ($questionsWithoutOptions) {
            $errors['response_inputs'][] = 'Every option-based question must contain at least one selectable answer.';
        }

        $unscorableOpenText = DB::table('questions as q')
            ->join('question_types as qt', 'qt.type_id', '=', 'q.type_id')
            ->whereIn('q.module_id', $moduleIds)
            ->where('q.is_active', true)
            ->where('q.is_scored', true)
            ->where('qt.type_code', 'OPEN_ENDED')
            ->exists();
        if ($unscorableOpenText) {
            $errors['scoring'][] = 'Open-text questions must be unscored supporting context.';
        }

        $scoredQuestionsWithoutProfile = DB::table('questions as q')
            ->leftJoin('sub_index_questions as siq', 'siq.question_id', '=', 'q.question_id')
            ->whereIn('q.module_id', $moduleIds)
            ->where('q.is_active', true)
            ->where('q.is_scored', true)
            ->whereNull('siq.sub_index_id')
            ->exists();
        if ($scoredQuestionsWithoutProfile) {
            $errors['scoring'][] = 'Every scored question must belong to the Vytte scoring profile.';
        }

        $scoredOptionsWithoutWeight = DB::table('questions as q')
            ->join('question_types as qt', 'qt.type_id', '=', 'q.type_id')
            ->join('question_options as qo', 'qo.question_id', '=', 'q.question_id')
            ->whereIn('q.module_id', $moduleIds)
            ->where('q.is_active', true)
            ->where('q.is_scored', true)
            ->whereIn('qt.type_code', ResponseInputContract::OPTION_TYPES)
            ->whereNull('qo.score_weight')
            ->exists();
        if ($scoredOptionsWithoutWeight) {
            $errors['scoring'][] = 'Every option on a scored question must have a score weight.';
        }

        $invalidNumericBands = $activeQuestions
            ->where('is_scored', true)
            ->contains(fn ($question) => $question->questionType?->type_code === 'NUMERIC'
                && $question->numericBands->isEmpty());
        if ($invalidNumericBands) {
            $errors['scoring'][] = 'Scored numeric questions must define frozen scoring bands.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $payload = $this->content->modulePayload($module);

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
