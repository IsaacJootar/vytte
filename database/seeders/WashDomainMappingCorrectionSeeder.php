<?php

namespace Database\Seeders;

use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\DomainDefinition;
use App\Models\DomainTaxonomyVersion;
use App\Models\FrameworkIndicator;
use App\Models\FrameworkIndicatorDomainMapping;
use App\Models\HealthDomain;
use App\Services\AssessmentPublicationService;
use App\Services\AssessmentVersionService;
use App\Services\CatalogueReleaseAdvancementService;
use App\Services\DepartmentFrameworkPublishingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Restores the indicator-level domain mappings the WASH framework's RES_I1 and SAFE_I2
 * indicators lost across earlier version clones.
 *
 * AssessmentVersionService::cloneToDraft() previously never copied FrameworkIndicatorDomainMapping
 * rows when cloning a framework into a new version (fixed separately, in the same change as
 * this seeder). WASH is the department that happened to expose the bug: its original content
 * had correct domain routing — department framework v2's RES_I1 and focused framework v1's
 * RES_I1 and SAFE_I2 were all tagged — but each mapping was silently dropped on a later clone:
 * one predating this session, one from this session's own menstrual hygiene extension. Every
 * answer under those indicators was still recorded correctly; it simply became invisible to
 * domain scoring, with no error anywhere, which is what made it hard to notice.
 *
 * This is a one-time retroactive correction, not something the cloneToDraft() fix alone can
 * undo: published content is immutable, so the correction ships as a new framework version,
 * exactly like every other governed correction on this platform. It does not retroactively
 * fix already-completed report snapshots — those are frozen by design and stay as they were.
 *
 * Runs after WashMenstrualHygieneExtensionSeeder, on top of its published output, using the
 * same governed publishing services Governance Studio and Advanced Tools use. Idempotent:
 * each phase checks whether the indicator is already tagged and skips it, so a partial or
 * repeated run finishes cleanly rather than duplicating framework versions.
 */
class WashDomainMappingCorrectionSeeder extends Seeder
{
    private const DEPARTMENT_DISPLAY_NAME = 'Water, Sanitation & Hygiene Department Framework';

    private const FOCUSED_DISPLAY_NAME = 'WASH in Health Care Facilities';

    public function run(): void
    {
        $module = AssessmentModule::where('module_code', 'WSHF')
            ->where('target_type_code', 'HEALTH_FACILITY')
            ->first();

        if (! $module) {
            $this->command?->warn('WASH department module missing; domain-mapping correction skipped.');

            return;
        }

        $this->correctFocusedVersion($module);
        $this->correctDepartmentVersion($module);

        $this->command?->info('WASH indicator domain-mapping correction applied.');
    }

    private function correctFocusedVersion(AssessmentModule $module): void
    {
        $published = DepartmentFrameworkVersion::where('module_id', $module->module_id)
            ->where('display_name', self::FOCUSED_DISPLAY_NAME)
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->first();

        if (! $published) {
            $this->command?->warn('Focused WASH framework not published yet; correction skipped.');

            return;
        }

        if ($this->indicatorIsTagged($published, 'RES_I1') && $this->indicatorIsTagged($published, 'SAFE_I2')) {
            return;
        }

        DB::transaction(function () use ($published): void {
            $draft = app(AssessmentVersionService::class)->startNewVersion($published, null);
            $this->retagIndicator($draft, 'RES_I1', 'RES');
            $this->retagIndicator($draft, 'SAFE_I2', 'SAFE');

            $healthDomainId = HealthDomain::where('domain_code', 'WASH')->value('health_domain_id');
            app(AssessmentPublicationService::class)->publish($draft->fresh(), $healthDomainId, null);
        });

        $this->command?->info('Focused WASH framework re-published with restored domain mappings.');
    }

    private function correctDepartmentVersion(AssessmentModule $module): void
    {
        $published = DepartmentFrameworkVersion::where('module_id', $module->module_id)
            ->where('display_name', self::DEPARTMENT_DISPLAY_NAME)
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->first();

        if (! $published) {
            $this->command?->warn('WASH department framework not published yet; correction skipped.');

            return;
        }

        if ($this->indicatorIsTagged($published, 'RES_I1')) {
            return;
        }

        $newVersion = DB::transaction(function () use ($published): DepartmentFrameworkVersion {
            // Mirrors WashMenstrualHygieneExtensionSeeder: DEPARTMENT-type frameworks are
            // retired immediately at clone time. Catalogue release advancement happens
            // separately below, after the transaction, matching that seeder's structure.
            $draft = app(AssessmentVersionService::class)->cloneToDraft(
                $published,
                'Restores the RES_I1 domain mapping lost on an earlier clone.'
            );
            $this->retagIndicator($draft, 'RES_I1', 'RES');

            $publishedVersion = app(DepartmentFrameworkPublishingService::class)->publish($draft->fresh());
            $published->update(['status' => DepartmentFrameworkVersion::STATUS_SUPERSEDED]);

            return $publishedVersion;
        });

        $this->advanceComprehensiveReleases($module, $published, $newVersion);
    }

    private function advanceComprehensiveReleases(AssessmentModule $module, DepartmentFrameworkVersion $oldVersion, DepartmentFrameworkVersion $newVersion): void
    {
        $advancement = app(CatalogueReleaseAdvancementService::class);
        $advanced = 0;

        AssessmentCatalogueRelease::where('creation_path', 'COMPREHENSIVE')
            ->where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)
            ->whereHas('departmentFrameworkVersions', fn ($query) => $query
                ->where('department_framework_versions.framework_version_id', $oldVersion->framework_version_id))
            ->get()
            ->each(function (AssessmentCatalogueRelease $release) use ($module, $newVersion, $advancement, &$advanced): void {
                $advancement->advance($release, $module->module_id, $newVersion->framework_version_id);
                $advanced++;
            });

        $this->command?->info("WASH department framework re-advanced in {$advanced} comprehensive catalogue release(s).");
    }

    private function indicatorIsTagged(DepartmentFrameworkVersion $framework, string $indicatorCode): bool
    {
        $indicator = FrameworkIndicator::where('framework_version_id', $framework->framework_version_id)
            ->where('indicator_code', $indicatorCode)
            ->first();

        return $indicator !== null && $indicator->domainMappings()->exists();
    }

    private function retagIndicator(DepartmentFrameworkVersion $framework, string $indicatorCode, string $domainCode): void
    {
        $indicator = FrameworkIndicator::where('framework_version_id', $framework->framework_version_id)
            ->where('indicator_code', $indicatorCode)
            ->first();

        if (! $indicator) {
            $this->command?->warn("Indicator {$indicatorCode} not found on framework {$framework->framework_version_id}; skipped.");

            return;
        }

        if ($indicator->domainMappings()->exists()) {
            return;
        }

        $taxonomyVersion = DomainTaxonomyVersion::where('status', DomainTaxonomyVersion::STATUS_PUBLISHED)
            ->orderByDesc('version_number')
            ->firstOrFail();

        $definition = DomainDefinition::where('domain_taxonomy_version_id', $taxonomyVersion->domain_taxonomy_version_id)
            ->where('domain_code', $domainCode)
            ->firstOrFail();

        FrameworkIndicatorDomainMapping::create([
            'framework_indicator_id' => $indicator->framework_indicator_id,
            'domain_definition_id' => $definition->domain_definition_id,
            'is_primary' => true,
            'contribution_weight' => 1,
            'rationale' => 'Section maps to its measurement domain so scores roll up across subjects.',
        ]);
    }
}
