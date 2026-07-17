<?php

namespace App\Services;

use App\Models\AssessmentTemplateVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TemplatePublishingService
{
    private const SUPPORTED_RESPONSE_TYPES = ['SINGLE_SELECT', 'LIKERT', 'OPEN_ENDED'];

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

        $payload = $modules->map(fn ($module) => [
            'module_id' => $module->module_id,
            'module_code' => $module->module_code,
            'module_name' => $module->module_name,
            'display_order' => $module->pivot->display_order,
            'area_label' => $module->pivot->area_label,
            'questions' => $module->questions->map(fn ($question) => [
                'question_id' => $question->question_id,
                'question_text' => $question->question_text,
                'response_type' => $question->questionType?->type_code,
                'display_order' => $question->display_order,
                'is_scored' => $question->is_scored,
                'options' => $question->options->map(fn ($option) => [
                    'option_id' => $option->option_id,
                    'option_label' => $option->option_label,
                    'option_order' => $option->option_order,
                    'score_weight' => $option->score_weight,
                ])->values()->all(),
            ])->values()->all(),
        ])->values()->all();

        $version->update([
            'status' => 'PUBLISHED',
            'content_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'published_at' => now(),
            'published_by' => $publisherId,
        ]);
        $template->update(['status' => 'PUBLISHED']);

        return $version->fresh(['template', 'modules']);
    }
}
