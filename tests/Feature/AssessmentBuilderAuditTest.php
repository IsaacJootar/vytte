<?php

namespace Tests\Feature;

use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\Domain;
use App\Models\FrameworkQuestionPlacement;
use App\Models\FrameworkSection;
use App\Models\HealthDomain;
use App\Models\QuestionVersion;
use App\Models\SubIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Control audit for the assessment builder.
 *
 * Every mutating control is probed against the states it must refuse: published content,
 * unauthorized users, objects belonging to another assessment, and repeated operations.
 * These are deliberately adversarial; a failure here is a governance hole, not a UI bug.
 */
class AssessmentBuilderAuditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);
    }

    private function module(): AssessmentModule
    {
        return AssessmentModule::where('is_active', true)->orderBy('module_id')->firstOrFail();
    }

    private function draft(string $name = 'Audit Assessment'): DepartmentFrameworkVersion
    {
        $this->post(route('admin.assessments.store'), [
            'display_name' => $name,
            'module_id' => $this->module()->module_id,
        ]);

        return DepartmentFrameworkVersion::where('display_name', $name)->firstOrFail();
    }

    /** @return array{0: DepartmentFrameworkVersion, 1: FrameworkSection, 2: FrameworkQuestionPlacement} */
    private function draftWithQuestion(string $name = 'Audit Assessment'): array
    {
        $assessment = $this->draft($name);
        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Audit Section']);
        $section = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();
        $this->post(route('admin.assessments.questions.store', [$assessment, $section]), [
            'question_text' => 'Audit question?',
            'format' => 'yes_no',
        ]);
        $placement = FrameworkQuestionPlacement::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();

        return [$assessment, $section, $placement];
    }

    private function publishedWithParts(): array
    {
        [$assessment, $section, $placement] = $this->draftWithQuestion('Audit Published');
        SubIndex::where('module_id', $assessment->module_id)->delete();
        SubIndex::create([
            'module_id' => $assessment->module_id,
            'domain_id' => Domain::orderBy('domain_id')->firstOrFail()->domain_id,
            'acronym' => 'AUD',
            'full_name' => 'Audit Score',
        ]);
        $this->put(route('admin.assessments.questions.settings.save', [$assessment, $placement]), [
            'is_scored' => 1, 'evidence_mode' => 'none', 'points' => [1 => 100, 2 => 0],
        ]);
        $this->patch(route('admin.assessments.questions.approve', [$assessment, $placement]));
        $this->put(route('admin.assessments.provenance', $assessment), [
            'source_authority' => 'Audit authority', 'license_code' => 'AUDIT-1.0',
        ]);
        $this->post(route('admin.assessments.publish', $assessment), [
            'health_domain_id' => HealthDomain::orderBy('health_domain_id')->firstOrFail()->health_domain_id,
            'confirm' => 1,
        ])->assertSessionHasNoErrors();

        return [$assessment->fresh(), $section, $placement->fresh()];
    }

    // ---- Every mutating control refuses a published assessment ----

    public function test_no_mutating_control_accepts_a_published_assessment(): void
    {
        $this->actingAs($this->admin());
        [$assessment, $section, $placement] = $this->publishedWithParts();

        $probes = [
            'rename assessment' => fn () => $this->put(route('admin.assessments.update', $assessment), [
                'display_name' => 'Changed', 'module_id' => $assessment->module_id,
            ]),
            'change provenance' => fn () => $this->put(route('admin.assessments.provenance', $assessment), [
                'source_authority' => 'Changed', 'license_code' => 'CHANGED',
            ]),
            'add section' => fn () => $this->post(route('admin.assessments.sections.store', $assessment), [
                'section_name' => 'Late section',
            ]),
            'rename section' => fn () => $this->put(route('admin.assessments.sections.update', [$assessment, $section]), [
                'section_name' => 'Renamed late',
            ]),
            'move section' => fn () => $this->patch(route('admin.assessments.sections.move', [$assessment, $section]), [
                'direction' => 'up',
            ]),
            'delete section' => fn () => $this->delete(route('admin.assessments.sections.destroy', [$assessment, $section])),
            'add library question' => fn () => $this->post(route('admin.assessments.questions.add-from-library', [$assessment, $section]), [
                'question_version_id' => QuestionVersion::where('status', QuestionVersion::STATUS_PUBLISHED)->firstOrFail()->question_version_id,
            ]),
            'create question' => fn () => $this->post(route('admin.assessments.questions.store', [$assessment, $section]), [
                'question_text' => 'Late question?', 'format' => 'yes_no',
            ]),
            'change scoring' => fn () => $this->put(route('admin.assessments.questions.settings.save', [$assessment, $placement]), [
                'is_scored' => 0, 'evidence_mode' => 'none',
            ]),
            'move question' => fn () => $this->patch(route('admin.assessments.questions.move', [$assessment, $placement]), [
                'direction' => 'up',
            ]),
            'remove question' => fn () => $this->delete(route('admin.assessments.questions.destroy', [$assessment, $placement])),
            'create score group' => fn () => $this->post(route('admin.assessments.scoring-groups.store', $assessment), [
                'name' => 'Late score', 'domain_id' => Domain::orderBy('domain_id')->firstOrFail()->domain_id,
            ]),
        ];

        $sectionsBefore = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->count();
        $questionsBefore = FrameworkQuestionPlacement::where('framework_version_id', $assessment->framework_version_id)->count();

        foreach ($probes as $label => $probe) {
            $probe()->assertSessionHasErrors([], null, "The control \"{$label}\" accepted a published assessment.");
            session()->forget('errors');
        }

        $this->assertSame($sectionsBefore, FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->count());
        $this->assertSame($questionsBefore, FrameworkQuestionPlacement::where('framework_version_id', $assessment->framework_version_id)->count());
        $this->assertSame('Audit Published', $assessment->fresh()->display_name);
    }

    // ---- Every control refuses an unauthorized user ----

    public function test_no_control_is_reachable_by_a_workspace_user(): void
    {
        $this->actingAs($this->admin());
        [$assessment, $section, $placement] = $this->draftWithQuestion();

        $this->actingAs(User::factory()->create());

        $probes = [
            'index' => fn () => $this->get(route('admin.assessments.index')),
            'create' => fn () => $this->get(route('admin.assessments.create')),
            'show' => fn () => $this->get(route('admin.assessments.show', $assessment)),
            'build' => fn () => $this->get(route('admin.assessments.build', $assessment)),
            'review' => fn () => $this->get(route('admin.assessments.review', $assessment)),
            'preview' => fn () => $this->get(route('admin.assessments.preview', $assessment)),
            'edit' => fn () => $this->get(route('admin.assessments.edit', $assessment)),
            'library' => fn () => $this->get(route('admin.assessments.questions.library', [$assessment, $section])),
            'question create' => fn () => $this->get(route('admin.assessments.questions.create', [$assessment, $section])),
            'settings' => fn () => $this->get(route('admin.assessments.questions.settings', [$assessment, $placement])),
            'store' => fn () => $this->post(route('admin.assessments.store'), ['display_name' => 'x', 'module_id' => $this->module()->module_id]),
            'update' => fn () => $this->put(route('admin.assessments.update', $assessment), ['display_name' => 'x', 'module_id' => $assessment->module_id]),
            'provenance' => fn () => $this->put(route('admin.assessments.provenance', $assessment), ['source_authority' => 'x', 'license_code' => 'y']),
            'section store' => fn () => $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'x']),
            'section update' => fn () => $this->put(route('admin.assessments.sections.update', [$assessment, $section]), ['section_name' => 'x']),
            'section move' => fn () => $this->patch(route('admin.assessments.sections.move', [$assessment, $section]), ['direction' => 'up']),
            'section destroy' => fn () => $this->delete(route('admin.assessments.sections.destroy', [$assessment, $section])),
            'question store' => fn () => $this->post(route('admin.assessments.questions.store', [$assessment, $section]), ['question_text' => 'x', 'format' => 'yes_no']),
            'question move' => fn () => $this->patch(route('admin.assessments.questions.move', [$assessment, $placement]), ['direction' => 'up']),
            'question destroy' => fn () => $this->delete(route('admin.assessments.questions.destroy', [$assessment, $placement])),
            'settings save' => fn () => $this->put(route('admin.assessments.questions.settings.save', [$assessment, $placement]), ['is_scored' => 0, 'evidence_mode' => 'none']),
            'approve' => fn () => $this->patch(route('admin.assessments.questions.approve', [$assessment, $placement])),
            'scoring group' => fn () => $this->post(route('admin.assessments.scoring-groups.store', $assessment), ['name' => 'x', 'domain_id' => 1]),
            'publish' => fn () => $this->post(route('admin.assessments.publish', $assessment), ['health_domain_id' => 1, 'confirm' => 1]),
            'version' => fn () => $this->post(route('admin.assessments.versions.store', $assessment)),
        ];

        foreach ($probes as $label => $probe) {
            $probe()->assertForbidden("The control \"{$label}\" was reachable by a workspace user.");
        }
    }

    // ---- Objects from another assessment are not addressable ----

    public function test_a_section_or_question_from_another_assessment_is_not_addressable(): void
    {
        $this->actingAs($this->admin());
        [$first, $firstSection, $firstPlacement] = $this->draftWithQuestion('Audit First');
        [$second] = $this->draftWithQuestion('Audit Second');

        $this->get(route('admin.assessments.questions.library', [$second, $firstSection]))->assertNotFound();
        $this->put(route('admin.assessments.sections.update', [$second, $firstSection]), ['section_name' => 'x'])->assertNotFound();
        $this->delete(route('admin.assessments.sections.destroy', [$second, $firstSection]))->assertNotFound();
        $this->get(route('admin.assessments.questions.settings', [$second, $firstPlacement]))->assertNotFound();
        $this->delete(route('admin.assessments.questions.destroy', [$second, $firstPlacement]))->assertNotFound();
        $this->patch(route('admin.assessments.questions.approve', [$second, $firstPlacement]))->assertNotFound();

        $this->assertDatabaseHas('framework_sections', ['framework_section_id' => $firstSection->framework_section_id]);
        $this->assertDatabaseHas('framework_question_placements', [
            'framework_question_placement_id' => $firstPlacement->framework_question_placement_id,
        ]);
    }

    // ---- Repeated operations ----

    public function test_publishing_the_same_assessment_twice_creates_only_one_release(): void
    {
        $this->actingAs($this->admin());
        [$assessment] = $this->publishedWithParts();

        $releases = AssessmentCatalogueRelease::whereHas(
            'departmentFrameworkVersions',
            fn ($q) => $q->where('department_framework_versions.framework_version_id', $assessment->framework_version_id)
        )->count();

        $this->post(route('admin.assessments.publish', $assessment), [
            'health_domain_id' => HealthDomain::orderBy('health_domain_id')->firstOrFail()->health_domain_id,
            'confirm' => 1,
        ])->assertSessionHasErrors();

        $this->assertSame($releases, AssessmentCatalogueRelease::whereHas(
            'departmentFrameworkVersions',
            fn ($q) => $q->where('department_framework_versions.framework_version_id', $assessment->framework_version_id)
        )->count());
    }

    public function test_approving_an_already_approved_question_is_harmless(): void
    {
        $this->actingAs($this->admin());
        [$assessment, , $placement] = $this->draftWithQuestion();

        $this->patch(route('admin.assessments.questions.approve', [$assessment, $placement]))->assertSessionHasNoErrors();
        $hash = $placement->fresh()->questionVersion->content_hash;

        $this->patch(route('admin.assessments.questions.approve', [$assessment, $placement]))->assertSessionHasNoErrors();

        // The frozen version must not be re-published or re-hashed.
        $this->assertSame($hash, $placement->fresh()->questionVersion->content_hash);
        $this->assertSame(QuestionVersion::STATUS_PUBLISHED, $placement->fresh()->questionVersion->status);
    }

    // ---- Consequential actions are audited ----

    public function test_every_consequential_builder_action_is_audited(): void
    {
        $this->actingAs($this->admin());
        [$assessment, $section, $placement] = $this->publishedWithParts();
        $this->post(route('admin.assessments.versions.store', $assessment));

        foreach ([
            'assessment.draft.created',
            'assessment.section.added',
            'assessment.question.added',
            'assessment.question.settings_updated',
            'assessment.question.approved',
            'assessment.provenance.recorded',
            'assessment.published',
            'assessment.version.started',
        ] as $event) {
            $this->assertDatabaseHas('audit_logs', ['event' => $event]);
        }
    }

    public function test_removing_a_section_or_question_is_audited(): void
    {
        $this->actingAs($this->admin());
        [$assessment, $section, $placement] = $this->draftWithQuestion();

        $this->delete(route('admin.assessments.questions.destroy', [$assessment, $placement]))->assertSessionHasNoErrors();
        $this->delete(route('admin.assessments.sections.destroy', [$assessment, $section]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['event' => 'assessment.question.removed']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'assessment.section.removed']);
    }

    // ---- Structural integrity ----

    public function test_removing_a_section_does_not_orphan_its_indicator(): void
    {
        $this->actingAs($this->admin());
        $assessment = $this->draft();
        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Disposable']);
        $section = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();

        $this->delete(route('admin.assessments.sections.destroy', [$assessment, $section]))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('framework_sections', ['framework_section_id' => $section->framework_section_id]);
        $this->assertDatabaseMissing('framework_indicators', ['framework_section_id' => $section->framework_section_id]);
    }

    public function test_a_score_group_from_another_department_cannot_be_selected(): void
    {
        $this->actingAs($this->admin());
        [$assessment, , $placement] = $this->draftWithQuestion();

        $otherModule = AssessmentModule::where('is_active', true)
            ->where('module_id', '!=', $assessment->module_id)
            ->orderBy('module_id')
            ->firstOrFail();
        $foreign = SubIndex::create([
            'module_id' => $otherModule->module_id,
            'domain_id' => Domain::orderBy('domain_id')->firstOrFail()->domain_id,
            'acronym' => 'FOR',
            'full_name' => 'Foreign Score',
        ]);

        $this->put(route('admin.assessments.questions.settings.save', [$assessment, $placement]), [
            'is_scored' => 1,
            'scoring_group_id' => $foreign->sub_index_id,
            'evidence_mode' => 'none',
            'points' => [1 => 100, 2 => 0],
        ])->assertSessionHasErrors();

        $this->assertNull($placement->fresh()->sub_index_id);
    }

    public function test_publishing_rejects_a_health_area_that_does_not_exist(): void
    {
        $this->actingAs($this->admin());
        [$assessment] = $this->draftWithQuestion();

        $this->post(route('admin.assessments.publish', $assessment), [
            'health_domain_id' => 999999,
            'confirm' => 1,
        ])->assertSessionHasErrors('health_domain_id');

        $this->assertSame(DepartmentFrameworkVersion::STATUS_DRAFT, $assessment->fresh()->status);
    }
}
