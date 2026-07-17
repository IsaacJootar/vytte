<?php

namespace App\Services;

use App\Models\AssessmentTemplateVersion;

class TemplateContentService
{
    public function payload(AssessmentTemplateVersion $version, ?array $moduleIds = null): array
    {
        $version->load([
            'modules.questions.options.translations',
            'modules.questions.numericBands',
            'modules.questions.translations',
            'modules.questions.questionType',
            'modules.questions.moduleDomain',
            'modules.subIndices.domain',
            'modules.subIndices.questions',
        ]);
        $modules = $version->modules;

        if ($moduleIds !== null) {
            $modules = $modules->whereIn('module_id', $moduleIds);
        }

        return $modules->map(function ($module): array {
            $activeQuestions = $module->questions->where('is_active', true)->values();
            $activeQuestionIds = $activeQuestions->pluck('question_id');

            return [
                'module_id' => $module->module_id,
                'module_code' => $module->module_code,
                'module_name' => $module->module_name,
                'requires_consent' => (bool) $module->requires_consent,
                'display_order' => $module->pivot->display_order,
                'area_label' => $module->pivot->area_label,
                'questions' => $activeQuestions->map(fn ($question) => [
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
                    'numeric_config' => $question->questionType?->type_code === 'NUMERIC' ? [
                        'unit' => $question->numeric_unit,
                        'min' => $question->numeric_min !== null ? (float) $question->numeric_min : null,
                        'max' => $question->numeric_max !== null ? (float) $question->numeric_max : null,
                        'step' => $question->numeric_step !== null ? (float) $question->numeric_step : null,
                    ] : null,
                    'numeric_bands' => $question->numericBands->map(fn ($band) => [
                        'min_value' => $band->min_value !== null ? (float) $band->min_value : null,
                        'max_value' => $band->max_value !== null ? (float) $band->max_value : null,
                        'score_weight' => (float) $band->score_weight,
                        'band_order' => $band->band_order,
                    ])->values()->all(),
                    'options' => $question->options->map(fn ($option) => [
                        'option_id' => $option->option_id,
                        'option_label' => $option->option_label,
                        'translations' => $option->translations->pluck('option_label', 'locale')->all(),
                        'option_order' => $option->option_order,
                        'score_weight' => $option->score_weight,
                    ])->values()->all(),
                ])->values()->all(),
                'scoring_profile' => $module->subIndices->map(fn ($subIndex) => [
                    'sub_index_id' => $subIndex->sub_index_id,
                    'acronym' => $subIndex->acronym,
                    'full_name' => $subIndex->full_name,
                    'domain_id' => $subIndex->domain_id,
                    'domain_code' => $subIndex->domain?->domain_code,
                    'domain_name' => $subIndex->domain?->domain_name,
                    'domain_display_order' => $subIndex->domain?->display_order,
                    'questions' => $subIndex->questions->whereIn('question_id', $activeQuestionIds)->map(fn ($question) => [
                        'question_id' => $question->question_id,
                        'weight' => (float) ($question->pivot->weight ?? 1.0),
                    ])->values()->all(),
                ])->values()->all(),
            ];
        })->values()->all();
    }

    public function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
