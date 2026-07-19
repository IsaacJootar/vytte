<?php

namespace Tests\Feature;

use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\Domain;
use App\Models\FrameworkIndicator;
use App\Models\FrameworkQuestionPlacement;
use App\Models\FrameworkSection;
use App\Models\QuestionVersion;
use App\Models\SubIndex;
use App\Models\User;
use App\Services\FrameworkContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function platformAdmin(): User
    {
        return User::factory()->create(['platform_role' => 'PLATFORM_ADMIN']);
    }

    private function activeModule(): AssessmentModule
    {
        // Ordered explicitly: without it PostgreSQL may return a different module per run,
        // and the seeded score groups differ between departments.
        return AssessmentModule::where('is_active', true)->orderBy('module_id')->firstOrFail();
    }

    // ---- Authorization ----

    public function test_platform_admin_can_open_the_assessments_landing_page(): void
    {
        $this->actingAs($this->platformAdmin())
            ->get(route('admin.assessments.index'))
            ->assertOk()
            ->assertSee('Assessments')
            ->assertSee('New Assessment');
    }

    public function test_workspace_user_cannot_open_the_assessment_builder(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.assessments.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.assessments.create'))->assertForbidden();
    }

    public function test_guest_cannot_open_the_assessment_builder(): void
    {
        $this->get(route('admin.assessments.index'))->assertRedirect(route('login'));
    }

    public function test_workspace_user_cannot_create_an_assessment(): void
    {
        $module = $this->activeModule();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.assessments.store'), [
                'display_name' => 'Unauthorized assessment',
                'module_id' => $module->module_id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('department_framework_versions', ['display_name' => 'Unauthorized assessment']);
    }

    // ---- Draft creation ----

    public function test_platform_admin_creates_a_draft_assessment_from_basic_information(): void
    {
        $module = $this->activeModule();

        $response = $this->actingAs($this->platformAdmin())
            ->post(route('admin.assessments.store'), [
                'display_name' => 'Outpatient Readiness Assessment',
                'description' => 'Checks readiness of outpatient services.',
                'module_id' => $module->module_id,
                'purpose' => 'Quarterly facility review.',
            ]);

        $assessment = DepartmentFrameworkVersion::where('display_name', 'Outpatient Readiness Assessment')->firstOrFail();

        $response->assertRedirect(route('admin.assessments.show', $assessment));
        $this->assertSame(DepartmentFrameworkVersion::STATUS_DRAFT, $assessment->status);
        $this->assertSame($module->module_id, $assessment->module_id);
        $this->assertSame('Quarterly facility review.', $assessment->purpose);
    }

    public function test_draft_creation_requires_a_name_and_department(): void
    {
        $this->actingAs($this->platformAdmin())
            ->post(route('admin.assessments.store'), ['display_name' => '', 'module_id' => ''])
            ->assertSessionHasErrors(['display_name', 'module_id']);

        $this->assertSame(0, DepartmentFrameworkVersion::where('display_name', '')->count());
    }

    public function test_draft_creation_rejects_an_inactive_department(): void
    {
        $module = AssessmentModule::where('is_active', true)->firstOrFail();
        $module->update(['is_active' => false]);

        $this->actingAs($this->platformAdmin())
            ->post(route('admin.assessments.store'), [
                'display_name' => 'Inactive department assessment',
                'module_id' => $module->module_id,
            ])
            ->assertSessionHasErrors('module_id');
    }

    public function test_creating_a_draft_records_an_audit_event(): void
    {
        $this->actingAs($this->platformAdmin())
            ->post(route('admin.assessments.store'), [
                'display_name' => 'Audited assessment',
                'module_id' => $this->activeModule()->module_id,
            ]);

        $this->assertDatabaseHas('audit_logs', ['event' => 'assessment.draft.created']);
    }

    // ---- Draft resume ----

    public function test_a_saved_draft_is_listed_and_can_be_resumed(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)->post(route('admin.assessments.store'), [
            'display_name' => 'Resumable Draft Assessment',
            'module_id' => $this->activeModule()->module_id,
        ]);

        $assessment = DepartmentFrameworkVersion::where('display_name', 'Resumable Draft Assessment')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.assessments.index'))
            ->assertOk()
            ->assertSee('Resumable Draft Assessment')
            ->assertSee('Continue');

        $this->actingAs($admin)->get(route('admin.assessments.show', $assessment))
            ->assertOk()
            ->assertSee('Resumable Draft Assessment')
            ->assertSee('Basic Information');
    }

    public function test_basic_information_can_be_edited_while_the_assessment_is_a_draft(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)->post(route('admin.assessments.store'), [
            'display_name' => 'Original Name',
            'module_id' => $this->activeModule()->module_id,
        ]);
        $assessment = DepartmentFrameworkVersion::where('display_name', 'Original Name')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.assessments.update', $assessment), [
            'display_name' => 'Renamed Assessment',
            'description' => 'Updated description.',
            'module_id' => $assessment->module_id,
            'purpose' => 'Updated purpose.',
        ])->assertRedirect(route('admin.assessments.show', $assessment));

        $assessment->refresh();
        $this->assertSame('Renamed Assessment', $assessment->display_name);
        $this->assertSame('Updated description.', $assessment->description);
    }

    // ---- Published protection ----

    public function test_a_published_assessment_cannot_be_edited_through_the_builder(): void
    {
        $admin = $this->platformAdmin();
        $published = DepartmentFrameworkVersion::where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)->firstOrFail();
        $originalName = $published->display_name;

        $this->actingAs($admin)->put(route('admin.assessments.update', $published), [
            'display_name' => 'Attempted rename of published content',
            'module_id' => $published->module_id,
        ])->assertSessionHasErrors('status');

        $this->assertSame($originalName, $published->fresh()->display_name);
    }

    public function test_a_published_assessment_shows_as_locked_without_an_edit_action(): void
    {
        $published = DepartmentFrameworkVersion::where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)->firstOrFail();

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.assessments.show', $published))
            ->assertOk()
            ->assertSee('This assessment is locked')
            ->assertDontSee(route('admin.assessments.edit', $published));
    }

    // ---- Wizard shape ----

    public function test_the_wizard_shows_progress_and_marks_unavailable_steps_as_coming_next(): void
    {
        $this->actingAs($this->platformAdmin())
            ->get(route('admin.assessments.create'))
            ->assertOk()
            ->assertSee('Basic Information')
            ->assertSee('Build Assessment')
            ->assertSee('Review')
            ->assertSee('Publish')
            ->assertSee('Coming next');
    }

    public function test_the_builder_form_does_not_ask_for_governance_internals(): void
    {
        $response = $this->actingAs($this->platformAdmin())->get(route('admin.assessments.create'));

        // Governance vocabulary still appears in the Advanced Tools navigation, which is
        // intended. What must not appear is a governance knob the ordinary author is asked
        // to set: those are established by the domain services instead.
        foreach ([
            'name="framework_type"',
            'name="question_version_id"',
            'name="sub_index_id"',
            'name="catalogue_release_id"',
            'name="source_authority"',
            'name="license_code"',
            'name="version_number"',
        ] as $governanceField) {
            $response->assertDontSee($governanceField, false);
        }
    }

    // ---- Sections ----

    private function draftAssessment(): DepartmentFrameworkVersion
    {
        $this->post(route('admin.assessments.store'), [
            'display_name' => 'Builder Content Assessment',
            'module_id' => $this->activeModule()->module_id,
        ]);

        return DepartmentFrameworkVersion::where('display_name', 'Builder Content Assessment')->firstOrFail();
    }

    public function test_adding_a_section_creates_a_hidden_default_indicator(): void
    {
        $this->actingAs($this->platformAdmin());
        $assessment = $this->draftAssessment();

        $this->post(route('admin.assessments.sections.store', $assessment), [
            'section_name' => 'Infection Control',
        ])->assertRedirect();

        $section = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();

        $this->assertSame('Infection Control', $section->section_name);
        $this->assertSame(1, FrameworkIndicator::where('framework_section_id', $section->framework_section_id)->count());
    }

    public function test_the_author_never_sees_or_names_the_indicator(): void
    {
        $this->actingAs($this->platformAdmin());
        $assessment = $this->draftAssessment();
        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Leadership']);

        $this->get(route('admin.assessments.build', $assessment))
            ->assertOk()
            ->assertSee('Leadership')
            ->assertDontSee('Indicator')
            ->assertDontSee('indicator_code');
    }

    public function test_sections_can_be_reordered(): void
    {
        $this->actingAs($this->platformAdmin());
        $assessment = $this->draftAssessment();

        foreach (['First', 'Second'] as $name) {
            $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => $name]);
        }

        $second = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)
            ->where('section_name', 'Second')->firstOrFail();

        $this->patch(route('admin.assessments.sections.move', [$assessment, $second]), ['direction' => 'up'])
            ->assertRedirect();

        $ordered = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)
            ->orderBy('display_order')->pluck('section_name')->all();

        $this->assertSame(['Second', 'First'], $ordered);
    }

    public function test_a_section_holding_questions_cannot_be_removed(): void
    {
        $this->actingAs($this->platformAdmin());
        $assessment = $this->draftAssessment();
        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Occupied']);
        $section = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();

        $version = QuestionVersion::where('status', QuestionVersion::STATUS_PUBLISHED)->firstOrFail();
        $this->post(route('admin.assessments.questions.add-from-library', [$assessment, $section]), [
            'question_version_id' => $version->question_version_id,
        ]);

        $this->delete(route('admin.assessments.sections.destroy', [$assessment, $section]))
            ->assertSessionHasErrors('section');

        $this->assertDatabaseHas('framework_sections', ['framework_section_id' => $section->framework_section_id]);
    }

    public function test_sections_cannot_be_added_to_a_published_assessment(): void
    {
        $published = DepartmentFrameworkVersion::where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)->firstOrFail();

        $this->actingAs($this->platformAdmin())
            ->post(route('admin.assessments.sections.store', $published), ['section_name' => 'Should not be added'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('framework_sections', ['section_name' => 'Should not be added']);
    }

    // ---- Question library ----

    public function test_the_library_lists_published_questions_with_their_answer_format(): void
    {
        $this->actingAs($this->platformAdmin());
        $assessment = $this->draftAssessment();
        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Library Section']);
        $section = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();

        $this->get(route('admin.assessments.questions.library', [$assessment, $section]))
            ->assertOk()
            ->assertSee('Question Library')
            ->assertSee('Add to section');
    }

    public function test_a_library_question_is_placed_with_safe_unscored_defaults(): void
    {
        $this->actingAs($this->platformAdmin());
        $assessment = $this->draftAssessment();
        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Placement Section']);
        $section = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();
        $version = QuestionVersion::where('status', QuestionVersion::STATUS_PUBLISHED)->firstOrFail();

        $this->post(route('admin.assessments.questions.add-from-library', [$assessment, $section]), [
            'question_version_id' => $version->question_version_id,
        ])->assertRedirect(route('admin.assessments.build', $assessment));

        $placement = FrameworkQuestionPlacement::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();

        $this->assertSame($version->question_version_id, $placement->question_version_id);
        $this->assertFalse((bool) $placement->scoring_contribution);
        $this->assertNull($placement->sub_index_id);
        $this->assertSame('STANDARD', $placement->criticality);
    }

    public function test_the_same_question_cannot_be_added_twice_to_one_assessment(): void
    {
        $this->actingAs($this->platformAdmin());
        $assessment = $this->draftAssessment();
        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Duplicate Section']);
        $section = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();
        $version = QuestionVersion::where('status', QuestionVersion::STATUS_PUBLISHED)->firstOrFail();

        $this->post(route('admin.assessments.questions.add-from-library', [$assessment, $section]), [
            'question_version_id' => $version->question_version_id,
        ]);
        $this->post(route('admin.assessments.questions.add-from-library', [$assessment, $section]), [
            'question_version_id' => $version->question_version_id,
        ])->assertSessionHasErrors('question');

        $this->assertSame(1, FrameworkQuestionPlacement::where('framework_version_id', $assessment->framework_version_id)->count());
    }

    // ---- Creating a new question ----

    public function test_a_new_yes_no_question_creates_real_answer_options(): void
    {
        $this->actingAs($this->platformAdmin());
        $assessment = $this->draftAssessment();
        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'New Question Section']);
        $section = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();

        $this->post(route('admin.assessments.questions.store', [$assessment, $section]), [
            'question_text' => 'Is emergency oxygen available today?',
            'format' => 'yes_no',
        ])->assertRedirect(route('admin.assessments.build', $assessment));

        $placement = FrameworkQuestionPlacement::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();
        $version = $placement->questionVersion;

        $this->assertSame(QuestionVersion::STATUS_DRAFT, $version->status);
        $this->assertSame(['Yes', 'No'], collect($version->options)->pluck('option_label')->all());

        // responses.value_option_id is a foreign key to question_options, so every option
        // in the version payload must exist as a real row or the answer cannot be stored.
        foreach ($version->options as $option) {
            $this->assertDatabaseHas('question_options', [
                'option_id' => $option['option_id'],
                'question_id' => $version->question_id,
            ]);
        }
    }

    public function test_a_multiple_choice_question_requires_at_least_two_distinct_choices(): void
    {
        $this->actingAs($this->platformAdmin());
        $assessment = $this->draftAssessment();
        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Choices Section']);
        $section = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();

        $this->post(route('admin.assessments.questions.store', [$assessment, $section]), [
            'question_text' => 'Which supply is short?',
            'format' => 'multiple_choice',
            'choices' => ['Gloves', ''],
        ])->assertSessionHasErrors('choices');

        $this->post(route('admin.assessments.questions.store', [$assessment, $section]), [
            'question_text' => 'Which supply is short?',
            'format' => 'multiple_choice',
            'choices' => ['Gloves', 'Gloves'],
        ])->assertSessionHasErrors('choices');
    }

    public function test_an_unsupported_answer_format_is_rejected(): void
    {
        $this->actingAs($this->platformAdmin());
        $assessment = $this->draftAssessment();
        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Format Section']);
        $section = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();

        foreach (['date', 'file_upload'] as $unsupportedFormat) {
            $this->post(route('admin.assessments.questions.store', [$assessment, $section]), [
                'question_text' => 'Unsupported format question',
                'format' => $unsupportedFormat,
            ])->assertSessionHasErrors('format');
        }
    }

    public function test_a_number_question_rejects_a_minimum_above_the_maximum(): void
    {
        $this->actingAs($this->platformAdmin());
        $assessment = $this->draftAssessment();
        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Numeric Section']);
        $section = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();

        $this->post(route('admin.assessments.questions.store', [$assessment, $section]), [
            'question_text' => 'How many nurses are on duty?',
            'format' => 'number',
            'numeric_min' => 50,
            'numeric_max' => 10,
        ])->assertSessionHasErrors('numeric_min');
    }

    public function test_questions_can_be_reordered_and_removed(): void
    {
        $this->actingAs($this->platformAdmin());
        $assessment = $this->draftAssessment();
        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Ordering Section']);
        $section = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();

        foreach (['First question', 'Second question'] as $text) {
            $this->post(route('admin.assessments.questions.store', [$assessment, $section]), [
                'question_text' => $text,
                'format' => 'yes_no',
            ]);
        }

        $placements = FrameworkQuestionPlacement::where('framework_version_id', $assessment->framework_version_id)
            ->orderBy('display_order')->get();
        $this->patch(route('admin.assessments.questions.move', [$assessment, $placements[1]]), ['direction' => 'up'])
            ->assertRedirect();

        $ordered = FrameworkQuestionPlacement::where('framework_version_id', $assessment->framework_version_id)
            ->with('questionVersion')->orderBy('display_order')->get()
            ->map(fn ($placement) => $placement->questionVersion->question_text)->all();
        $this->assertSame(['Second question', 'First question'], $ordered);

        $this->delete(route('admin.assessments.questions.destroy', [$assessment, $placements[0]]))->assertRedirect();
        $this->assertSame(1, FrameworkQuestionPlacement::where('framework_version_id', $assessment->framework_version_id)->count());
    }

    public function test_workspace_user_cannot_add_sections_or_questions(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin);
        $assessment = $this->draftAssessment();
        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Guarded']);
        $section = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Hacked'])
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.assessments.questions.store', [$assessment, $section]), [
                'question_text' => 'Hacked question',
                'format' => 'yes_no',
            ])->assertForbidden();
    }

    public function test_the_build_screen_does_not_expose_governance_vocabulary(): void
    {
        $this->actingAs($this->platformAdmin());
        $assessment = $this->draftAssessment();
        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Plain Language']);

        $response = $this->get(route('admin.assessments.build', $assessment));

        // Governance vocabulary still appears in the Advanced Tools navigation by design.
        // What the build screen must never do is ask the author to set a governance knob.
        foreach ([
            'name="sub_index_id"',
            'name="framework_indicator_id"',
            'name="framework_section_id"',
            'name="question_version_id"',
            'name="criticality"',
            'name="weight"',
            'name="scoring_contribution"',
            'name="indicator_code"',
            'name="display_order"',
        ] as $governanceField) {
            $response->assertDontSee($governanceField, false);
        }
    }

    // ---- Scoring, evidence and approval ----

    /** @return array{0: DepartmentFrameworkVersion, 1: FrameworkQuestionPlacement} */
    private function assessmentWithOneNewQuestion(string $format = 'yes_no'): array
    {
        $assessment = $this->draftAssessment();
        $this->post(route('admin.assessments.sections.store', $assessment), ['section_name' => 'Settings Section']);
        $section = FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->firstOrFail();
        $this->post(route('admin.assessments.questions.store', [$assessment, $section]), [
            'question_text' => 'Scoring fixture question?',
            'format' => $format,
        ]);

        return [$assessment, FrameworkQuestionPlacement::where('framework_version_id', $assessment->framework_version_id)->firstOrFail()];
    }

    /**
     * Leaves the department with exactly one score, so tests covering the unambiguous
     * case are not affected by whichever scores the seed happened to create.
     */
    private function scoringGroupFor(DepartmentFrameworkVersion $assessment): SubIndex
    {
        SubIndex::where('module_id', $assessment->module_id)->delete();

        return SubIndex::create([
            'module_id' => $assessment->module_id,
            'domain_id' => Domain::orderBy('domain_id')->firstOrFail()->domain_id,
            'acronym' => 'TST',
            'full_name' => 'Test Readiness Score',
        ]);
    }

    public function test_scoring_can_be_switched_on_with_points_and_a_critical_answer(): void
    {
        $this->actingAs($this->platformAdmin());
        [$assessment, $placement] = $this->assessmentWithOneNewQuestion();
        $this->scoringGroupFor($assessment);

        $this->put(route('admin.assessments.questions.settings.save', [$assessment, $placement]), [
            'is_scored' => 1,
            'importance' => 'high',
            'evidence_mode' => 'none',
            'points' => [1 => 100, 2 => 0],
            'critical' => [2 => 1],
        ])->assertRedirect(route('admin.assessments.build', $assessment))->assertSessionHasNoErrors();

        $placement->refresh();
        $this->assertTrue((bool) $placement->scoring_contribution);
        $this->assertEquals(2.0, (float) $placement->weight);
        $this->assertSame('CRITICAL', $placement->criticality);
        $this->assertNotNull($placement->sub_index_id);

        $options = collect($placement->questionVersion->fresh()->options)->keyBy('option_label');
        $this->assertEquals(100.0, $options['Yes']['score_weight']);
        $this->assertTrue($options['No']['critical_failure']);
    }

    public function test_a_scored_question_is_auto_assigned_when_only_one_score_exists(): void
    {
        $this->actingAs($this->platformAdmin());
        [$assessment, $placement] = $this->assessmentWithOneNewQuestion();
        $group = $this->scoringGroupFor($assessment);

        $this->put(route('admin.assessments.questions.settings.save', [$assessment, $placement]), [
            'is_scored' => 1,
            'evidence_mode' => 'none',
            'points' => [1 => 100, 2 => 0],
        ])->assertSessionHasNoErrors();

        $this->assertSame((int) $group->sub_index_id, (int) $placement->fresh()->sub_index_id);
    }

    public function test_scoring_is_refused_when_the_department_has_no_score(): void
    {
        $this->actingAs($this->platformAdmin());
        [$assessment, $placement] = $this->assessmentWithOneNewQuestion();
        SubIndex::where('module_id', $assessment->module_id)->delete();

        $this->put(route('admin.assessments.questions.settings.save', [$assessment, $placement]), [
            'is_scored' => 1,
            'evidence_mode' => 'none',
            'points' => [1 => 100, 2 => 0],
        ])->assertSessionHasErrors('scoring');

        $this->assertFalse((bool) $placement->fresh()->scoring_contribution);
    }

    public function test_a_score_can_be_created_when_the_department_has_none(): void
    {
        $this->actingAs($this->platformAdmin());
        [$assessment, $placement] = $this->assessmentWithOneNewQuestion();
        SubIndex::where('module_id', $assessment->module_id)->delete();

        $this->post(route('admin.assessments.scoring-groups.store', $assessment), [
            'name' => 'Outpatient Readiness',
            'domain_id' => Domain::firstOrFail()->domain_id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sub_indices', [
            'module_id' => $assessment->module_id,
            'full_name' => 'Outpatient Readiness',
        ]);
    }

    public function test_choosing_between_several_scores_is_required(): void
    {
        $this->actingAs($this->platformAdmin());
        [$assessment, $placement] = $this->assessmentWithOneNewQuestion();
        $domainId = Domain::firstOrFail()->domain_id;
        SubIndex::create(['module_id' => $assessment->module_id, 'domain_id' => $domainId, 'acronym' => 'AAA', 'full_name' => 'Score A']);
        SubIndex::create(['module_id' => $assessment->module_id, 'domain_id' => $domainId, 'acronym' => 'BBB', 'full_name' => 'Score B']);

        $this->put(route('admin.assessments.questions.settings.save', [$assessment, $placement]), [
            'is_scored' => 1,
            'evidence_mode' => 'none',
            'points' => [1 => 100, 2 => 0],
        ])->assertSessionHasErrors('scoring_group');
    }

    public function test_written_answers_cannot_be_scored(): void
    {
        $this->actingAs($this->platformAdmin());
        [$assessment, $placement] = $this->assessmentWithOneNewQuestion('text');
        $this->scoringGroupFor($assessment);

        $this->put(route('admin.assessments.questions.settings.save', [$assessment, $placement]), [
            'is_scored' => 1,
            'evidence_mode' => 'none',
        ])->assertSessionHasErrors('is_scored');
    }

    public function test_a_scored_number_question_requires_ranges(): void
    {
        $this->actingAs($this->platformAdmin());
        [$assessment, $placement] = $this->assessmentWithOneNewQuestion('number');
        $this->scoringGroupFor($assessment);

        $this->put(route('admin.assessments.questions.settings.save', [$assessment, $placement]), [
            'is_scored' => 1,
            'evidence_mode' => 'none',
            'bands' => [],
        ])->assertSessionHasErrors('bands');

        $this->put(route('admin.assessments.questions.settings.save', [$assessment, $placement]), [
            'is_scored' => 1,
            'evidence_mode' => 'none',
            'bands' => [['min' => 0, 'max' => 49, 'points' => 0], ['min' => 50, 'max' => 100, 'points' => 100]],
        ])->assertSessionHasNoErrors();

        $this->assertCount(2, $placement->fresh()->questionVersion->numeric_bands);
    }

    public function test_an_evidence_prompt_is_stored_and_reaches_the_respondent_snapshot(): void
    {
        $this->actingAs($this->platformAdmin());
        [$assessment, $placement] = $this->assessmentWithOneNewQuestion();

        $this->put(route('admin.assessments.questions.settings.save', [$assessment, $placement]), [
            'is_scored' => 0,
            'evidence_mode' => 'note',
            'evidence_prompt' => 'Name the register you checked.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Name the register you checked.', $placement->fresh()->evidence_expectation);

        // The prompt must survive into the frozen framework payload, otherwise the
        // respondent never sees what the author asked for.
        $payload = app(FrameworkContentService::class)->frameworkPayload($assessment->fresh());
        $this->assertSame('Name the register you checked.', $payload['questions'][0]['evidence_expectation']);
    }

    public function test_a_question_is_only_approved_by_an_explicit_action(): void
    {
        $this->actingAs($this->platformAdmin());
        [$assessment, $placement] = $this->assessmentWithOneNewQuestion();

        // Building, scoring and saving never approve anything on their own.
        $this->scoringGroupFor($assessment);
        $this->put(route('admin.assessments.questions.settings.save', [$assessment, $placement]), [
            'is_scored' => 1,
            'evidence_mode' => 'none',
            'points' => [1 => 100, 2 => 0],
        ]);
        $this->assertSame(QuestionVersion::STATUS_DRAFT, $placement->fresh()->questionVersion->status);

        $this->patch(route('admin.assessments.questions.approve', [$assessment, $placement]))
            ->assertSessionHasNoErrors();

        $version = $placement->fresh()->questionVersion;
        $this->assertSame(QuestionVersion::STATUS_PUBLISHED, $version->status);
        $this->assertNotNull($version->content_hash);
        $this->assertDatabaseHas('audit_logs', ['event' => 'assessment.question.approved']);
    }

    public function test_an_approved_question_becomes_immutable(): void
    {
        $this->actingAs($this->platformAdmin());
        [$assessment, $placement] = $this->assessmentWithOneNewQuestion();
        $this->patch(route('admin.assessments.questions.approve', [$assessment, $placement]));

        $this->expectException(\LogicException::class);
        $placement->fresh()->questionVersion->update(['question_text' => 'Changed after approval']);
    }

    public function test_workspace_user_cannot_change_scoring_or_approve(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin);
        [$assessment, $placement] = $this->assessmentWithOneNewQuestion();

        $this->actingAs(User::factory()->create())
            ->put(route('admin.assessments.questions.settings.save', [$assessment, $placement]), [
                'is_scored' => 1, 'evidence_mode' => 'none',
            ])->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->patch(route('admin.assessments.questions.approve', [$assessment, $placement]))
            ->assertForbidden();
    }

    public function test_the_draft_is_created_as_a_focused_framework_version_without_the_author_choosing_one(): void
    {
        $this->actingAs($this->platformAdmin())->post(route('admin.assessments.store'), [
            'display_name' => 'Type Defaulted Assessment',
            'module_id' => $this->activeModule()->module_id,
        ]);

        $assessment = DepartmentFrameworkVersion::where('display_name', 'Type Defaulted Assessment')->firstOrFail();

        $this->assertSame(DepartmentFrameworkVersion::TYPE_FOCUSED, $assessment->framework_type);
        $this->assertGreaterThanOrEqual(1, $assessment->version_number);
    }
}
