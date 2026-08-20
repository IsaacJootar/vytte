<?php

namespace Tests\Feature;

use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FrameworkIndicator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WashDomainMappingCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_focused_wash_indicators_are_tagged_with_their_domains(): void
    {
        $this->seed(DatabaseSeeder::class);

        $module = AssessmentModule::where('module_code', 'WSHF')->firstOrFail();
        $live = DepartmentFrameworkVersion::where('module_id', $module->module_id)
            ->where('display_name', 'WASH in Health Care Facilities')
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->firstOrFail();

        $resI1 = FrameworkIndicator::where('framework_version_id', $live->framework_version_id)
            ->where('indicator_code', 'RES_I1')->firstOrFail();
        $safeI2 = FrameworkIndicator::where('framework_version_id', $live->framework_version_id)
            ->where('indicator_code', 'SAFE_I2')->firstOrFail();

        $resMapping = $resI1->domainMappings()->firstOrFail();
        $this->assertSame('RES', $resMapping->domainDefinition->domain_code);

        $safeMapping = $safeI2->domainMappings()->firstOrFail();
        $this->assertSame('SAFE', $safeMapping->domainDefinition->domain_code);

        // The correction ships as a new version, not an edit — the previously-live, wrongly
        // untagged version must still exist, superseded, for historical assessments to trace to.
        $this->assertGreaterThan(1, $live->version_number);
    }

    public function test_department_wash_indicator_is_tagged_and_comprehensive_releases_advanced(): void
    {
        $this->seed(DatabaseSeeder::class);

        $module = AssessmentModule::where('module_code', 'WSHF')->firstOrFail();
        $live = DepartmentFrameworkVersion::where('module_id', $module->module_id)
            ->where('display_name', 'Water, Sanitation & Hygiene Department Framework')
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->firstOrFail();

        $resI1 = FrameworkIndicator::where('framework_version_id', $live->framework_version_id)
            ->where('indicator_code', 'RES_I1')->firstOrFail();
        $mapping = $resI1->domainMappings()->firstOrFail();
        $this->assertSame('RES', $mapping->domainDefinition->domain_code);
        $this->assertTrue((bool) $mapping->is_primary);

        // Every official comprehensive release including WASH must now pin this corrected
        // version — mirrors the assertion style already proven in WashMenstrualHygieneExtensionTest.
        $comprehensiveReleasesOnLive = AssessmentCatalogueRelease::where('creation_path', 'COMPREHENSIVE')
            ->where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)
            ->where('release_code', 'like', 'VYTTE_%')
            ->whereHas('departmentFrameworkVersions', fn ($query) => $query
                ->where('department_framework_versions.framework_version_id', $live->framework_version_id))
            ->count();
        $comprehensiveReleasesTotal = AssessmentCatalogueRelease::where('creation_path', 'COMPREHENSIVE')
            ->where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)
            ->where('release_code', 'like', 'VYTTE_%')
            ->count();
        $this->assertSame($comprehensiveReleasesTotal, $comprehensiveReleasesOnLive, 'Every official comprehensive release must pin the corrected WASH department version.');
        $this->assertGreaterThan(0, $comprehensiveReleasesTotal);
    }

    public function test_original_wash_content_is_untouched_by_the_correction(): void
    {
        $this->seed(DatabaseSeeder::class);

        $module = AssessmentModule::where('module_code', 'WSHF')->firstOrFail();
        $live = DepartmentFrameworkVersion::where('module_id', $module->module_id)
            ->where('display_name', 'WASH in Health Care Facilities')
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->firstOrFail();

        // 16 original WASH + 5 linked IPC + 5 menstrual hygiene management — unchanged from
        // the extension seeder's own count; the correction adds no content, only tags it.
        $this->assertSame(26, $live->questionPlacements()->count());
    }

    public function test_running_the_correction_twice_does_not_duplicate_framework_versions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(\Database\Seeders\WashDomainMappingCorrectionSeeder::class);

        $module = AssessmentModule::where('module_code', 'WSHF')->firstOrFail();

        $publishedFocusedCount = DepartmentFrameworkVersion::where('module_id', $module->module_id)
            ->where('display_name', 'WASH in Health Care Facilities')
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->count();
        $this->assertSame(1, $publishedFocusedCount, 'Re-running the correction must not publish a second version.');

        $publishedDepartmentCount = DepartmentFrameworkVersion::where('module_id', $module->module_id)
            ->where('display_name', 'Water, Sanitation & Hygiene Department Framework')
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->count();
        $this->assertSame(1, $publishedDepartmentCount, 'Re-running the correction must not publish a second version.');
    }
}
