<?php

namespace Tests\Feature;

use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\Domain;
use App\Models\FrameworkQuestionPlacement;
use App\Models\FrameworkSection;
use App\Models\HealthDomain;
use App\Models\SubIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Probes whether the builder's guard rails hold when the interface is bypassed.
 *
 * The review screen disables publishing until every blocker clears, but a disabled button
 * is not a control. These tests post directly to the endpoints to confirm the server
 * refuses the same states the interface refuses.
 */
class AssessmentBuilderBypassTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);
    }

    private function healthDomainId(): int
    {
        return HealthDomain::orderBy('health_domain_id')->firstOrFail()->health_domain_id;
    }

    private function assessmentWithSectionAndQuestion(): array
    {
        $module = AssessmentModule::where('is_active', true)->orderBy('module_id')->firstOrFail();
        $this->post(route('admin.assessments.store'), [
            'display_name' => 'Bypass Assessment',
            'module_id' => $module->module_id,
        ]);
        $assessment = DepartmentFrameworkVersion::where('display_name', 'Bypass Assessment')->firstOrFail();

        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Filled Section']);
        $section = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();
        $this->post(route('admin.assessments.questions.store', [$assessment, $section]), [
            'question_text' => 'Bypass question?',
            'format' => 'yes_no',
        ]);
        $placement = FrameworkQuestionPlacement::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();

        SubIndex::where('module_id', $assessment->module_id)->delete();
        SubIndex::create([
            'module_id' => $assessment->module_id,
            'domain_id' => Domain::orderBy('domain_id')->firstOrFail()->domain_id,
            'acronym' => 'BYP',
            'full_name' => 'Bypass Score',
        ]);

        $this->put(route('admin.assessments.questions.settings.save', [$assessment, $placement]), [
            'is_scored' => 1, 'evidence_mode' => 'none', 'points' => [1 => 100, 2 => 0],
        ]);
        $this->patch(route('admin.assessments.questions.approve', [$assessment, $placement]));
        $this->put(route('admin.assessments.provenance', $assessment), [
            'source_authority' => 'Bypass authority', 'license_code' => 'BYPASS-1.0',
        ]);

        return [$assessment->fresh(), $section, $placement];
    }

    public function test_an_assessment_with_an_empty_section_cannot_be_published_by_posting_directly(): void
    {
        $this->actingAs($this->admin());
        [$assessment] = $this->assessmentWithSectionAndQuestion();

        // An empty section is listed as a blocker and the button is disabled, but the
        // endpoint must refuse it too.
        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Empty Section']);

        $this->post(route('admin.assessments.publish', $assessment), [
            'health_domain_id' => $this->healthDomainId(),
            'confirm' => 1,
        ])->assertSessionHasErrors();

        $this->assertSame(DepartmentFrameworkVersion::STATUS_DRAFT, $assessment->fresh()->status);
    }

    public function test_a_scored_question_without_a_score_group_cannot_be_published_by_posting_directly(): void
    {
        $this->actingAs($this->admin());
        [$assessment, , $placement] = $this->assessmentWithSectionAndQuestion();

        // Force the invalid state the interface prevents.
        FrameworkQuestionPlacement::where('framework_question_placement_id', $placement->framework_question_placement_id)
            ->update(['sub_index_id' => null]);

        $this->post(route('admin.assessments.publish', $assessment), [
            'health_domain_id' => $this->healthDomainId(),
            'confirm' => 1,
        ])->assertSessionHasErrors();

        $this->assertSame(DepartmentFrameworkVersion::STATUS_DRAFT, $assessment->fresh()->status);
    }

    public function test_builder_publications_are_single_respondent(): void
    {
        $this->actingAs($this->admin());
        [$assessment] = $this->assessmentWithSectionAndQuestion();

        $this->post(route('admin.assessments.publish', $assessment), [
            'health_domain_id' => $this->healthDomainId(),
            'confirm' => 1,
        ])->assertSessionHasNoErrors();

        $release = AssessmentCatalogueRelease::whereHas(
            'departmentFrameworkVersions',
            fn ($q) => $q->where('department_framework_versions.framework_version_id', $assessment->framework_version_id)
        )->firstOrFail();

        // Evidence prompts are rendered by the authenticated runner. The public respondent
        // runner has no evidence support, so builder publications must stay single
        // respondent or an authored prompt would silently never be shown.
        $this->assertFalse((bool) ($release->collection_config['allows_multi_respondent'] ?? false));
    }
}
