<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentModuleScope;
use App\Models\PublicResponseSession;
use App\Models\Question;
use App\Models\RespondentScoreResult;
use App\Models\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RespondentSubmissionService
{
    public function __construct(private readonly ScoringService $scoring) {}

    public function submit(PublicResponseSession $session): RespondentScoreResult
    {
        return DB::transaction(function () use ($session): RespondentScoreResult {
            $assessmentId = PublicResponseSession::whereKey($session->session_id)->value('assessment_id');
            $assessment = Assessment::whereKey($assessmentId)->lockForUpdate()->with('snapshot')->firstOrFail();
            $session = PublicResponseSession::whereKey($session->session_id)->lockForUpdate()->firstOrFail();
            if ($session->submitted_at !== null) {
                return $session->scoreResult()->firstOrFail();
            }

            if ($assessment->status !== Assessment::STATUS_IN_PROGRESS) {
                throw ValidationException::withMessages(['assessment' => 'This assessment is not accepting responses.']);
            }

            $responses = Response::where('public_response_session_id', $session->session_id)
                ->orderBy('question_id')
                ->get();
            $this->assertComplete($assessment, $responses);

            $responseSnapshot = $responses->map(fn (Response $response) => [
                'response_id' => $response->response_id,
                'question_id' => $response->question_id,
                'value_option_id' => $response->value_option_id !== null ? (int) $response->value_option_id : null,
                'value_text' => $response->value_text,
                'value_numeric' => $response->value_numeric !== null ? (string) $response->value_numeric : null,
                'answered_at' => $response->answered_at?->toIso8601String(),
            ])->values()->all();
            $inputHash = hash('sha256', json_encode($responseSnapshot, JSON_THROW_ON_ERROR));
            $score = $this->scoring->scoreResponseSet($assessment, $responses->keyBy('question_id'));
            $resultHash = hash('sha256', json_encode($score, JSON_THROW_ON_ERROR));

            $result = RespondentScoreResult::create([
                'public_response_session_id' => $session->session_id,
                'assessment_id' => $assessment->assessment_id,
                'overall_score' => $score['overall_score'],
                'calibration_status' => $score['calibration_status'],
                'scoring_version' => $score['scoring_version'],
                'input_hash' => $inputHash,
                'result_hash' => $resultHash,
                'payload' => $score,
                'calculated_at' => now(),
            ]);

            $rules = $assessment->snapshot?->collection_config['respondent_eligibility_rules'] ?? [];
            $requiresReview = is_array($rules) && $rules !== [];
            $session->update([
                'submitted_at' => now(),
                'last_activity_at' => now(),
                'eligibility_status' => $requiresReview ? 'PENDING' : 'ELIGIBLE',
                'eligibility_reason' => $requiresReview ? 'Eligibility review required by the template.' : null,
                'response_snapshot' => $responseSnapshot,
                'response_snapshot_hash' => $inputHash,
            ]);

            return $result;
        });
    }

    private function assertComplete($assessment, $responses): void
    {
        $snapshot = $assessment->snapshot;
        if ($snapshot) {
            $required = collect($snapshot->payload)
                ->flatMap(fn ($module) => $module['questions'] ?? [])
                ->where('is_scored', true);
            $byQuestion = $responses->keyBy('question_id');
            $complete = $required->isNotEmpty() && $required->every(function ($question) use ($byQuestion): bool {
                $response = $byQuestion->get($question['question_id']);
                if (! $response) {
                    return false;
                }

                return match ($question['response_type'] ?? null) {
                    'OPEN_ENDED' => filled($response->value_text),
                    'NUMERIC' => $response->value_numeric !== null,
                    default => collect($question['options'] ?? [])
                        ->contains('option_id', (int) $response->value_option_id),
                };
            });

            if (! $complete) {
                throw ValidationException::withMessages(['responses' => 'Every required scored question must be answered before submission.']);
            }

            return;
        }

        $moduleIds = AssessmentModuleScope::where('assessment_id', $assessment->assessment_id)
            ->where('in_scope', true)
            ->pluck('module_id');
        $required = Question::whereIn('module_id', $moduleIds)
            ->where('is_active', true)
            ->where('is_scored', true)
            ->with(['options', 'questionType'])
            ->get();
        $byQuestion = $responses->keyBy('question_id');
        $complete = $required->isNotEmpty() && $required->every(function (Question $question) use ($byQuestion): bool {
            $response = $byQuestion->get($question->question_id);

            if (! $response) {
                return false;
            }

            return match ($question->questionType?->type_code) {
                'OPEN_ENDED' => filled($response->value_text),
                'NUMERIC' => $response->value_numeric !== null,
                default => $question->options->contains('option_id', (int) $response->value_option_id),
            };
        });
        if (! $complete) {
            throw ValidationException::withMessages(['responses' => 'Every required scored question must be answered before submission.']);
        }
    }
}
