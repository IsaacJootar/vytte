<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentReportSnapshot;
use App\Models\AssessmentSnapshot;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FrameworkQuestionPlacement;
use App\Models\QuestionVersion;

class GovernanceDependencyService
{
    public function questionVersion(QuestionVersion $version): array
    {
        return [
            'framework_placements' => FrameworkQuestionPlacement::where('question_version_id', $version->question_version_id)->count(),
            'assessment_snapshots' => $this->assessmentSnapshotReferences($version->question_version_id),
            'report_snapshots' => $this->reportSnapshotReferences($version->question_version_id),
            'successor_versions' => QuestionVersion::where('parent_version_id', $version->question_version_id)->count(),
        ];
    }

    public function frameworkVersion(DepartmentFrameworkVersion $version): array
    {
        return [
            'catalogue_releases' => $version->catalogueReleases()->count(),
            'assessment_snapshots' => $this->assessmentSnapshotReferences($version->framework_version_id),
            'report_snapshots' => $this->reportSnapshotReferences($version->framework_version_id),
            'successor_versions' => DepartmentFrameworkVersion::where('parent_version_id', $version->framework_version_id)->count(),
        ];
    }

    public function catalogueRelease(AssessmentCatalogueRelease $release): array
    {
        return [
            'assessments' => Assessment::where('catalogue_release_id', $release->catalogue_release_id)->count(),
            'assessment_snapshots' => AssessmentSnapshot::where('catalogue_release_id', $release->catalogue_release_id)->count()
                + $this->assessmentSnapshotReferences($release->catalogue_release_id),
            'report_snapshots' => $this->reportSnapshotReferences($release->catalogue_release_id),
            'successor_releases' => AssessmentCatalogueRelease::where('parent_release_id', $release->catalogue_release_id)->count(),
        ];
    }

    public function hasBlockingArchiveDependencies(array $summary): bool
    {
        return collect($summary)->except(['successor_versions', 'successor_releases'])->sum() > 0;
    }

    public function total(array $summary): int
    {
        return (int) collect($summary)->sum();
    }

    private function assessmentSnapshotReferences(string $needle): int
    {
        return AssessmentSnapshot::query()
            ->select(['snapshot_id', 'payload', 'composition_manifest', 'aggregation_policy', 'collection_config'])
            ->get()
            ->filter(fn (AssessmentSnapshot $snapshot) => str_contains(json_encode([
                $snapshot->payload,
                $snapshot->composition_manifest,
                $snapshot->aggregation_policy,
                $snapshot->collection_config,
            ], JSON_THROW_ON_ERROR), $needle))
            ->count();
    }

    private function reportSnapshotReferences(string $needle): int
    {
        return AssessmentReportSnapshot::query()
            ->select(['report_snapshot_id', 'payload'])
            ->get()
            ->filter(fn (AssessmentReportSnapshot $snapshot) => str_contains(json_encode($snapshot->payload, JSON_THROW_ON_ERROR), $needle))
            ->count();
    }
}
