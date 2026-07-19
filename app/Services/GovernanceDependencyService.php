<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentReportSnapshot;
use App\Models\AssessmentSnapshot;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FrameworkQuestionPlacement;
use App\Models\QuestionVersion;
use Illuminate\Database\Eloquent\Builder;

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
            'assessment_snapshots' => $this->snapshotReferenceQuery($release->catalogue_release_id)
                ->orWhere('catalogue_release_id', $release->catalogue_release_id)
                ->count(),
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

    /**
     * Frozen snapshot documents embed governed identifiers rather than referencing
     * them with foreign keys, so a reference check is a search inside the JSON.
     * The match runs in the database: loading every snapshot into PHP to search it
     * grew unbounded with assessment volume on routine admin pages.
     */
    private function snapshotReferenceQuery(string $needle): Builder
    {
        $columns = ['payload', 'composition_manifest', 'aggregation_policy', 'collection_config'];

        return AssessmentSnapshot::query()->where(function (Builder $query) use ($columns, $needle): void {
            foreach ($columns as $column) {
                $query->orWhereRaw("CAST({$column} AS TEXT) LIKE ?", ['%'.$needle.'%']);
            }
        });
    }

    private function assessmentSnapshotReferences(string $needle): int
    {
        return $this->snapshotReferenceQuery($needle)->count();
    }

    private function reportSnapshotReferences(string $needle): int
    {
        return AssessmentReportSnapshot::query()
            ->whereRaw('CAST(payload AS TEXT) LIKE ?', ['%'.$needle.'%'])
            ->count();
    }
}
