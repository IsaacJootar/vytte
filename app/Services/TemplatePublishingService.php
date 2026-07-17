<?php

namespace App\Services;

use App\Models\AssessmentTemplateVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TemplatePublishingService
{
    private const SUPPORTED_RESPONSE_TYPES = ['SINGLE_SELECT', 'LIKERT', 'OPEN_ENDED'];

    public function __construct(private readonly TemplateContentService $content) {}

    public function publish(AssessmentTemplateVersion $version, ?string $publisherId = null): AssessmentTemplateVersion
    {
        $version->load(['template', 'modules.questions.options', 'modules.questions.questionType']);
        $template = $version->template;
        $modules = $version->modules;

        $errors = [];

        if (! in_array($template->creation_path, ['COMPREHENSIVE', 'FOCUSED'], true)) {
            $errors['creation_path'][] = 'A template must use one of the two approved creation paths.';
        }

        if (! $template->source_authority || ! $template->license_code) {
            $errors['provenance'][] = 'Source authority and license metadata are required before publishing.';
        }

        if ($template->creation_path === 'COMPREHENSIVE' && ! $template->setting_type_code) {
            $errors['setting_type_code'][] = 'A comprehensive template must declare its setting.';
        }

        if ($template->creation_path === 'FOCUSED') {
            if (! $template->health_domain_id) {
                $errors['health_domain_id'][] = 'A focused template must declare one health domain.';
            }
            if ($modules->count() !== 1) {
                $errors['modules'][] = 'A focused template must open one assessment scope directly.';
            }
        }

        if ($modules->isEmpty()) {
            $errors['modules'][] = 'A template must contain at least one assessment module.';
        }

        $unsupportedTypes = DB::table('questions as q')
            ->join('question_types as qt', 'qt.type_id', '=', 'q.type_id')
            ->whereIn('q.module_id', $modules->pluck('module_id'))
            ->where('q.is_active', true)
            ->whereNotIn('qt.type_code', self::SUPPORTED_RESPONSE_TYPES)
            ->distinct()
            ->pluck('qt.type_code');

        if ($unsupportedTypes->isNotEmpty()) {
            $errors['response_types'][] = 'Unsupported response types: '.$unsupportedTypes->join(', ').'.';
        }

        $questionsWithoutOptions = DB::table('questions as q')
            ->join('question_types as qt', 'qt.type_id', '=', 'q.type_id')
            ->leftJoin('question_options as qo', 'qo.question_id', '=', 'q.question_id')
            ->whereIn('q.module_id', $modules->pluck('module_id'))
            ->where('q.is_active', true)
            ->where('qt.type_code', '!=', 'OPEN_ENDED')
            ->groupBy('q.question_id', 'qt.type_code')
            ->havingRaw('COUNT(qo.option_id) = 0')
            ->exists();

        if ($questionsWithoutOptions) {
            $errors['scoring'][] = 'Every active question must have a currently supported response input.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $payload = $this->content->payload($version);

        $version->update([
            'status' => 'PUBLISHED',
            'content_hash' => $this->content->hash($payload),
            'published_at' => now(),
            'published_by' => $publisherId,
        ]);
        $template->update(['status' => 'PUBLISHED']);

        return $version->fresh(['template', 'modules']);
    }
}
