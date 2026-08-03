<?php

namespace App\Services;

use App\Models\AssessmentModule;
use App\Models\ContentContribution;
use App\Models\ContentPublisher;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionType;
use App\Models\QuestionVersion;
use App\Support\AnswerFormat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContentContributionService
{
    public function promote(ContentContribution $contribution, ContentPublisher $publisher, int $moduleId, string $reviewerId): QuestionVersion
    {
        if ($contribution->status !== 'ACCEPTED') {
            throw ValidationException::withMessages(['status' => 'Accept the contribution before promoting it.']);
        }
        if ($publisher->verification_status === ContentPublisher::STATUS_SUSPENDED) {
            throw ValidationException::withMessages(['publisher' => 'Choose an active publisher.']);
        }

        $format = AnswerFormat::require($contribution->response_format);
        $choices = AnswerFormat::choicesFor($format, $contribution->answer_options ?? []);

        return DB::transaction(function () use ($contribution, $publisher, $moduleId, $reviewerId, $format, $choices): QuestionVersion {
            $typeId = QuestionType::where('type_code', $format['type_code'])->value('type_id');
            $prefix = AssessmentModule::findOrFail($moduleId)->module_code;
            do {
                $code = strtoupper($prefix).'.CONTRIB.'.strtoupper(Str::random(8));
            } while (Question::where('question_code', $code)->exists());

            $numeric = $format['key'] === AnswerFormat::NUMBER ? $contribution->numeric_config : null;
            $question = Question::create([
                'module_id' => $moduleId,
                'content_publisher_id' => $publisher->content_publisher_id,
                'distribution_level' => ContentPublisher::VISIBILITY_PRIVATE,
                'question_number' => ((int) Question::where('module_id', $moduleId)->max('question_number')) + 1,
                'question_code' => $code,
                'question_text' => $contribution->question_text,
                'type_id' => $typeId,
                'display_order' => ((int) Question::where('module_id', $moduleId)->max('display_order')) + 1,
                'is_active' => true,
                'is_scored' => false,
                'source' => 'EXPERT_CONTRIBUTION',
                'question_status' => 'DRAFT',
                'standard_alignment_status' => 'PENDING_REVIEW',
                'numeric_unit' => $numeric['unit'] ?? null,
                'numeric_min' => $numeric['min'] ?? null,
                'numeric_max' => $numeric['max'] ?? null,
            ]);

            $options = [];
            foreach ($choices as $index => $label) {
                $option = QuestionOption::create([
                    'question_id' => $question->question_id,
                    'option_label' => $label,
                    'option_order' => $index + 1,
                    'score_weight' => null,
                    'is_flagged_pain_point' => false,
                ]);
                $options[] = [
                    'option_id' => (int) $option->option_id,
                    'option_key' => 'OPT'.($index + 1),
                    'option_label' => $label,
                    'option_order' => $index + 1,
                    'score_weight' => null,
                    'critical_failure' => false,
                ];
            }

            $version = QuestionVersion::create([
                'question_id' => $question->question_id,
                'version_number' => 1,
                'status' => QuestionVersion::STATUS_DRAFT,
                'question_text' => $contribution->question_text,
                'type_id' => $typeId,
                'options' => $options,
                'numeric_config' => $numeric,
                'numeric_bands' => [],
                'requires_observation' => false,
                'methodology_notes' => $contribution->methodology_notes,
                'source_summary' => collect([
                    $contribution->source_authority,
                    $contribution->source_url,
                    $contribution->license_code,
                ])->filter()->join(' · '),
                'review_notes' => 'Promoted from expert contribution '.$contribution->content_contribution_id.'. Further content and scoring review required.',
                'reviewed_by' => $reviewerId,
            ]);

            $contribution->update([
                'status' => 'PROMOTED',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'promoted_question_id' => $question->question_id,
                'promoted_question_version_id' => $version->question_version_id,
            ]);

            return $version;
        });
    }
}
