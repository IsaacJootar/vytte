<?php

namespace App\Services;

use App\Models\AssessmentTemplate;
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

        if ($version->allows_multi_respondent) {
            if (($version->minimum_completed_respondents ?? 0) < 1) {
                $errors['minimum_completed_respondents'][] = 'Multi-respondent templates require a minimum completed respondent threshold.';
            }
            if ($version->aggregation_method !== 'ARITHMETIC_MEAN') {
                $errors['aggregation_method'][] = 'Arithmetic mean is the only currently supported multi-respondent aggregation method.';
            }
            if ($version->respondent_eligibility_rules !== null && ! is_array($version->respondent_eligibility_rules)) {
                $errors['respondent_eligibility_rules'][] = 'Respondent eligibility rules must be a structured list.';
            }
        } elseif ($version->minimum_completed_respondents !== null || $version->aggregation_method !== null) {
            $errors['collection'][] = 'Respondent thresholds and aggregation methods require multi-respondent collection to be enabled.';
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

        $moduleIds = $modules->pluck('module_id');
        $modulesWithoutQuestions = $modules->filter(fn ($module) => ! $module->questions->contains('is_active', true));
        if ($modulesWithoutQuestions->isNotEmpty()) {
            $errors['questions'][] = 'Every assessment area must contain at least one active question.';
        }

        $unsupportedTypes = DB::table('questions as q')
            ->join('question_types as qt', 'qt.type_id', '=', 'q.type_id')
            ->whereIn('q.module_id', $moduleIds)
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
            ->whereIn('q.module_id', $moduleIds)
            ->where('q.is_active', true)
            ->where('qt.type_code', '!=', 'OPEN_ENDED')
            ->groupBy('q.question_id', 'qt.type_code')
            ->havingRaw('COUNT(qo.option_id) = 0')
            ->exists();

        if ($questionsWithoutOptions) {
            $errors['scoring'][] = 'Every active question must have a currently supported response input.';
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
            $errors['scoring'][] = 'Every scored question must belong to the template scoring profile.';
        }

        $scoredOptionsWithoutWeight = DB::table('questions as q')
            ->join('question_types as qt', 'qt.type_id', '=', 'q.type_id')
            ->join('question_options as qo', 'qo.question_id', '=', 'q.question_id')
            ->whereIn('q.module_id', $moduleIds)
            ->where('q.is_active', true)
            ->where('q.is_scored', true)
            ->where('qt.type_code', '!=', 'OPEN_ENDED')
            ->whereNull('qo.score_weight')
            ->exists();
        if ($scoredOptionsWithoutWeight) {
            $errors['scoring'][] = 'Every option on a scored question must have a score weight.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $payload = $this->content->payload($version);

        $version->update([
            'status' => AssessmentTemplateVersion::STATUS_PUBLISHED,
            'scoring_version' => ScoringService::ALGORITHM_VERSION,
            'content_hash' => $this->content->hash($payload),
            'published_payload' => $payload,
            'published_at' => now(),
            'published_by' => $publisherId,
        ]);
        $template->update(['status' => AssessmentTemplate::STATUS_PUBLISHED]);

        $published = $version->fresh(['template', 'modules']);
        app(AuditService::class)->record(
            'template.version.published',
            $published,
            ['status' => AssessmentTemplateVersion::STATUS_DRAFT],
            ['status' => AssessmentTemplateVersion::STATUS_PUBLISHED, 'content_hash' => $published->content_hash],
            userId: $publisherId,
        );

        return $published;
    }
}
