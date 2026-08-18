<?php

namespace Tests\Feature;

use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\FrameworkQuestionPlacement;
use App\Models\Question;
use App\Models\QuestionVersion;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WashMenstrualHygieneExtensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_menstrual_hygiene_questions_are_published_with_distinct_hashes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $hashes = [];

        foreach (['WASH.017', 'WASH.018', 'WASH.019', 'WASH.020', 'WASH.021'] as $code) {
            $question = Question::where('question_code', $code)->firstOrFail();
            $version = QuestionVersion::where('question_id', $question->question_id)
                ->where('status', QuestionVersion::STATUS_PUBLISHED)
                ->firstOrFail();

            $this->assertNotNull($version->content_hash);
            $hashes[] = $version->content_hash;
        }

        $this->assertCount(5, array_unique($hashes), 'Each new question version should carry a distinct content hash.');
    }

    public function test_original_wash_questions_are_untouched(): void
    {
        $this->seed(DatabaseSeeder::class);

        for ($number = 1; $number <= 16; $number++) {
            $code = sprintf('WASH.%03d', $number);
            $question = Question::where('question_code', $code)->firstOrFail();

            $this->assertSame(1, $question->versions()->count(), "{$code} should still have exactly one version.");
            $this->assertSame(QuestionVersion::STATUS_PUBLISHED, $question->versions()->firstOrFail()->status);
        }
    }

    public function test_focused_wash_framework_is_advanced_to_v2(): void
    {
        $this->seed(DatabaseSeeder::class);

        $module = AssessmentModule::where('module_code', 'WSHF')->firstOrFail();

        // WASH_FRAMEWORK and WASH_DEPARTMENT share one module_id, and version numbers are
        // assigned per module rather than per framework identity, so the successor's exact
        // version_number is not predictable here — look up by status instead.
        $supersededExists = DepartmentFrameworkVersion::where('module_id', $module->module_id)
            ->where('display_name', 'WASH in Health Care Facilities')
            ->where('status', DepartmentFrameworkVersion::STATUS_SUPERSEDED)
            ->exists();
        $this->assertTrue($supersededExists, 'The original focused WASH framework version should now be superseded.');

        $v2 = DepartmentFrameworkVersion::where('module_id', $module->module_id)
            ->where('display_name', 'WASH in Health Care Facilities')
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->firstOrFail();
        $this->assertGreaterThan(1, $v2->version_number, 'The live focused WASH framework should be a successor version, not the original.');
        // 16 original WASH questions + 5 linked IPC questions (second section) + 5 new
        // menstrual hygiene management questions.
        $this->assertSame(26, $v2->questionPlacements()->count());

        $this->assertDatabaseHas('assessment_catalogue_releases', [
            'release_code' => 'VYTTE_WASH_V1',
            'status' => AssessmentCatalogueRelease::STATUS_SUPERSEDED,
        ]);

        $liveFocusedReleases = AssessmentCatalogueRelease::where('creation_path', 'FOCUSED')
            ->where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)
            ->whereHas('departmentFrameworkVersions', fn ($query) => $query
                ->where('department_framework_versions.framework_version_id', $v2->framework_version_id))
            ->get();
        $this->assertCount(1, $liveFocusedReleases);
    }

    public function test_comprehensive_wash_department_is_advanced_to_v2_everywhere(): void
    {
        $this->seed(DatabaseSeeder::class);

        $module = AssessmentModule::where('module_code', 'WSHF')->firstOrFail();

        // Version numbers are assigned per module_id, not per framework identity (see
        // test_focused_wash_framework_is_advanced_to_v2), so look up by status instead of
        // a hardcoded version_number.
        $supersededIds = DepartmentFrameworkVersion::where('module_id', $module->module_id)
            ->where('display_name', 'Water, Sanitation & Hygiene Department Framework')
            ->where('status', DepartmentFrameworkVersion::STATUS_SUPERSEDED)
            ->pluck('framework_version_id');
        $this->assertNotEmpty($supersededIds, 'The original department WASH framework version should now be superseded.');

        $v2 = DepartmentFrameworkVersion::where('module_id', $module->module_id)
            ->where('display_name', 'Water, Sanitation & Hygiene Department Framework')
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->firstOrFail();
        $this->assertSame(21, $v2->questionPlacements()->count());

        // The shared test baseline adds its own demo comprehensive release (e.g.
        // DEMO_CLINIC_COMPREHENSIVE_V1), which pins no WASH department at all. Restricting
        // to the official VYTTE_% release codes matches the convention already used in
        // OfficialPhcCatalogueTest for the same reason.
        $comprehensiveReleasesOnV2 = AssessmentCatalogueRelease::where('creation_path', 'COMPREHENSIVE')
            ->where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)
            ->where('release_code', 'like', 'VYTTE_%')
            ->whereHas('departmentFrameworkVersions', fn ($query) => $query
                ->where('department_framework_versions.framework_version_id', $v2->framework_version_id))
            ->count();
        $this->assertSame(23, $comprehensiveReleasesOnV2, 'Every comprehensive release including WASH should now pin the v2 department framework.');

        $comprehensiveReleasesStillPublished = AssessmentCatalogueRelease::where('creation_path', 'COMPREHENSIVE')
            ->where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)
            ->where('release_code', 'like', 'VYTTE_%')
            ->count();
        $this->assertSame(23, $comprehensiveReleasesStillPublished, 'Advancing WASH should replace releases one-for-one, never leave duplicates published.');

        $comprehensiveReleasesOnOldVersions = AssessmentCatalogueRelease::where('creation_path', 'COMPREHENSIVE')
            ->where('status', AssessmentCatalogueRelease::STATUS_PUBLISHED)
            ->where('release_code', 'like', 'VYTTE_%')
            ->whereHas('departmentFrameworkVersions', fn ($query) => $query
                ->whereIn('department_framework_versions.framework_version_id', $supersededIds))
            ->count();
        $this->assertSame(0, $comprehensiveReleasesOnOldVersions, 'No published comprehensive release should still reference a superseded WASH department framework version.');
    }

    public function test_scoring_rule_for_wash_020_preserves_its_critical_failure_flag(): void
    {
        $this->seed(DatabaseSeeder::class);

        $module = AssessmentModule::where('module_code', 'WSHF')->firstOrFail();
        $v2 = DepartmentFrameworkVersion::where('module_id', $module->module_id)
            ->where('display_name', 'Water, Sanitation & Hygiene Department Framework')
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->firstOrFail();

        $placement = FrameworkQuestionPlacement::where('framework_version_id', $v2->framework_version_id)
            ->whereHas('question', fn ($query) => $query->where('question_code', 'WASH.020'))
            ->with('scoringItemRule')
            ->firstOrFail();

        $this->assertTrue($placement->scoring_contribution);
        $this->assertNotNull($placement->sub_index_id);

        $rule = $placement->scoringItemRule;
        $this->assertNotNull($rule);
        $this->assertSame('OPTION_MAP', $rule->method);
        $this->assertTrue(
            collect($rule->rule_config['option_scores'])->contains(fn ($option) => (bool) $option['critical_failure'] === true),
            'WASH.020 answering "No" should be preserved as a critical failure in its derived scoring rule.'
        );
    }
}
