<?php

namespace App\Services;

use App\Models\AssessmentCatalogueRelease;
use App\Models\DepartmentFrameworkVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Publishes what the author calls "an assessment".
 *
 * One authored assessment maps to two governed artifacts: the department framework version
 * holding the content, and a focused catalogue release that makes it selectable. Publishing
 * only the framework leaves nothing usable, which was easy to get wrong when the two were
 * separate screens.
 *
 * This orchestrates both through their existing publishing services. It performs no
 * validation of its own: DepartmentFrameworkPublishingService and CataloguePublishingService
 * remain the authority and reject anything that does not satisfy them. If either refuses,
 * the whole publication rolls back, so an assessment is never left half published.
 */
class AssessmentPublicationService
{
    public function __construct(
        private readonly DepartmentFrameworkPublishingService $frameworks,
        private readonly CataloguePublishingService $catalogues,
        private readonly AuditService $audit,
    ) {}

    public function publish(DepartmentFrameworkVersion $assessment, int $healthDomainId, ?string $publisherId): AssessmentCatalogueRelease
    {
        if ($assessment->status !== DepartmentFrameworkVersion::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'publish' => 'This assessment has already been published.',
            ]);
        }

        return DB::transaction(function () use ($assessment, $healthDomainId, $publisherId): AssessmentCatalogueRelease {
            // Authoritative content checks. Anything wrong stops publication here.
            $this->frameworks->publish($assessment, $publisherId);

            $release = AssessmentCatalogueRelease::create([
                'release_code' => $this->uniqueReleaseCode($assessment),
                'release_name' => $assessment->display_name,
                'description' => $assessment->description,
                'creation_path' => 'FOCUSED',
                'health_domain_id' => $healthDomainId,
                'status' => AssessmentCatalogueRelease::STATUS_DRAFT,
                'aggregation_policy' => [
                    'method' => 'MEAN_OF_SCORED_SUB_INDICES',
                    'critical_failures' => $this->criticalFailurePolicy($assessment),
                ],
                'composition_rules' => ['latest_resolution' => 'forbidden'],
                'collection_config' => [
                    'allows_multi_respondent' => false,
                    'scoring_profile_version' => ScoringService::ALGORITHM_VERSION,
                ],
            ]);

            $release->departmentFrameworkVersions()->attach($assessment->framework_version_id, [
                'module_id' => $assessment->module_id,
                'applicability' => 'REQUIRED',
                'display_order' => 1,
                'area_label' => $assessment->display_name,
            ]);

            // Authoritative release checks, including that the framework it pins is published.
            $published = $this->catalogues->publish($release->fresh(), $publisherId);

            $this->audit->record('assessment.published', $assessment->fresh(), newValues: [
                'framework_version_id' => $assessment->framework_version_id,
                'catalogue_release_id' => $published->catalogue_release_id,
                'release_code' => $published->release_code,
                'published_by' => $publisherId,
            ], userId: $publisherId);

            return $published;
        });
    }

    /**
     * Critical failures are only switched on when the author actually marked an answer as
     * critical. Enabling the policy otherwise would attach a rule to content that never
     * uses it.
     *
     * @return array<string, mixed>
     */
    private function criticalFailurePolicy(DepartmentFrameworkVersion $assessment): array
    {
        $hasCritical = $assessment->questionPlacements()->where('criticality', 'CRITICAL')->exists();

        return $hasCritical
            ? ['enabled' => true, 'option_score_at_or_below' => 0, 'overall_score' => 'ZERO']
            : ['enabled' => false];
    }

    private function uniqueReleaseCode(DepartmentFrameworkVersion $assessment): string
    {
        $base = Str::of($assessment->display_name)->slug('_')->upper()->limit(50, '')->value() ?: 'ASSESSMENT';

        do {
            $code = $base.'_V'.$assessment->version_number.'_'.strtoupper(Str::random(4));
        } while (AssessmentCatalogueRelease::where('release_code', $code)->exists());

        return $code;
    }
}
