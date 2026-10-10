<?php

namespace App\Services\Reporting;

use App\Models\Assessment;
use App\Models\PublicResponseSession;
use App\Services\MultiRespondentAggregationService;
use App\Support\ReportType;

/**
 * Programme outcome indicators from a survey: "what share of the girls and women gave this answer".
 *
 * Reads only what is already frozen: the question and option wording in the assessment snapshot,
 * the template's indicator definitions (the `report.indicators` policy), and the immutable response
 * snapshots of the respondents who counted. Respondents counted are exactly the ones the survey's own
 * result counts (`MultiRespondentAggregationService`), so the indicators and the headline score can
 * never disagree about who took part.
 *
 * A respondent who gave no direct answer to a question (cannot answer, not applicable, prefers not to
 * say) is left out of that indicator's total rather than counted as a "no". The number of respondents
 * behind every figure is returned with it so a small group is never presented as strong evidence.
 */
class ProgrammeIndicatorService
{
    public function __construct(private readonly MultiRespondentAggregationService $aggregation) {}

    /**
     * The indicator results for one survey assessment.
     *
     * @return array{respondents: int, enough: bool, minimum: int, indicators: array<int, array<string, mixed>>}
     */
    public function forAssessment(Assessment $assessment): array
    {
        $assessment->loadMissing(['snapshot', 'aggregationResult']);
        $config = ReportType::config($assessment);
        $minimum = (int) ($config['min_respondents_to_report'] ?? 10);

        $sessions = $this->eligibleSessions($assessment);
        $questions = $this->questionMap($assessment);

        $rows = [];
        foreach ((array) ($config['indicators'] ?? []) as $indicator) {
            $rows[] = $this->compute($indicator, $sessions, $questions);
        }

        return [
            'respondents' => $sessions->count(),
            'enough' => $sessions->count() >= $minimum,
            'minimum' => $minimum,
            'indicators' => $rows,
        ];
    }

    /**
     * The same results with the earlier comparable survey for this school or PHC alongside, so the
     * report can show before and after. "Comparable" is decided by ComparisonSeriesService, the same
     * rule Progress and Portfolio use.
     *
     * @return array<string, mixed>
     */
    public function withComparison(Assessment $assessment): array
    {
        $current = $this->forAssessment($assessment);
        $previous = app(ComparisonSeriesService::class)
            ->seriesMatching($assessment->project, $assessment)
            ->where('assessment_id', '!=', $assessment->assessment_id)
            ->filter(fn (Assessment $other) => $other->completed_at !== null
                && ($assessment->completed_at === null || $other->completed_at->lt($assessment->completed_at)))
            ->last();

        $current['previous'] = null;
        if ($previous) {
            $before = $this->forAssessment($previous);
            $current['previous'] = [
                'assessment_id' => $previous->assessment_id,
                'completed_at' => $previous->completed_at,
                'respondents' => $before['respondents'],
            ];
            $beforeByCode = collect($before['indicators'])->keyBy('code');

            foreach ($current['indicators'] as &$row) {
                $old = $beforeByCode->get($row['code']);
                $row['before'] = $old && $old['reportable'] ? $old['value'] : null;
                $row['change'] = ($row['before'] !== null && $row['value'] !== null) ? round($row['value'] - $row['before'], 1) : null;
                $row['improved'] = $row['change'] === null ? null : $this->improved($row['direction'], $row['change']);
            }
            unset($row);
        }

        return $current;
    }

    /**
     * Pool several surveys (one per school or PHC) into one set of figures. Counts are added up, not
     * averaged, so a school with more respondents weighs more, exactly as it would in one big survey.
     *
     * @param  array<int, array<string, mixed>>  $results  each the output of forAssessment()
     * @return array<int, array<string, mixed>>
     */
    public function pool(array $results): array
    {
        $pooled = [];
        foreach ($results as $result) {
            foreach ($result['indicators'] as $row) {
                $slot = &$pooled[$row['code']];
                $slot ??= $row + ['hits_total' => 0, 'n_total' => 0, 'sum_total' => 0.0];
                if ($row['value'] !== null) {
                    $slot['n_total'] += $row['n'];
                    $slot['hits_total'] += $row['hits'];
                    $slot['sum_total'] += ($row['sum'] ?? 0);
                }
                unset($slot);
            }
        }

        return collect($pooled)->map(function (array $row): array {
            $isMean = ($row['kind'] ?? 'PERCENT') === 'KNOWLEDGE';
            $row['n'] = $row['n_total'];
            $row['value'] = $row['n_total'] > 0
                ? round($isMean ? $row['sum_total'] / $row['n_total'] : $row['hits_total'] / $row['n_total'] * 100, 1)
                : null;
            $row['hits'] = $row['hits_total'];
            $row['reportable'] = $row['n_total'] > 0;

            return $row;
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $indicator
     * @param  \Illuminate\Support\Collection<int, PublicResponseSession>  $sessions
     * @param  array<string, array<string, mixed>>  $questions  keyed by question code
     * @return array<string, mixed>
     */
    private function compute(array $indicator, $sessions, array $questions): array
    {
        $isKnowledge = ($indicator['kind'] ?? 'PERCENT') === 'KNOWLEDGE';
        $n = 0;
        $hits = 0;
        $sum = 0.0;

        foreach ($sessions as $session) {
            $answers = $this->answersBySession($session, $questions);

            if ($isKnowledge) {
                $score = $this->knowledgeScore((array) ($indicator['questions'] ?? []), $answers, $questions);
                if ($score !== null) {
                    $n++;
                    $sum += $score;
                }

                continue;
            }

            $outcome = $this->respondentOutcome($indicator, $answers);
            if ($outcome !== null) {
                $n++;
                $hits += $outcome ? 1 : 0;
            }
        }

        $value = $n === 0 ? null : round($isKnowledge ? $sum / $n : $hits / $n * 100, 1);

        return [
            'code' => $indicator['code'],
            'outcome' => $indicator['outcome'] ?? null,
            'label' => $indicator['label'],
            'direction' => strtoupper((string) ($indicator['direction'] ?? 'UP')),
            'kind' => $isKnowledge ? 'KNOWLEDGE' : 'PERCENT',
            'value' => $value,
            'n' => $n,
            'hits' => $hits,
            'sum' => $sum,
            'reportable' => $value !== null,
        ];
    }

    /**
     * Did this respondent meet the indicator? null when the answers given cannot tell either way.
     *
     * @param  array<string, string>  $answers  option label chosen, keyed by question code
     */
    private function respondentOutcome(array $indicator, array $answers): ?bool
    {
        $tests = (array) ($indicator['tests'] ?? []);
        if ($tests === []) {
            return null;
        }

        $results = [];
        foreach ($tests as $test) {
            $given = $answers[$test['question']] ?? null;
            $results[] = $given === null ? null : in_array($given, (array) $test['answers'], true);
        }

        if (($indicator['mode'] ?? 'ALL') === 'ANY') {
            if (in_array(true, $results, true)) {
                return true;
            }

            return in_array(null, $results, true) ? null : false;
        }

        if (in_array(null, $results, true)) {
            return null;
        }

        return ! in_array(false, $results, true);
    }

    /**
     * Share of the knowledge questions this respondent got right (the answer worth full marks).
     *
     * @param  array<int, string>  $codes
     * @param  array<string, string>  $answers
     * @param  array<string, array<string, mixed>>  $questions
     */
    private function knowledgeScore(array $codes, array $answers, array $questions): ?float
    {
        $answered = 0;
        $correct = 0;
        foreach ($codes as $code) {
            if (! isset($answers[$code])) {
                continue;
            }
            $answered++;
            $best = $questions[$code]['correct'] ?? null;
            $correct += ($best !== null && $answers[$code] === $best) ? 1 : 0;
        }

        return $answered === 0 ? null : $correct / $answered * 100;
    }

    /**
     * The option label each question was answered with by one respondent, keyed by question code.
     * Only real answers appear: a "cannot answer" style state leaves the question out.
     *
     * @param  array<string, array<string, mixed>>  $questions
     * @return array<string, string>
     */
    private function answersBySession(PublicResponseSession $session, array $questions): array
    {
        $byId = [];
        foreach ($questions as $code => $question) {
            $byId[$question['question_id']] = $code;
        }

        $answers = [];
        foreach ((array) $session->response_snapshot as $response) {
            if (($response['response_state'] ?? 'ANSWERED') !== 'ANSWERED' || ($response['value_option_id'] ?? null) === null) {
                continue;
            }
            $code = $byId[$response['question_id']] ?? null;
            $label = $code ? ($questions[$code]['options'][(int) $response['value_option_id']] ?? null) : null;
            if ($label !== null) {
                $answers[$code] = $label;
            }
        }

        return $answers;
    }

    /**
     * Question wording from the frozen snapshot: option label by option id, and the full-marks answer.
     *
     * @return array<string, array<string, mixed>>
     */
    private function questionMap(Assessment $assessment): array
    {
        $map = [];
        foreach ((array) $assessment->snapshot?->payload as $module) {
            foreach ((array) ($module['questions'] ?? []) as $question) {
                $options = [];
                $correct = null;
                foreach ((array) ($question['options'] ?? []) as $option) {
                    $options[(int) $option['option_id']] = $option['option_label'] ?? null;
                    if ((float) ($option['score_weight'] ?? 0) >= 100) {
                        $correct = $option['option_label'] ?? null;
                    }
                }
                $map[$question['question_code']] = [
                    'question_id' => $question['question_id'],
                    'options' => $options,
                    'correct' => $correct,
                ];
            }
        }

        return $map;
    }

    /** @return \Illuminate\Support\Collection<int, PublicResponseSession> */
    public function eligibleSessions(Assessment $assessment)
    {
        $ids = $assessment->aggregationResult
            ? collect($assessment->aggregationResult->payload['eligible_session_references'] ?? [])->pluck('session_id')
            : collect($this->aggregation->preview($assessment)['eligible_session_references'])->pluck('session_id');

        return PublicResponseSession::whereIn('session_id', $ids->all())->get();
    }

    private function improved(string $direction, float $change): ?bool
    {
        if (abs($change) < 0.05) {
            return null;
        }

        return $direction === 'DOWN' ? $change < 0 : $change > 0;
    }
}
