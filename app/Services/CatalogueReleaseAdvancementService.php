<?php

namespace App\Services;

use App\Models\AssessmentCatalogueRelease;
use Illuminate\Support\Facades\DB;

/**
 * Advances a published catalogue release to a successor that swaps one pinned department
 * framework version for a newer one, leaving every other pinned department untouched.
 *
 * A published catalogue release is immutable (enforced by a model guard), so picking up a
 * new department framework version always means a new release row, never an edit. This
 * mirrors the same clone-swap-publish-supersede sequence CatalogueReleaseController drives
 * through three separate admin actions (supersede, attachFramework/detachFramework,
 * publish), extracted so it can run in a loop across many releases at once — the situation
 * a single department framework being reused across every comprehensive facility profile
 * creates whenever that department gains a new version.
 */
class CatalogueReleaseAdvancementService
{
    public function __construct(
        private readonly CataloguePublishingService $publishing,
        private readonly AuditService $audit,
    ) {}

    public function advance(
        AssessmentCatalogueRelease $release,
        int $moduleId,
        string $newFrameworkVersionId,
        ?string $userId = null,
    ): AssessmentCatalogueRelease {
        return DB::transaction(function () use ($release, $moduleId, $newFrameworkVersionId, $userId): AssessmentCatalogueRelease {
            $release->load('departmentFrameworkVersions');

            $successor = AssessmentCatalogueRelease::create([
                'release_code' => $this->nextReleaseCode($release->release_code),
                'parent_release_id' => $release->catalogue_release_id,
                'release_name' => $release->release_name,
                'description' => $release->description,
                'creation_path' => $release->creation_path,
                'facility_profile_id' => $release->facility_profile_id,
                'health_domain_id' => $release->health_domain_id,
                'status' => AssessmentCatalogueRelease::STATUS_DRAFT,
                'aggregation_policy' => $release->aggregation_policy,
                'composition_rules' => $release->composition_rules,
                'collection_config' => $release->collection_config,
                'content_publisher_id' => $release->content_publisher_id,
                'distribution_level' => $release->distribution_level,
            ]);

            foreach ($release->departmentFrameworkVersions as $framework) {
                $pinnedVersionId = (int) $framework->module_id === $moduleId
                    ? $newFrameworkVersionId
                    : $framework->framework_version_id;

                $successor->departmentFrameworkVersions()->attach($pinnedVersionId, [
                    'module_id' => $framework->pivot->module_id,
                    'applicability' => $framework->pivot->applicability,
                    'display_order' => $framework->pivot->display_order,
                    'area_label' => $framework->pivot->area_label,
                ]);
            }

            // Publish the successor before retiring the original, so a failed publish
            // rolls the whole transaction back and leaves the original release live.
            $published = $this->publishing->publish($successor->fresh(), $userId);

            $old = ['status' => $release->status];
            $release->update(['status' => AssessmentCatalogueRelease::STATUS_SUPERSEDED]);
            $this->audit->record('assessment.catalogue.superseded', $release->fresh(), $old, [
                'status' => AssessmentCatalogueRelease::STATUS_SUPERSEDED,
                'successor_catalogue_release_id' => $published->catalogue_release_id,
            ], userId: $userId);

            return $published;
        });
    }

    private function nextReleaseCode(string $releaseCode): string
    {
        $baseCode = preg_replace('/_V\d+$/', '', $releaseCode) ?: $releaseCode;
        $nextNumber = AssessmentCatalogueRelease::where('release_code', 'LIKE', $baseCode.'_V%')->count() + 1;
        $candidate = $baseCode.'_V'.$nextNumber;

        while (AssessmentCatalogueRelease::where('release_code', $candidate)->exists()) {
            $nextNumber++;
            $candidate = $baseCode.'_V'.$nextNumber;
        }

        return $candidate;
    }
}
