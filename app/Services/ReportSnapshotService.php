<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentReportSnapshot;
use Illuminate\Support\Facades\DB;

class ReportSnapshotService
{
    public const SCHEMA_VERSION = 'vytte-report-1.0';

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
        $assessment->load(['project', 'target.targetType', 'score.maturityLevel', 'snapshot', 'templateVersion.template', 'moduleScope.module']);
        $contentModules = $assessment->snapshot?->payload;
        $modules = $contentModules
            ? collect($contentModules)->map(fn ($module) => [
                'module_id' => (int) $module['module_id'],
                'module_code' => $module['module_code'],
                'module_name' => $module['module_name'],
                'area_label' => $module['area_label'] ?? null,
            ])->values()->all()
            : $assessment->moduleScope->where('in_scope', true)->map(fn ($scope) => [
                'module_id' => (int) $scope->module_id,
                'module_code' => $scope->module?->module_code,
                'module_name' => $scope->module?->module_name,
                'area_label' => null,
            ])->values()->all();

        $title = $assessment->templateVersion?->template?->template_name
            ?? (count($modules) === 1 ? ($modules[0]['module_name'] ?? 'Assessment') : 'Comprehensive Health Assessment');

        [$subIndices, $domains] = $contentModules
            ? $this->snapshotScoreBreakdown($assessment, $contentModules)
            : $this->legacyScoreBreakdown($assessment);

        return [
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
        ];
    }

    private function snapshotScoreBreakdown(Assessment $assessment, array $contentModules): array
    {
        $subScores = DB::table('sub_index_scores')
            ->where('assessment_id', $assessment->assessment_id)
            ->where('respondent_type', 'STAFF')
            ->get()->keyBy('sub_index_id');
        $domainScores = DB::table('domain_scores')
            ->where('assessment_id', $assessment->assessment_id)
            ->get()->keyBy('domain_id');

        $profiles = collect($contentModules)->flatMap(fn ($module) => $module['scoring_profile'] ?? []);
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

        return [$subIndices, $domains];
    }

    private function legacyScoreBreakdown(Assessment $assessment): array
    {
        $subIndices = DB::table('sub_index_scores as sis')
            ->join('sub_indices as si', 'si.sub_index_id', '=', 'sis.sub_index_id')
            ->join('domains as d', 'd.domain_id', '=', 'si.domain_id')
            ->where('sis.assessment_id', $assessment->assessment_id)
            ->where('sis.respondent_type', 'STAFF')
            ->select('sis.sub_index_id', 'sis.score', 'sis.calibration_status', 'sis.scoring_version', 'si.acronym', 'si.full_name', 'd.domain_id', 'd.domain_name', 'd.domain_code')
            ->get()->map(fn ($row) => (array) $row)->all();
        $domains = DB::table('domain_scores as ds')
            ->join('domains as d', 'd.domain_id', '=', 'ds.domain_id')
            ->where('ds.assessment_id', $assessment->assessment_id)
            ->select('ds.domain_id', 'ds.score', 'ds.calibration_status', 'ds.scoring_version', 'd.domain_name', 'd.domain_code')
            ->get()->map(fn ($row) => (array) $row)->all();

        return [$subIndices, $domains];
    }
}
