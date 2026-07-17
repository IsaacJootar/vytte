<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentAggregationResult;
use App\Models\AssessmentModuleScope;
use App\Models\PublicResponseSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MultiRespondentAggregationService
{
    public const METHOD_ARITHMETIC_MEAN = 'ARITHMETIC_MEAN';

    public function __construct(
        private readonly ScoringService $scoring,
        private readonly ReportSnapshotService $reports,
    ) {}

    public function preview(Assessment $assessment): array
    {
        $assessment->loadMissing(['snapshot', 'publicResponseSessions.accessToken', 'publicResponseSessions.scoreResult']);
        $config = $this->config($assessment);
        $eligible = collect();
        $excluded = collect();

        foreach ($assessment->publicResponseSessions as $session) {
            $reason = $this->exclusionReason($session);
            if ($reason !== null) {
                $excluded->push([
                    'session_id' => $session->session_id,
                    'reason' => $reason,
                ]);
            } else {
                $eligible->push($session);
            }
        }

        $aggregate = $this->arithmeticMean($eligible);
        $references = $eligible->map(fn (PublicResponseSession $session) => [
            'session_id' => $session->session_id,
            'response_snapshot_hash' => $session->response_snapshot_hash,
            'score_result_id' => $session->scoreResult->score_result_id,
            'score_result_hash' => $session->scoreResult->result_hash,
        ])->sortBy('session_id')->values()->all();

        return [
            'aggregation_method' => $config['aggregation_method'],
            'minimum_completed_respondents' => $config['minimum_completed_respondents'],
            'eligible_respondent_count' => $eligible->count(),
            'excluded_session_count' => $excluded->count(),
            'eligible_session_references' => $references,
            'excluded_sessions' => $excluded->sortBy('session_id')->values()->all(),
            'result' => $aggregate,
            'scoring_version' => $config['scoring_profile_version'],
            'template_version_id' => $assessment->template_version_id,
            'respondent_eligibility_rules' => $config['respondent_eligibility_rules'],
        ];
    }

    public function finalize(Assessment $assessment, string $finalizerId): AssessmentAggregationResult
    {
        return DB::transaction(function () use ($assessment, $finalizerId): AssessmentAggregationResult {
            $assessment = Assessment::whereKey($assessment->assessment_id)->lockForUpdate()->firstOrFail();
            if ($assessment->status !== Assessment::STATUS_IN_PROGRESS) {
                throw ValidationException::withMessages(['assessment' => 'Only an in-progress respondent collection can be finalized.']);
            }
            if ($assessment->aggregationResult()->exists()) {
                throw ValidationException::withMessages(['assessment' => 'This respondent collection already has a final immutable result.']);
            }

            PublicResponseSession::where('assessment_id', $assessment->assessment_id)->lockForUpdate()->get();
            $preview = $this->preview($assessment->fresh());
            if ($preview['eligible_respondent_count'] < $preview['minimum_completed_respondents']) {
                throw ValidationException::withMessages([
                    'respondents' => "At least {$preview['minimum_completed_respondents']} eligible completed respondents are required before finalization.",
                ]);
            }

            $result = $preview['result'];
            $inputHash = hash('sha256', json_encode([
                'method' => $preview['aggregation_method'],
                'minimum' => $preview['minimum_completed_respondents'],
                'eligible_sessions' => $preview['eligible_session_references'],
                'excluded_sessions' => $preview['excluded_sessions'],
            ], JSON_THROW_ON_ERROR));
            $resultHash = hash('sha256', json_encode($result, JSON_THROW_ON_ERROR));
            $finalizedAt = now();

            $aggregation = AssessmentAggregationResult::create([
                'assessment_id' => $assessment->assessment_id,
                'aggregation_method' => $preview['aggregation_method'],
                'minimum_completed_respondents' => $preview['minimum_completed_respondents'],
                'eligible_respondent_count' => $preview['eligible_respondent_count'],
                'excluded_session_count' => $preview['excluded_session_count'],
                'overall_score' => $result['overall_score'],
                'calibration_status' => $result['calibration_status'],
                'scoring_version' => $result['scoring_version'],
                'input_hash' => $inputHash,
                'result_hash' => $resultHash,
                'payload' => array_merge($preview, [
                    'input_hash' => $inputHash,
                    'result_hash' => $resultHash,
                    'finalized_at' => $finalizedAt->toIso8601String(),
                    'finalized_by' => $finalizerId,
                ]),
                'finalized_by' => $finalizerId,
                'finalized_at' => $finalizedAt,
            ]);

            $this->scoring->persistResult($assessment, $result, 'AGGREGATE');
            $assessment->update([
                'status' => Assessment::STATUS_COMPLETE,
                'completed_at' => $finalizedAt,
            ]);
            AssessmentModuleScope::where('assessment_id', $assessment->assessment_id)
                ->where('in_scope', true)
                ->update([
                    'status' => AssessmentModuleScope::STATUS_COMPLETED,
                    'completed_at' => $finalizedAt,
                ]);
            $this->reports->createFor($assessment->fresh());
            app(AuditService::class)->record(
                'assessment.multi_respondent.finalized',
                $assessment,
                ['status' => Assessment::STATUS_IN_PROGRESS],
                [
                    'status' => Assessment::STATUS_COMPLETE,
                    'aggregation_result_id' => $aggregation->aggregation_result_id,
                    'eligible_respondent_count' => $aggregation->eligible_respondent_count,
                    'excluded_session_count' => $aggregation->excluded_session_count,
                    'input_hash' => $aggregation->input_hash,
                    'result_hash' => $aggregation->result_hash,
                ],
                userId: $finalizerId,
            );

            return $aggregation;
        });
    }

    private function config(Assessment $assessment): array
    {
        $config = $assessment->snapshot?->collection_config ?? [];
        if (! ($config['allows_multi_respondent'] ?? false)) {
            throw ValidationException::withMessages(['assessment' => 'This template does not allow multi-respondent collection.']);
        }
        if (($config['aggregation_method'] ?? null) !== self::METHOD_ARITHMETIC_MEAN) {
            throw ValidationException::withMessages(['assessment' => 'This assessment uses an unsupported aggregation method.']);
        }
        if (($config['minimum_completed_respondents'] ?? 0) < 1) {
            throw ValidationException::withMessages(['assessment' => 'This assessment has no valid respondent threshold.']);
        }
        if (($config['scoring_profile_version'] ?? null) !== ScoringService::ALGORITHM_VERSION) {
            throw ValidationException::withMessages(['assessment' => 'This assessment uses an unsupported frozen scoring profile version.']);
        }

        return [
            'aggregation_method' => self::METHOD_ARITHMETIC_MEAN,
            'minimum_completed_respondents' => (int) $config['minimum_completed_respondents'],
            'respondent_eligibility_rules' => $config['respondent_eligibility_rules'] ?? [],
            'scoring_profile_version' => $config['scoring_profile_version'],
        ];
    }

    private function exclusionReason(PublicResponseSession $session): ?string
    {
        if ($session->submitted_at === null) {
            return 'INCOMPLETE_SESSION';
        }
        if ($session->is_test) {
            return 'TEST_SESSION';
        }
        if ($session->eligibility_status !== 'ELIGIBLE') {
            return $session->eligibility_status === 'EXCLUDED'
                ? 'INELIGIBLE: '.($session->eligibility_reason ?: 'No reason recorded')
                : 'ELIGIBILITY_NOT_CONFIRMED';
        }
        if ($session->accessToken?->revoked_at !== null) {
            return 'ACCESS_TOKEN_REVOKED';
        }
        if ($session->accessToken?->expires_at?->isPast()) {
            return 'ACCESS_TOKEN_EXPIRED';
        }
        if (! $session->accessToken) {
            return 'ACCESS_TOKEN_MISSING';
        }
        if (! $session->scoreResult) {
            return 'RESPONDENT_SCORE_MISSING';
        }
        $snapshotHash = hash('sha256', json_encode($session->response_snapshot, JSON_THROW_ON_ERROR));
        if (! hash_equals((string) $session->response_snapshot_hash, $snapshotHash)) {
            return 'RESPONSE_SNAPSHOT_INTEGRITY_MISMATCH';
        }
        if (! hash_equals((string) $session->response_snapshot_hash, (string) $session->scoreResult->input_hash)) {
            return 'RESPONSE_SCORE_INTEGRITY_MISMATCH';
        }
        $scoreHash = hash('sha256', json_encode($session->scoreResult->payload, JSON_THROW_ON_ERROR));
        if (! hash_equals((string) $session->scoreResult->result_hash, $scoreHash)) {
            return 'SCORE_RESULT_INTEGRITY_MISMATCH';
        }

        return null;
    }

    private function arithmeticMean(Collection $sessions): array
    {
        $scorePayloads = $sessions->pluck('scoreResult.payload');
        $subIndexIds = $scorePayloads->flatMap(fn ($payload) => collect($payload['sub_indices'] ?? [])->pluck('sub_index_id'))->unique();
        $domainIds = $scorePayloads->flatMap(fn ($payload) => collect($payload['domains'] ?? [])->pluck('domain_id'))->unique();

        $subIndices = $subIndexIds->map(function ($id) use ($scorePayloads, $sessions) {
            $values = $scorePayloads->map(fn ($payload) => collect($payload['sub_indices'] ?? [])->firstWhere('sub_index_id', $id)['score'] ?? null)
                ->filter(fn ($score) => $score !== null);

            return [
                'sub_index_id' => (int) $id,
                'domain_id' => (int) (collect($scorePayloads->first()['sub_indices'] ?? [])->firstWhere('sub_index_id', $id)['domain_id'] ?? 0),
                'score' => $values->isEmpty() ? null : round($values->avg(), 2),
                'calibration_status' => $values->count() === $sessions->count() ? 'CALIBRATED' : ($values->isEmpty() ? 'NOT_CALIBRATED' : 'PARTIAL'),
            ];
        })->values()->all();

        $domains = $domainIds->map(function ($id) use ($scorePayloads, $sessions) {
            $values = $scorePayloads->map(fn ($payload) => collect($payload['domains'] ?? [])->firstWhere('domain_id', $id)['score'] ?? null)
                ->filter(fn ($score) => $score !== null);

            return [
                'domain_id' => (int) $id,
                'score' => $values->isEmpty() ? null : round($values->avg(), 2),
                'calibration_status' => $values->count() === $sessions->count() ? 'CALIBRATED' : ($values->isEmpty() ? 'NOT_CALIBRATED' : 'PARTIAL'),
            ];
        })->values()->all();

        $overallValues = $scorePayloads->pluck('overall_score')->filter(fn ($score) => $score !== null);

        return [
            'sub_indices' => $subIndices,
            'domains' => $domains,
            'overall_score' => $overallValues->isEmpty() ? null : round($overallValues->avg(), 2),
            'calibration_status' => $overallValues->count() === $sessions->count()
                ? 'CALIBRATED'
                : ($overallValues->isEmpty() ? 'NOT_CALIBRATED' : 'PARTIAL'),
            'scoring_version' => ScoringService::ALGORITHM_VERSION,
        ];
    }
}
