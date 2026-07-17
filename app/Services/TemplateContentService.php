<?php

namespace App\Services;

use App\Models\AssessmentTemplateVersion;

class TemplateContentService
{
    public function payload(AssessmentTemplateVersion $version, ?array $moduleIds = null): array
    {
        $version->load([
            'modules.questions.options.translations',
            'modules.questions.translations',
            'modules.questions.questionType',
            'modules.questions.moduleDomain',
        ]);
        $modules = $version->modules;

        if ($moduleIds !== null) {
            $modules = $modules->whereIn('module_id', $moduleIds);
        }

        return $modules->map(fn ($module) => [
            'module_id' => $module->module_id,
            'module_code' => $module->module_code,
            'module_name' => $module->module_name,
            'display_order' => $module->pivot->display_order,
            'area_label' => $module->pivot->area_label,
            'questions' => $module->questions->map(fn ($question) => [
                'question_id' => $question->question_id,
                'question_code' => $question->question_code,
                'question_text' => $question->question_text,
                'translations' => $question->translations->pluck('question_text', 'locale')->all(),
                'response_type' => $question->questionType?->type_code,
                'display_order' => $question->display_order,
                'domain_label' => $question->moduleDomain?->domain_label,
                'domain_number' => $question->moduleDomain?->domain_number,
                'is_scored' => $question->is_scored,
                'requires_observation' => $question->requires_observation,
                'options' => $question->options->map(fn ($option) => [
                    'option_id' => $option->option_id,
                    'option_label' => $option->option_label,
                    'translations' => $option->translations->pluck('option_label', 'locale')->all(),
                    'option_order' => $option->option_order,
                    'score_weight' => $option->score_weight,
                ])->values()->all(),
            ])->values()->all(),
        ])->values()->all();
    }

    public function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
