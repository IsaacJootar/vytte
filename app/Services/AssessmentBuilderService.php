<?php

namespace App\Services;

use App\Models\DepartmentFrameworkVersion;
use App\Models\FrameworkIndicator;
use App\Models\FrameworkQuestionPlacement;
use App\Models\FrameworkSection;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionType;
use App\Models\QuestionVersion;
use App\Models\SubIndex;
use App\Support\AnswerFormat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates the governed objects behind the simple authoring experience.
 *
 * The author works with sections and questions. Underneath, this service maintains
 * framework sections, a hidden default indicator per section, reusable question
 * identities, immutable question versions and framework question placements, using the
 * same models and rules as Advanced Tools.
 *
 * It adds no governance rules of its own and relaxes none. Publication validation stays in
 * DepartmentFrameworkPublishingService and remains the authority on what may be published.
 */
class AssessmentBuilderService
{
    /**
     * Every section carries one indicator so that placements satisfy the framework
     * structure. Authors never see or name it: indicators remain an analytical grouping
     * that Advanced Tools can refine later.
     */
    private const DEFAULT_INDICATOR_SUFFIX = 'MAIN';

    public function addSection(DepartmentFrameworkVersion $assessment, string $name, ?string $description = null): FrameworkSection
    {
        $this->assertDraft($assessment);

        return DB::transaction(function () use ($assessment, $name, $description): FrameworkSection {
            $order = ((int) $assessment->sections()->max('display_order')) + 1;
            $code = $this->uniqueSectionCode($assessment, $name);

            $section = FrameworkSection::create([
                'framework_version_id' => $assessment->framework_version_id,
                'section_code' => $code,
                'section_name' => $name,
                'purpose' => $description,
                'display_order' => $order,
            ]);

            FrameworkIndicator::create([
                'framework_version_id' => $assessment->framework_version_id,
                'framework_section_id' => $section->framework_section_id,
                'indicator_code' => $code.'-'.self::DEFAULT_INDICATOR_SUFFIX,
                'indicator_name' => $name,
                'display_order' => 1,
            ]);

            return $section;
        });
    }

    public function renameSection(FrameworkSection $section, string $name, ?string $description = null): FrameworkSection
    {
        $this->assertDraft($section->frameworkVersion);

        $section->update(['section_name' => $name, 'purpose' => $description]);
        $this->defaultIndicator($section)?->update(['indicator_name' => $name]);

        return $section->fresh();
    }

    /**
     * Moves a section one place up or down. Sibling display orders are rewritten in a
     * single transaction; framework_sections carries no unique order constraint.
     */
    public function moveSection(FrameworkSection $section, string $direction): void
    {
        $this->assertDraft($section->frameworkVersion);

        DB::transaction(function () use ($section, $direction): void {
            $sections = FrameworkSection::where('framework_version_id', $section->framework_version_id)
                ->orderBy('display_order')
                ->lockForUpdate()
                ->get();

            $index = $sections->search(fn ($item) => $item->framework_section_id === $section->framework_section_id);
            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || $target < 0 || $target >= $sections->count()) {
                return;
            }

            $reordered = $sections->values()->all();
            [$reordered[$index], $reordered[$target]] = [$reordered[$target], $reordered[$index]];

            foreach ($reordered as $position => $item) {
                $item->update(['display_order' => $position + 1]);
            }
        });
    }

    public function deleteSection(FrameworkSection $section): void
    {
        $this->assertDraft($section->frameworkVersion);

        if ($this->questionCountFor($section) > 0) {
            throw ValidationException::withMessages([
                'section' => 'Remove the questions in this section before deleting it.',
            ]);
        }

        $section->delete();
    }

    /**
     * Places an existing library question version into a section. Scoring and evidence are
     * deliberately left at their safe defaults; they are configured in a later step.
     */
    public function addLibraryQuestion(FrameworkSection $section, QuestionVersion $version): FrameworkQuestionPlacement
    {
        $assessment = $section->frameworkVersion;
        $this->assertDraft($assessment);
        $this->assertQuestionNotAlreadyUsed($assessment, $version->question_id);

        return DB::transaction(function () use ($assessment, $section, $version): FrameworkQuestionPlacement {
            return FrameworkQuestionPlacement::create([
                'framework_version_id' => $assessment->framework_version_id,
                'framework_section_id' => $section->framework_section_id,
                'framework_indicator_id' => $this->requireDefaultIndicator($section)->framework_indicator_id,
                'question_id' => $version->question_id,
                'question_version_id' => $version->question_version_id,
                'display_order' => $this->nextQuestionOrder($assessment),
                'is_required' => true,
                'scoring_contribution' => false,
                'weight' => 1.0,
                'criticality' => 'STANDARD',
            ]);
        });
    }

    /**
     * Creates a reusable question identity, its first immutable draft version and, for
     * option formats, the answer option rows the runtime stores responses against, then
     * places it in the section.
     *
     * Answer options must exist as question_options rows: responses.value_option_id is a
     * foreign key to that table, so options that live only inside version JSON cannot be
     * answered.
     *
     * @param  array{format: string, question_text: string, choices?: list<string>, numeric?: array{min?: float|null, max?: float|null, unit?: string|null}}  $input
     */
    public function createQuestion(FrameworkSection $section, array $input): FrameworkQuestionPlacement
    {
        $assessment = $section->frameworkVersion;
        $this->assertDraft($assessment);

        $format = AnswerFormat::require($input['format']);
        $choices = AnswerFormat::choicesFor($format, $input['choices'] ?? []);

        return DB::transaction(function () use ($assessment, $section, $input, $format, $choices): FrameworkQuestionPlacement {
            $typeId = QuestionType::where('type_code', $format['type_code'])->value('type_id');
            $numeric = $format['key'] === AnswerFormat::NUMBER ? $this->numericConfig($input['numeric'] ?? []) : null;

            $question = Question::create([
                'module_id' => $assessment->module_id,
                'question_number' => ((int) Question::where('module_id', $assessment->module_id)->max('question_number')) + 1,
                'question_code' => $this->uniqueQuestionCode($assessment),
                'question_text' => $input['question_text'],
                'type_id' => $typeId,
                'display_order' => ((int) Question::where('module_id', $assessment->module_id)->max('display_order')) + 1,
                'is_active' => true,
                'is_scored' => false,
                'source' => 'PLATFORM_ADMIN',
                'question_status' => 'DRAFT',
                'standard_alignment_status' => 'PENDING_REVIEW',
                'numeric_unit' => $numeric['unit'] ?? null,
                'numeric_min' => $numeric['min'] ?? null,
                'numeric_max' => $numeric['max'] ?? null,
            ]);

            $options = [];
            foreach ($choices as $index => $label) {
                $option = QuestionOption::create([
                    'question_id' => $question->question_id,
                    'option_label' => $label,
                    'option_order' => $index + 1,
                    'score_weight' => null,
                    'is_flagged_pain_point' => false,
                ]);

                $options[] = [
                    'option_id' => (int) $option->option_id,
                    'option_key' => 'OPT'.($index + 1),
                    'option_label' => $label,
                    'option_order' => $index + 1,
                    'score_weight' => null,
                    'critical_failure' => false,
                ];
            }

            $version = QuestionVersion::create([
                'question_id' => $question->question_id,
                'version_number' => 1,
                'status' => QuestionVersion::STATUS_DRAFT,
                'question_text' => $input['question_text'],
                'type_id' => $typeId,
                'options' => $options,
                'numeric_config' => $numeric,
                'numeric_bands' => [],
                'requires_observation' => false,
                'methodology_notes' => 'Created in the Vytte assessment builder.',
            ]);

            return FrameworkQuestionPlacement::create([
                'framework_version_id' => $assessment->framework_version_id,
                'framework_section_id' => $section->framework_section_id,
                'framework_indicator_id' => $this->requireDefaultIndicator($section)->framework_indicator_id,
                'question_id' => $question->question_id,
                'question_version_id' => $version->question_version_id,
                'display_order' => $this->nextQuestionOrder($assessment),
                'is_required' => true,
                'scoring_contribution' => false,
                'weight' => 1.0,
                'criticality' => 'STANDARD',
            ]);
        });
    }

    /**
     * Scoring groups available to an assessment. A scored placement must belong to one:
     * DepartmentFrameworkPublishingService rejects publication otherwise.
     *
     * @return Collection<int, SubIndex>
     */
    public function scoringGroupsFor(DepartmentFrameworkVersion $assessment)
    {
        return SubIndex::where('module_id', $assessment->module_id)->orderBy('full_name')->get();
    }

    /**
     * Resolves the scoring group for a placement being switched on.
     *
     * Exactly one available group is selected automatically. Several require an explicit
     * choice. None is a real blocker and is reported as one rather than papered over with
     * an invalid value.
     */
    public function resolveScoringGroup(DepartmentFrameworkVersion $assessment, ?int $chosen): int
    {
        $groups = $this->scoringGroupsFor($assessment);

        if ($groups->isEmpty()) {
            throw ValidationException::withMessages([
                'scoring' => 'This department has no score group yet, so questions here cannot affect a score. Create one first.',
            ]);
        }

        if ($chosen !== null) {
            $match = $groups->firstWhere('sub_index_id', $chosen);
            if (! $match) {
                throw ValidationException::withMessages(['scoring_group' => 'Choose a score group from this department.']);
            }

            return (int) $match->sub_index_id;
        }

        if ($groups->count() === 1) {
            return (int) $groups->first()->sub_index_id;
        }

        throw ValidationException::withMessages([
            'scoring_group' => 'Choose which score this question should contribute to.',
        ]);
    }

    public function createScoringGroup(DepartmentFrameworkVersion $assessment, string $name, int $domainId): SubIndex
    {
        $this->assertDraft($assessment);

        $acronym = Str::of($name)->substr(0, 3)->upper()->append((string) ($this->scoringGroupsFor($assessment)->count() + 1))->value();

        return SubIndex::create([
            'module_id' => $assessment->module_id,
            'domain_id' => $domainId,
            'acronym' => $acronym,
            'full_name' => $name,
            'description' => 'Created in the Vytte assessment builder.',
        ]);
    }

    /**
     * Applies the scoring and evidence settings an author chose.
     *
     * Placement settings apply to this assessment only and stay editable while it is a
     * draft. Answer points and critical-failure marks belong to the question version, so
     * they can only be changed while that version is still a draft: a published library
     * question carries the official values and is left untouched.
     *
     * @param  array{is_scored: bool, scoring_group_id?: int|null, importance?: string, evidence_mode?: string, evidence_prompt?: string|null, points?: array<int, mixed>, critical?: array<int, mixed>, bands?: array<int, array<string, mixed>>}  $input
     */
    public function applyScoringAndEvidence(FrameworkQuestionPlacement $placement, array $input): void
    {
        $assessment = $placement->frameworkVersion;
        $this->assertDraft($assessment);

        $version = $placement->questionVersion;
        $typeCode = $version?->questionType?->type_code;
        $isScored = (bool) $input['is_scored'];

        if ($isScored && $typeCode === 'OPEN_ENDED') {
            throw ValidationException::withMessages([
                'is_scored' => 'Written answers cannot be scored. They are kept as supporting context.',
            ]);
        }

        DB::transaction(function () use ($placement, $assessment, $version, $typeCode, $isScored, $input): void {
            $subIndexId = $isScored
                ? $this->resolveScoringGroup($assessment, $input['scoring_group_id'] ?? null)
                : null;

            $criticalMarked = false;

            if ($isScored && $version?->status === QuestionVersion::STATUS_DRAFT) {
                if (in_array($typeCode, ['SINGLE_SELECT', 'LIKERT'], true)) {
                    $criticalMarked = $this->applyOptionPoints($version, $input['points'] ?? [], $input['critical'] ?? []);
                }

                if ($typeCode === 'NUMERIC') {
                    $this->applyNumericBands($version, $input['bands'] ?? []);
                }
            }

            if ($isScored && $version?->status === QuestionVersion::STATUS_PUBLISHED) {
                $criticalMarked = collect($version->options ?? [])->contains(fn ($option) => (bool) ($option['critical_failure'] ?? false));
            }

            $placement->update([
                'scoring_contribution' => $isScored,
                'sub_index_id' => $subIndexId,
                'weight' => ($input['importance'] ?? 'normal') === 'high' ? 2.0 : 1.0,
                'criticality' => $criticalMarked ? 'CRITICAL' : 'STANDARD',
                'evidence_expectation' => ($input['evidence_mode'] ?? 'none') === 'note'
                    ? (trim((string) ($input['evidence_prompt'] ?? '')) ?: 'Add a brief note describing what supports this answer.')
                    : null,
            ]);
        });
    }

    /**
     * @return bool whether any answer is marked as a critical failure
     */
    private function applyOptionPoints(QuestionVersion $version, array $points, array $critical): bool
    {
        $options = collect($version->options ?? []);

        if ($options->isEmpty()) {
            return false;
        }

        $updated = $options->map(function (array $option) use ($points, $critical): array {
            $order = (int) $option['option_order'];
            $score = $points[$order] ?? null;

            if ($score === null || $score === '') {
                throw ValidationException::withMessages([
                    'points' => 'Give every answer a number of points.',
                ]);
            }

            if (! is_numeric($score) || (float) $score < 0 || (float) $score > 100) {
                throw ValidationException::withMessages([
                    'points' => 'Points must be between 0 and 100.',
                ]);
            }

            $option['score_weight'] = (float) $score;
            $option['critical_failure'] = filter_var($critical[$order] ?? false, FILTER_VALIDATE_BOOLEAN);

            return $option;
        })->all();

        $version->update([
            'options' => app(QuestionOptionSyncService::class)->sync($version->question, $updated),
        ]);

        return collect($updated)->contains(fn ($option) => (bool) $option['critical_failure']);
    }

    /**
     * Scored numeric questions cannot be published without frozen bands, so they are
     * required as soon as scoring is switched on.
     */
    private function applyNumericBands(QuestionVersion $version, array $bands): void
    {
        $normalised = collect($bands)
            ->filter(fn ($band) => filled($band['min'] ?? null) || filled($band['max'] ?? null) || filled($band['points'] ?? null))
            ->values()
            ->map(function (array $band, int $index): array {
                foreach (['min', 'max', 'points'] as $field) {
                    if (! isset($band[$field]) || $band[$field] === '' || ! is_numeric($band[$field])) {
                        throw ValidationException::withMessages([
                            'bands' => 'Give every scoring range a smallest value, a largest value and a number of points.',
                        ]);
                    }
                }

                if ((float) $band['min'] > (float) $band['max']) {
                    throw ValidationException::withMessages([
                        'bands' => 'A scoring range cannot start above where it ends.',
                    ]);
                }

                return [
                    'label' => trim((string) ($band['label'] ?? '')) ?: (($band['min']).' to '.($band['max'])),
                    'min_value' => (float) $band['min'],
                    'max_value' => (float) $band['max'],
                    'score_weight' => (float) $band['points'],
                    'display_order' => $index + 1,
                ];
            })
            ->all();

        if ($normalised === []) {
            throw ValidationException::withMessages([
                'bands' => 'Add at least one scoring range so this number can be scored.',
            ]);
        }

        $version->update(['numeric_bands' => $normalised]);
    }

    /**
     * Approves and freezes a question so the assessment can eventually be published.
     *
     * This is always an explicit act. Nothing here approves a question automatically, and
     * the underlying publishing service still applies every content check.
     */
    public function approveQuestion(QuestionVersion $version, ?string $approverId): QuestionVersion
    {
        if ($version->status === QuestionVersion::STATUS_PUBLISHED) {
            return $version;
        }

        if (! in_array($version->status, [QuestionVersion::STATUS_DRAFT, QuestionVersion::STATUS_INTERNAL_REVIEW, QuestionVersion::STATUS_APPROVED], true)) {
            throw ValidationException::withMessages([
                'question' => 'This question is closed and cannot be approved.',
            ]);
        }

        if ($version->status !== QuestionVersion::STATUS_APPROVED) {
            $version->update([
                'status' => QuestionVersion::STATUS_APPROVED,
                'approved_by' => $approverId,
                'review_notes' => $version->review_notes ?: 'Reviewed and approved in the assessment builder.',
            ]);
        }

        return app(QuestionVersionPublishingService::class)->publish($version->fresh(), $approverId);
    }

    public function removeQuestion(FrameworkQuestionPlacement $placement): void
    {
        $this->assertDraft($placement->frameworkVersion);

        $placement->delete();
    }

    /**
     * Moves a question one place up or down inside its section.
     */
    public function moveQuestion(FrameworkQuestionPlacement $placement, string $direction): void
    {
        $this->assertDraft($placement->frameworkVersion);

        DB::transaction(function () use ($placement, $direction): void {
            $siblings = FrameworkQuestionPlacement::where('framework_section_id', $placement->framework_section_id)
                ->orderBy('display_order')
                ->lockForUpdate()
                ->get();

            $index = $siblings->search(fn ($item) => $item->framework_question_placement_id === $placement->framework_question_placement_id);
            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || $target < 0 || $target >= $siblings->count()) {
                return;
            }

            $swapped = $siblings->values()->all();
            [$swapped[$index], $swapped[$target]] = [$swapped[$target], $swapped[$index]];

            $orders = $siblings->pluck('display_order')->sort()->values();
            foreach ($swapped as $position => $item) {
                $item->update(['display_order' => $orders[$position]]);
            }
        });
    }

    public function questionCountFor(FrameworkSection $section): int
    {
        return FrameworkQuestionPlacement::where('framework_section_id', $section->framework_section_id)->count();
    }

    private function assertDraft(DepartmentFrameworkVersion $assessment): void
    {
        if ($assessment->status !== DepartmentFrameworkVersion::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'This assessment has been published and cannot be changed. Create a new version to make changes.',
            ]);
        }
    }

    private function assertQuestionNotAlreadyUsed(DepartmentFrameworkVersion $assessment, string $questionId): void
    {
        $alreadyUsed = FrameworkQuestionPlacement::where('framework_version_id', $assessment->framework_version_id)
            ->where('question_id', $questionId)
            ->exists();

        if ($alreadyUsed) {
            throw ValidationException::withMessages([
                'question' => 'This question is already used in this assessment. A question can only be asked once per assessment.',
            ]);
        }
    }

    private function defaultIndicator(FrameworkSection $section): ?FrameworkIndicator
    {
        return FrameworkIndicator::where('framework_section_id', $section->framework_section_id)
            ->orderBy('display_order')
            ->first();
    }

    private function requireDefaultIndicator(FrameworkSection $section): FrameworkIndicator
    {
        return $this->defaultIndicator($section) ?? FrameworkIndicator::create([
            'framework_version_id' => $section->framework_version_id,
            'framework_section_id' => $section->framework_section_id,
            'indicator_code' => $section->section_code.'-'.self::DEFAULT_INDICATOR_SUFFIX,
            'indicator_name' => $section->section_name,
            'display_order' => 1,
        ]);
    }

    private function nextQuestionOrder(DepartmentFrameworkVersion $assessment): int
    {
        return ((int) FrameworkQuestionPlacement::where('framework_version_id', $assessment->framework_version_id)
            ->max('display_order')) + 1;
    }

    private function uniqueSectionCode(DepartmentFrameworkVersion $assessment, string $name): string
    {
        $base = Str::of($name)->slug('_')->upper()->limit(60, '')->value() ?: 'SECTION';
        $code = $base;
        $suffix = 2;

        while (FrameworkSection::where('framework_version_id', $assessment->framework_version_id)->where('section_code', $code)->exists()) {
            $code = Str::limit($base, 70, '').'_'.$suffix++;
        }

        return $code;
    }

    private function uniqueQuestionCode(DepartmentFrameworkVersion $assessment): string
    {
        $prefix = strtoupper($assessment->module?->module_code ?? 'VYT');

        do {
            $code = $prefix.'.'.strtoupper(Str::random(8));
        } while (Question::where('question_code', $code)->exists());

        return $code;
    }

    /**
     * @return array{min: float|null, max: float|null, unit: string|null}|null
     */
    private function numericConfig(array $numeric): ?array
    {
        $config = [
            'min' => isset($numeric['min']) && $numeric['min'] !== '' ? (float) $numeric['min'] : null,
            'max' => isset($numeric['max']) && $numeric['max'] !== '' ? (float) $numeric['max'] : null,
            'unit' => isset($numeric['unit']) && $numeric['unit'] !== '' ? (string) $numeric['unit'] : null,
        ];

        if ($config['min'] !== null && $config['max'] !== null && $config['min'] > $config['max']) {
            throw ValidationException::withMessages([
                'numeric_min' => 'The smallest allowed number cannot be larger than the largest allowed number.',
            ]);
        }

        return array_filter($config, fn ($value) => $value !== null) === [] ? null : $config;
    }
}
