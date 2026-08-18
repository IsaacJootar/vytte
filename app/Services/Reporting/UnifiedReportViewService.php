<?php

namespace App\Services\Reporting;

use App\Models\Assessment;
use App\Models\Response;

class UnifiedReportViewService
{
    /** @return array<string, mixed> */
    public function compose(Assessment $assessment, array $payload): array
    {
        $questions = collect($assessment->snapshot?->payload ?? [])
            ->flatMap(fn ($module) => $module['questions'] ?? [])
            ->unique('question_id')
            ->values();
        $isAggregate = (bool) ($payload['respondent_collection']['is_multi_respondent'] ?? false);
        $responses = $isAggregate
            ? collect()
            : Response::where('assessment_id', $assessment->assessment_id)
                ->whereNull('respondent_id')
                ->whereNull('public_response_session_id')
                ->get()
                ->keyBy(fn ($response) => (string) $response->question_id);

        $commonCore = $this->commonCore($assessment, $questions, collect($payload['domain_scores'] ?? []), $payload, $isAggregate);
        $contextItems = [];
        $qualitativeItems = [];
        if (! $isAggregate) {
            foreach ($questions as $question) {
                if (($question['is_scored'] ?? false) && ($question['score_role'] ?? 'PRIMARY') !== 'CONTEXT') {
                    continue;
                }
                $response = $responses->get((string) $question['question_id']);
                if (! $response) {
                    continue;
                }
                $item = [
                    'question_id' => $question['question_id'],
                    'question_text' => $question['question_text'],
                    'section_name' => $question['section_name'] ?? null,
                    'response_state' => $response->response_state ?? 'ANSWERED',
                    'answer' => $this->displayAnswer($response, $question),
                    'evidence_note' => $response->evidence_note,
                ];
                if (($question['response_type'] ?? null) === 'OPEN_ENDED') {
                    $qualitativeItems[] = $item;
                } else {
                    $contextItems[] = $item;
                }
            }
        }

        $answeredStates = $responses->map(fn ($response) => $response->response_state ?? 'ANSWERED')->countBy();
        $missingCount = max(0, $questions->count() - $responses->count());
        $limitations = [];
        if (($payload['score']['calibration_status'] ?? 'NOT_CALIBRATED') !== 'CALIBRATED') {
            $limitations[] = 'The primary result is '.$this->plainStatus($payload['score']['calibration_status'] ?? 'NOT_CALIBRATED').'; interpret it with the disclosed response coverage.';
        }
        if ($commonCore['comparison_eligibility']['eligible'] === false) {
            $limitations[] = $commonCore['comparison_eligibility']['reason'];
        }
        if ($isAggregate) {
            $limitations[] = 'Individual respondent answers and item-level issue traces are intentionally excluded from the finalized aggregate report.';
        }
        $issueRegister = collect($payload['intelligence']['findings'] ?? [])
            ->flatMap(function ($finding) {
                return collect($finding['failed_indicators'] ?? [])->map(fn ($indicator) => [
                    'issue_key' => $indicator['issue_key'] ?? hash('sha256', (string) ($indicator['question_id'] ?? $indicator['question_text'] ?? 'issue')),
                    'finding_key' => $finding['finding_key'] ?? null,
                    'measurement_domain' => $finding['measurement_domain'] ?? null,
                    'subject' => $finding['subject'] ?? null,
                    'severity' => ($indicator['critical_failure'] ?? false) ? 'CRITICAL' : ($finding['severity'] ?? 'MEDIUM'),
                    'question_text' => $indicator['question_text'],
                    'recorded_answer' => $indicator['answer'] ?? null,
                    'evidence_note' => $indicator['evidence_note'] ?? null,
                    'item_score' => $indicator['score'] ?? null,
                    'critical_failure' => (bool) ($indicator['critical_failure'] ?? false),
                    'status' => 'OPEN',
                ]);
            })->unique('issue_key')->values()->all();

        return [
            'primary' => [
                'label' => 'Primary instrument result',
                'value' => $payload['score']['overall_score'] ?? null,
                'construct' => collect($payload['scoring_models'] ?? [])->pluck('construct')->filter()->unique()->values()->join(', ') ?: 'Readiness',
                'direction' => collect($payload['scoring_models'] ?? [])->pluck('direction')->filter()->unique()->values()->join(', ') ?: 'Higher is better',
                'source_items' => $questions->where('is_scored', true)->count(),
                'formula' => 'Frozen publisher scoring model; weighted results normalized to 0–100.',
                'missing_policy' => collect($payload['scoring_models'] ?? [])->pluck('missing_policy')->filter()->values()->all(),
                'calibration_state' => $payload['score']['calibration_status'] ?? 'NOT_CALIBRATED',
                'comparison_eligibility' => [
                    'eligible' => filled($payload['comparison_signature'] ?? null),
                    'signature' => $payload['comparison_signature'] ?? null,
                    'reason' => filled($payload['comparison_signature'] ?? null)
                        ? 'Can be compared with any other report that used the exact same questions and scoring.'
                        : 'This report predates our comparison tracking, so we can\'t confirm it\'s safe to compare against newer reports.',
                ],
            ],
            'common_core' => $commonCore,
            'context' => [
                'label' => 'Context and needs',
                'scored' => false,
                'items' => $contextItems,
                'available_item_count' => $questions->filter(fn ($question) => ! ($question['is_scored'] ?? false) && ($question['response_type'] ?? null) !== 'OPEN_ENDED')->count(),
            ],
            'qualitative' => [
                'label' => 'Qualitative findings',
                'scored' => false,
                'items' => $qualitativeItems,
                'available_item_count' => $questions->where('response_type', 'OPEN_ENDED')->count(),
            ],
            'critical_findings' => collect($payload['intelligence']['findings'] ?? [])->where('category', 'CRITICAL_FINDING')->values()->all(),
            'issue_register' => $issueRegister,
            'response_coverage' => [
                'expected_items' => $questions->count(),
                'recorded_items' => $isAggregate ? null : $responses->count(),
                'missing_items' => $isAggregate ? null : $missingCount,
                'states' => $answeredStates->all(),
                'aggregate_privacy_protected' => $isAggregate,
            ],
            'limitations' => array_values(array_unique($limitations)),
        ];
    }

    private function commonCore(Assessment $assessment, $questions, $domains, array $payload, bool $isAggregate): array
    {
        $core = $questions->where('score_role', 'COMMON_CORE');
        $coreIds = $core->pluck('framework_question_placement_id')->filter()->map(fn ($id) => (string) $id)->flip();
        $traces = $domains->flatMap(fn ($domain) => $domain['contributing_question_trace'] ?? [])
            ->filter(fn ($trace) => $coreIds->has((string) ($trace['framework_question_placement_id'] ?? '')))
            ->unique(fn ($trace) => (string) ($trace['framework_question_placement_id'] ?? $trace['question_id'] ?? ''));
        $weight = $traces->sum(fn ($trace) => (float) ($trace['weight'] ?? 1));
        $score = $weight > 0
            ? round($traces->sum(fn ($trace) => (float) $trace['score'] * (float) ($trace['weight'] ?? 1)) / $weight, 2)
            : null;
        $defined = $core->isNotEmpty();
        $signature = $payload['comparison_signature'] ?? null;
        $benchmarkApproved = collect($assessment->snapshot?->payload ?? [])->isNotEmpty()
            && collect($assessment->snapshot?->payload ?? [])->every(fn ($module) => collect($module['governance_claims'] ?? [])->contains(
                fn ($claim) => ($claim['claim_type'] ?? null) === 'BENCHMARK_APPROVED' && ($claim['status'] ?? null) === 'PASSED'
            ));
        $eligible = $defined && ! $isAggregate && $benchmarkApproved && filled($signature) && $score !== null;
        $reason = match (true) {
            ! $defined => 'This instrument does not define a shared core set of questions.',
            $isAggregate => 'This is a combined report, so it has no separate core-questions score to compare.',
            ! $benchmarkApproved => 'This core question set has not yet been approved as a benchmark.',
            $score === null => 'Not enough of the core questions were answered to calculate a score.',
            default => 'Can be compared with any other report using the same core question set.',
        };

        return [
            'label' => 'Common-core result',
            'value' => $score,
            'construct' => 'Shared anchor performance',
            'direction' => 'Higher is better',
            'source_items' => $core->count(),
            'formula' => $defined ? 'Weighted mean of frozen COMMON_CORE item rules, normalized to 0–100.' : null,
            'missing_policy' => 'Not-applicable items are excluded; other missing core items make the result partial.',
            'calibration_state' => ! $defined ? 'NOT_DEFINED' : ($isAggregate ? 'NOT_AVAILABLE' : ($traces->count() === $core->count() ? 'CALIBRATED' : ($traces->isEmpty() ? 'NOT_CALIBRATED' : 'PARTIAL'))),
            'comparison_eligibility' => [
                'eligible' => $eligible,
                'signature' => $defined ? $signature : null,
                'reason' => $reason,
            ],
        ];
    }

    private function displayAnswer(Response $response, array $question): ?string
    {
        if (($response->response_state ?? 'ANSWERED') !== 'ANSWERED') {
            return $this->plainStatus($response->response_state);
        }

        return match ($question['response_type'] ?? null) {
            'OPEN_ENDED' => $response->value_text,
            'NUMERIC' => trim((string) $response->value_numeric.' '.($question['numeric_config']['unit'] ?? '')),
            'MULTI_SELECT' => collect($question['options'] ?? [])
                ->whereIn('option_id', $response->typed_value['option_ids'] ?? [])
                ->pluck('option_label')->join(', '),
            default => collect($question['options'] ?? [])->firstWhere('option_id', (int) $response->value_option_id)['option_label'] ?? null,
        };
    }

    private function plainStatus(?string $status): string
    {
        return str((string) $status)->replace('_', ' ')->lower()->toString();
    }
}
