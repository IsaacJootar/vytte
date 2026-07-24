<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentReportSnapshot;
use App\Services\Reporting\ReportComposer;
use Illuminate\Support\Facades\DB;

class ReportSnapshotService
{
    public const SCHEMA_VERSION = 'vytte-report-1.0';

    /** A contributing question scoring below this is treated as a failed indicator. */
    private const WEAK_INDICATOR = 45.0;

    public function createFor(Assessment $assessment): AssessmentReportSnapshot
    {
        if ($assessment->status !== Assessment::STATUS_COMPLETE) {
            throw new \LogicException('Only completed assessments can have a final report snapshot.');
        }

        $existing = $assessment->reportSnapshot()->first();
        if ($existing) {
            return $existing;
        }

        $payload = $this->buildPayload($assessment);

        $snapshot = AssessmentReportSnapshot::create([
            'assessment_id' => $assessment->assessment_id,
            'schema_version' => self::SCHEMA_VERSION,
            'content_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'payload' => $payload,
            'created_at' => now(),
        ]);

        app(AuditService::class)->record(
            'assessment.report.finalized',
            $assessment,
            newValues: ['report_hash' => $snapshot->content_hash, 'schema_version' => $snapshot->schema_version],
        );

        return $snapshot;
    }

    public function payloadFor(Assessment $assessment): array
    {
        return $assessment->reportSnapshot()->first()?->payload ?? $this->buildPayload($assessment);
    }

    public function buildPayload(Assessment $assessment): array
    {
        $assessment->load([
            'project',
            'target.targetType',
            'score.maturityLevel',
            'snapshot',
            'catalogueRelease',
            'moduleScope.module',
            'aggregationResult.finalizer',
        ]);
        $contentModules = $assessment->snapshot?->payload;
        if (! $contentModules) {
            throw new \LogicException('Governed reports require an immutable assessment snapshot.');
        }

        $modules = collect($contentModules)->map(fn ($module) => [
            'module_id' => (int) $module['module_id'],
            'module_code' => $module['module_code'],
            'module_name' => $module['module_name'],
            'area_label' => $module['area_label'] ?? null,
        ])->values()->all();

        $title = $assessment->catalogueRelease?->release_name
            ?? (count($modules) === 1 ? ($modules[0]['module_name'] ?? 'Assessment') : 'Comprehensive Health Assessment');

        [$subIndices, $domains] = $this->snapshotScoreBreakdown($assessment, $contentModules);

        // Attach the specific failing questions (failed indicators) to each domain, so a
        // weakness can point to the concrete items behind it rather than just a number.
        $domains = $this->attachFailedIndicators($domains, $contentModules);

        $aggregation = $assessment->aggregationResult;

        $payload = [
            'schema_version' => self::SCHEMA_VERSION,
            'assessment_id' => $assessment->assessment_id,
            'composition_hash' => $assessment->composition_hash,
            'creation_path' => $assessment->creation_path,
            'title' => $title,
            'modules' => $modules,
            'project' => ['name' => $assessment->project?->name],
            'target' => [
                'name' => $assessment->target?->name,
                'type' => $assessment->target?->targetType?->target_type_name,
            ],
            'assessor_name' => $assessment->assessor_name,
            'completed_at' => $assessment->completed_at?->toIso8601String(),
            'report_generated_at' => now()->toIso8601String(),
            'score' => [
                'overall_score' => $assessment->score?->overall_score !== null ? (float) $assessment->score->overall_score : null,
                'calibration_status' => $assessment->score?->calibration_status ?? 'NOT_CALIBRATED',
                'scoring_version' => $assessment->score?->scoring_version,
                'maturity_level' => $assessment->score?->maturityLevel ? [
                    'name' => $assessment->score->maturityLevel->level_name,
                    'number' => $assessment->score->maturityLevel->level_number,
                ] : null,
            ],
            'domain_scores' => $domains,
            'sub_index_scores' => $subIndices,
            'respondent_collection' => $aggregation ? [
                'is_multi_respondent' => true,
                'status' => 'FINAL',
                'eligible_completed_respondents' => $aggregation->eligible_respondent_count,
                'minimum_completed_respondents' => $aggregation->minimum_completed_respondents,
                'excluded_session_count' => $aggregation->excluded_session_count,
                'aggregation_method' => $aggregation->aggregation_method,
                'catalogue_release_id' => $assessment->catalogue_release_id,
                'scoring_profile_version' => $aggregation->scoring_version,
                'finalized_at' => $aggregation->finalized_at?->toIso8601String(),
                'finalized_by' => [
                    'user_id' => $aggregation->finalized_by,
                    'name' => $aggregation->finalizer?->name,
                ],
                'contributing_session_references' => $aggregation->payload['eligible_session_references'] ?? [],
                'excluded_sessions' => $aggregation->payload['excluded_sessions'] ?? [],
                'calculation_input_hash' => $aggregation->input_hash,
                'calculation_result_hash' => $aggregation->result_hash,
            ] : [
                'is_multi_respondent' => false,
            ],
        ];

        // Freeze the deterministic intelligence — findings, insights, recommendations —
        // alongside the scores it was derived from. Frozen together, a report reads
        // identically forever, and the reader never sees numbers without their meaning.
        $payload['intelligence'] = app(ReportComposer::class)->intelligence($payload);

        return $payload;
    }

    private function snapshotScoreBreakdown(Assessment $assessment, array $contentModules): array
    {
        $respondentType = ($assessment->snapshot?->collection_config['allows_multi_respondent'] ?? false)
            ? 'AGGREGATE'
            : 'STAFF';
        $subScores = DB::table('sub_index_scores')
            ->where('assessment_id', $assessment->assessment_id)
            ->where('respondent_type', $respondentType)
            ->get()->keyBy('sub_index_id');
        $domainScores = DB::table('domain_scores')
            ->where('assessment_id', $assessment->assessment_id)
            ->get()->keyBy('domain_id');

        $profiles = collect($contentModules)->flatMap(fn ($module) => $module['scoring_profile'] ?? []);
        $contentDomains = $this->domainMetadataFromContent($contentModules);
        $subIndices = $profiles->map(function ($profile) use ($subScores) {
            $score = $subScores->get($profile['sub_index_id']);

            return [
                'sub_index_id' => (int) $profile['sub_index_id'],
                'acronym' => $profile['acronym'],
                'full_name' => $profile['full_name'],
                'domain_id' => (int) $profile['domain_id'],
                'domain_name' => $profile['domain_name'],
                'domain_code' => $profile['domain_code'],
                'score' => $score?->score !== null ? (float) $score->score : null,
                'calibration_status' => $score?->calibration_status ?? 'NOT_CALIBRATED',
                'scoring_version' => $score?->scoring_version,
            ];
        })->values()->all();

        if ($contentDomains->isNotEmpty()) {
            $domains = $contentDomains->map(function ($profile) use ($domainScores) {
                $score = $domainScores->get($profile['domain_id']);

                return [
                    'domain_id' => (int) $profile['domain_id'],
                    'domain_name' => $profile['domain_name'],
                    'domain_code' => $profile['domain_code'],
                    'domain_taxonomy_version_id' => $score?->domain_taxonomy_version_id ?? $profile['domain_taxonomy_version_id'],
                    'domain_taxonomy_content_hash' => $score?->domain_taxonomy_content_hash ?? $profile['domain_taxonomy_content_hash'],
                    'score' => $score?->score !== null ? (float) $score->score : null,
                    'calibration_status' => $score?->calibration_status ?? 'NOT_CALIBRATED',
                    'questions_expected' => $score?->questions_expected !== null ? (int) $score->questions_expected : null,
                    'questions_answered' => $score?->questions_answered !== null ? (int) $score->questions_answered : null,
                    'contributing_question_trace' => $this->decodeJson($score?->contributing_question_trace),
                    'scoring_version' => $score?->scoring_version,
                ];
            })->values()->all();
        } else {
            $domains = $profiles->unique('domain_id')->map(function ($profile) use ($domainScores) {
                $score = $domainScores->get($profile['domain_id']);

                return [
                    'domain_id' => (int) $profile['domain_id'],
                    'domain_name' => $profile['domain_name'],
                    'domain_code' => $profile['domain_code'],
                    'score' => $score?->score !== null ? (float) $score->score : null,
                    'calibration_status' => $score?->calibration_status ?? 'NOT_CALIBRATED',
                    'scoring_version' => $score?->scoring_version,
                ];
            })->values()->all();
        }

        return [$subIndices, $domains];
    }

    /**
     * For each domain, list the contributing questions that scored below the weakness line —
     * the failed indicators. Question text is looked up from the immutable snapshot, so the
     * evidence is as frozen and reproducible as the score it explains.
     *
     * @param  array<int, array<string, mixed>>  $domains
     * @param  array<int, mixed>  $contentModules
     * @return array<int, array<string, mixed>>
     */
    private function attachFailedIndicators(array $domains, array $contentModules): array
    {
        $questionText = collect($contentModules)
            ->flatMap(fn ($module) => $module['questions'] ?? [])
            ->mapWithKeys(fn ($question) => [(string) $question['question_id'] => $question['question_text'] ?? 'Question'])
            ->all();

        return collect($domains)->map(function ($domain) use ($questionText) {
            // Every contributing question, worst first — the drill-down behind the domain score.
            $breakdown = collect($domain['contributing_question_trace'] ?? [])
                ->filter(fn ($item) => isset($item['score']) && $item['score'] !== null)
                ->map(fn ($item) => [
                    'question_id' => $item['question_id'] ?? null,
                    'question_text' => $questionText[(string) ($item['question_id'] ?? '')] ?? 'Question',
                    'score' => (float) $item['score'],
                ])
                ->sortBy('score')
                ->values();

            $domain['question_breakdown'] = $breakdown->all();
            // The subset below the weakness line — the failed indicators diagnostics reads.
            $failed = $breakdown->filter(fn ($q) => $q['score'] < self::WEAK_INDICATOR)->values()->all();
            $domain['failed_indicators'] = $failed;
            $domain['failed_indicator_count'] = count($failed);

            return $domain;
        })->all();
    }

    private function domainMetadataFromContent(array $contentModules)
    {
        return collect($contentModules)
            ->flatMap(fn ($module) => collect($module['questions'] ?? [])->flatMap(fn ($question) => $question['analytical_domains'] ?? []))
            ->filter(fn ($domain) => isset($domain['domain_id']) && ($domain['is_primary'] ?? true))
            ->unique('domain_id')
            ->sortBy('domain_code')
            ->values();
    }

    private function decodeJson($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || $value === '') {
            return [];
        }

        return json_decode($value, true) ?: [];
    }
}
