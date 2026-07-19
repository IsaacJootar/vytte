<?php

namespace App\Services;

use App\Models\DepartmentFrameworkVersion;
use App\Models\QuestionVersion;
use App\Support\ResponseInputContract;

/**
 * Explains, in the author's language, what still stands between a draft assessment and
 * publication.
 *
 * This does not decide whether publication is allowed. DepartmentFrameworkPublishingService
 * and CataloguePublishingService remain the authority and run their full checks regardless.
 * This service exists so the author sees "Question X is still waiting for approval" instead
 * of "Frameworks can only publish exact published question versions", and so blockers can
 * be listed before they attempt to publish rather than only afterwards.
 *
 * Each blocker mirrors a rule those services enforce. If a rule is ever missed here, the
 * publisher still refuses; the cost is a less friendly message, never a governance gap.
 */
class AssessmentReadinessService
{
    /**
     * @return array{ready: bool, blockers: list<array{message: string, section?: string, placement_id?: string, kind: string}>, summary: array<string, int|float|null>}
     */
    public function evaluate(DepartmentFrameworkVersion $assessment): array
    {
        $assessment->loadMissing([
            'module',
            'sections.questionPlacements.questionVersion.questionType',
        ]);

        $blockers = [];
        $placements = $assessment->sections->flatMap->questionPlacements;

        if ($assessment->status !== DepartmentFrameworkVersion::STATUS_DRAFT) {
            $blockers[] = ['kind' => 'status', 'message' => 'This assessment has already been published.'];
        }

        if ($assessment->sections->isEmpty()) {
            $blockers[] = ['kind' => 'structure', 'message' => 'Add at least one section before publishing.'];
        }

        foreach ($assessment->sections as $section) {
            if ($section->questionPlacements->isEmpty()) {
                $blockers[] = [
                    'kind' => 'structure',
                    'section' => $section->section_name,
                    'message' => 'The section "'.$section->section_name.'" has no questions. Add a question or remove the section.',
                ];
            }
        }

        if ($placements->isEmpty()) {
            $blockers[] = ['kind' => 'structure', 'message' => 'Add at least one question before publishing.'];
        }

        if (blank($assessment->source_authority) || blank($assessment->license_code)) {
            $blockers[] = [
                'kind' => 'provenance',
                'message' => 'Record where this assessment comes from and how it may be used before publishing.',
            ];
        }

        foreach ($placements as $placement) {
            $version = $placement->questionVersion;
            $label = $this->shortLabel($placement->local_display_text ?: $version?->question_text);
            $type = $version?->questionType?->type_code;

            if ($version?->status !== QuestionVersion::STATUS_PUBLISHED) {
                $blockers[] = [
                    'kind' => 'approval',
                    'placement_id' => $placement->framework_question_placement_id,
                    'section' => $placement->section?->section_name,
                    'message' => 'The question "'.$label.'" is still waiting for approval.',
                ];

                continue;
            }

            if (! ResponseInputContract::supports($type)) {
                $blockers[] = [
                    'kind' => 'answer',
                    'placement_id' => $placement->framework_question_placement_id,
                    'message' => 'The question "'.$label.'" uses an answer format Vytte cannot publish.',
                ];
            }

            if (in_array($type, ResponseInputContract::OPTION_TYPES, true) && collect($version->options ?? [])->isEmpty()) {
                $blockers[] = [
                    'kind' => 'answer',
                    'placement_id' => $placement->framework_question_placement_id,
                    'message' => 'The question "'.$label.'" has no answer choices.',
                ];
            }

            if ($placement->scoring_contribution) {
                if ($type === 'OPEN_ENDED') {
                    $blockers[] = [
                        'kind' => 'scoring',
                        'placement_id' => $placement->framework_question_placement_id,
                        'message' => 'The written answer "'.$label.'" cannot affect the score. Turn scoring off for it.',
                    ];
                }

                if ($placement->sub_index_id === null) {
                    $blockers[] = [
                        'kind' => 'scoring',
                        'placement_id' => $placement->framework_question_placement_id,
                        'message' => 'The question "'.$label.'" affects the score but is not attached to one. Open its scoring settings.',
                    ];
                }

                if (in_array($type, ResponseInputContract::OPTION_TYPES, true)
                    && collect($version->options ?? [])->contains(fn ($option) => ($option['score_weight'] ?? null) === null)) {
                    $blockers[] = [
                        'kind' => 'scoring',
                        'placement_id' => $placement->framework_question_placement_id,
                        'message' => 'Give every answer to "'.$label.'" a number of points.',
                    ];
                }

                if ($type === 'NUMERIC' && collect($version->numeric_bands ?? [])->isEmpty()) {
                    $blockers[] = [
                        'kind' => 'scoring',
                        'placement_id' => $placement->framework_question_placement_id,
                        'message' => 'The number question "'.$label.'" needs scoring ranges before it can be scored.',
                    ];
                }
            }
        }

        return [
            'ready' => $blockers === [],
            'blockers' => $blockers,
            'summary' => $this->summarise($assessment, $placements),
        ];
    }

    /**
     * @return array<string, int|float|null>
     */
    private function summarise(DepartmentFrameworkVersion $assessment, $placements): array
    {
        $scored = $placements->where('scoring_contribution', true);

        return [
            'sections' => $assessment->sections->count(),
            'questions' => $placements->count(),
            'scored_questions' => $scored->count(),
            'unscored_questions' => $placements->count() - $scored->count(),
            'critical_questions' => $placements->where('criticality', 'CRITICAL')->count(),
            'evidence_questions' => $placements->filter(fn ($placement) => filled($placement->evidence_expectation))->count(),
            'awaiting_approval' => $placements->filter(
                fn ($placement) => $placement->questionVersion?->status !== QuestionVersion::STATUS_PUBLISHED
            )->count(),
            'maximum_score' => $scored->isEmpty() ? null : 100.0,
        ];
    }

    private function shortLabel(?string $text): string
    {
        return str($text ?? 'Untitled question')->limit(70)->value();
    }
}
