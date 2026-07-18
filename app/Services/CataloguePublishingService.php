<?php

namespace App\Services;

use App\Models\AssessmentCatalogueRelease;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FacilityProfile;
use Illuminate\Validation\ValidationException;

class CataloguePublishingService
{
    public function publish(AssessmentCatalogueRelease $release, ?string $publisherId = null): AssessmentCatalogueRelease
    {
        $release->load(['facilityProfile.departments', 'departmentFrameworkVersions.module']);
        $errors = [];
        $versions = $release->departmentFrameworkVersions;

        if ($versions->isEmpty()) {
            $errors['framework_versions'][] = 'A catalogue release must pin at least one department framework version.';
        }

        if ($release->creation_path === 'COMPREHENSIVE') {
            if (! $release->facilityProfile) {
                $errors['facility_profile'][] = 'Comprehensive catalogue releases require a facility profile.';
            } elseif ($release->facilityProfile->status !== FacilityProfile::STATUS_PUBLISHED) {
                $errors['facility_profile'][] = 'Comprehensive catalogue releases require a published facility profile.';
            }
        }

        if ($release->creation_path === 'FOCUSED') {
            if (! $release->health_domain_id) {
                $errors['health_domain'][] = 'Focused catalogue releases require one health domain.';
            }
            if ($versions->count() !== 1) {
                $errors['framework_versions'][] = 'The first focused catalogue release supports one department framework version.';
            }
        }

        $unpublished = $versions->first(fn ($version) => $version->status !== DepartmentFrameworkVersion::STATUS_PUBLISHED);
        if ($unpublished) {
            $errors['framework_versions'][] = 'Catalogue releases can only reference published department framework versions.';
        }

        $duplicateModule = $versions->groupBy('module_id')->first(fn ($items) => $items->count() > 1);
        if ($duplicateModule) {
            $errors['framework_versions'][] = 'A catalogue release cannot include the same department more than once.';
        }

        if ($release->facilityProfile) {
            $profileModules = $release->facilityProfile->departments->keyBy('module_id');
            foreach ($versions as $version) {
                $profileDepartment = $profileModules->get($version->module_id);
                if (! $profileDepartment || $profileDepartment->pivot->applicability === 'UNAVAILABLE') {
                    $errors['facility_profile'][] = 'Catalogue release includes a department unavailable for this facility profile.';
                    break;
                }
            }
        }

        if (($release->aggregation_policy['method'] ?? null) !== 'MEAN_OF_SCORED_SUB_INDICES') {
            $errors['aggregation_policy'][] = 'MEAN_OF_SCORED_SUB_INDICES is the only demonstration facility aggregation method currently implemented.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $manifest = [
            'release_code' => $release->release_code,
            'creation_path' => $release->creation_path,
            'facility_profile_id' => $release->facility_profile_id,
            'health_domain_id' => $release->health_domain_id,
            'aggregation_policy' => $release->aggregation_policy,
            'composition_rules' => $release->composition_rules ?? [],
            'department_versions' => $versions->map(fn ($version) => [
                'module_id' => (int) $version->module_id,
                'module_code' => $version->module?->module_code,
                'framework_version_id' => $version->framework_version_id,
                'framework_version_number' => (int) $version->version_number,
                'framework_content_hash' => $version->content_hash,
                'applicability' => $version->pivot->applicability,
                'display_order' => (int) $version->pivot->display_order,
            ])->values()->all(),
        ];

        $release->update([
            'status' => AssessmentCatalogueRelease::STATUS_PUBLISHED,
            'content_hash' => hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR)),
            'published_at' => now(),
            'published_by' => $publisherId,
        ]);

        app(AuditService::class)->record(
            'assessment.catalogue.published',
            $release->fresh(),
            ['status' => AssessmentCatalogueRelease::STATUS_DRAFT],
            ['status' => AssessmentCatalogueRelease::STATUS_PUBLISHED, 'content_hash' => $release->content_hash],
            userId: $publisherId,
        );

        return $release->fresh(['facilityProfile', 'departmentFrameworkVersions']);
    }
}
