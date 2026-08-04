<?php

namespace App\Http\Controllers;

use App\Actions\CompleteSelfAssessment;
use App\Models\Assessment;
use App\Models\LocalCustomSection;
use App\Services\Reporting\CustomSectionScoringService;
use App\Support\LocalQuestionFormat;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The workspace's own local questions section on an assessment.
 *
 * Additive only: a workspace adds its own questions to sit alongside the published ones. They
 * never mutate the published assessment's frozen score or benchmark — only Yes/No, Yes/No/Not
 * applicable, and 1-5 rating questions may optionally contribute to a separate, clearly labelled
 * optional local score. Published questions can never be removed, so the published score always
 * measures the same standard set and stays comparable.
 *
 * Questions are authored only while the assessment is still open; once it is complete the
 * local section is frozen. They are answered as the last step of the same assessment,
 * right before it is finished.
 */
class CustomSectionController extends Controller
{
    public function edit(Assessment $assessment): View|RedirectResponse
    {
        $this->authorize('update', $assessment);
        if ($assessment->status === Assessment::STATUS_COMPLETE) {
            return redirect()->route('assessments.results', $assessment);
        }

        $section = $assessment->localCustomSections()->first();

        return view('assessments.custom-section', compact('assessment', 'section'));
    }

    public function save(Request $request, Assessment $assessment): RedirectResponse
    {
        $this->authorize('update', $assessment);
        abort_if($assessment->status === Assessment::STATUS_COMPLETE, 403);

        $validated = $request->validate([
            'section_title' => ['nullable', 'string', 'max:180'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'questions' => ['array', 'max:50'],
            'questions.*.id' => ['nullable', 'string'],
            'questions.*.text' => ['required', 'string', 'max:500'],
            'questions.*.type' => ['required', 'in:'.implode(',', LocalQuestionFormat::keys())],
            'questions.*.good' => ['nullable', 'in:YES,NO'],
            'questions.*.direction' => ['nullable', 'in:HIGHER_IS_BETTER,LOWER_IS_BETTER'],
            'questions.*.is_scored' => ['nullable', 'boolean'],
            'questions.*.choices' => ['nullable', 'array', 'max:10'],
            'questions.*.choices.*' => ['nullable', 'string', 'max:180'],
            'questions.*.numeric_min' => ['nullable', 'numeric'],
            'questions.*.numeric_max' => ['nullable', 'numeric'],
            'questions.*.numeric_unit' => ['nullable', 'string', 'max:40'],
        ]);

        // Keep each question's id stable across edits so any answers stay attached to it.
        $questions = collect($validated['questions'] ?? [])->map(function ($q, $index) {
            $type = $q['type'];
            $choices = collect($q['choices'] ?? [])->map(fn ($choice) => trim((string) $choice))->filter()->unique()->values();
            if (in_array($type, [LocalQuestionFormat::SINGLE_SELECT, LocalQuestionFormat::MULTI_SELECT], true) && $choices->count() < 2) {
                throw ValidationException::withMessages(["questions.$index.choices" => 'Add at least two different answer choices.']);
            }
            if (isset($q['numeric_min'], $q['numeric_max']) && (float) $q['numeric_min'] > (float) $q['numeric_max']) {
                throw ValidationException::withMessages(["questions.$index.numeric_min" => 'The minimum cannot be greater than the maximum.']);
            }

            $canScore = LocalQuestionFormat::canScore($type);

            return [
                'id' => ! empty($q['id']) ? $q['id'] : (string) Str::uuid(),
                'text' => $q['text'],
                'response_type' => $type,
                'choices' => $choices->all(),
                'numeric_min' => $type === LocalQuestionFormat::NUMERIC ? ($q['numeric_min'] ?? null) : null,
                'numeric_max' => $type === LocalQuestionFormat::NUMERIC ? ($q['numeric_max'] ?? null) : null,
                'numeric_unit' => $type === LocalQuestionFormat::NUMERIC ? ($q['numeric_unit'] ?? null) : null,
                'is_scored' => $canScore && (bool) ($q['is_scored'] ?? true),
                'good_answer' => in_array($type, [LocalQuestionFormat::YES_NO, LocalQuestionFormat::YES_NO_NA], true) ? ($q['good'] ?? 'YES') : null,
                'score_direction' => $type === LocalQuestionFormat::SCALE_5 ? ($q['direction'] ?? 'HIGHER_IS_BETTER') : null,
            ];
        })->values()->all();

        if ($questions === []) {
            // The step is optional. Left blank, there is nothing to keep — drop any unanswered
            // section so an empty one is never carried forward.
            LocalCustomSection::where('assessment_id', $assessment->assessment_id)
                ->whereNull('scored_at')
                ->delete();
        } else {
            LocalCustomSection::updateOrCreate(
                ['assessment_id' => $assessment->assessment_id],
                [
                    'workspace_id' => app('current.workspace')->workspace_id,
                    'section_title' => ($validated['section_title'] ?? null) ?: 'Local context',
                    'instructions' => $validated['instructions'] ?? null,
                    'questions' => $questions,
                    'created_by' => auth()->id(),
                ],
            );
        }

        // When saving from the setup wizard, continue to the next step instead of the editor.
        if ($request->input('redirect_to') === 'setup') {
            return redirect()->route('assessments.setup', ['assessment' => $assessment, 'step' => 3])
                ->with('success', 'Your questions were saved.');
        }

        return redirect()->route('assessments.custom.edit', $assessment)
            ->with('success', 'Your questions were saved.');
    }

    /**
     * The last step of the run: answer the tailored questions before finishing.
     */
    public function answer(Assessment $assessment): View|RedirectResponse
    {
        $this->authorize('update', $assessment);

        if ($assessment->status === Assessment::STATUS_COMPLETE) {
            return redirect()->route('assessments.results', $assessment);
        }

        $section = $assessment->localCustomSections()->first();
        if (! $section || empty($section->questions)) {
            return redirect()->route('assessments.run', $assessment);
        }

        return view('assessments.custom-answer', compact('assessment', 'section'));
    }

    /**
     * Score the tailored answers (their own 0-100 lane), then finish the whole assessment.
     * Skipping is allowed — the tailored section is optional.
     */
    public function finish(Request $request, Assessment $assessment, CustomSectionScoringService $scorer, CompleteSelfAssessment $complete): RedirectResponse
    {
        $this->authorize('update', $assessment);

        $section = $assessment->localCustomSections()->firstOrFail();

        $validated = $request->validate([
            'answers' => ['array'],
            'answers.*' => ['nullable'],
        ]);

        $questions = collect($section->questions ?? [])->keyBy('id');
        $answers = collect($validated['answers'] ?? [])->mapWithKeys(function ($value, $questionId) use ($questions) {
            $question = $questions->get($questionId);
            $normalized = $question ? LocalQuestionFormat::normalizeAnswer($question, $value) : null;

            return LocalQuestionFormat::isBlank($normalized) ? [] : [$questionId => $normalized];
        })->all();
        $result = $scorer->score($section->questions ?? [], $answers);

        $section->update([
            'answers' => $answers,
            'custom_score' => $result['overall'],
            'scored_at' => now(),
        ]);

        if ($assessment->status !== Assessment::STATUS_COMPLETE) {
            $complete->handle($assessment->fresh());
        }

        return redirect()->route('assessments.results', $assessment)
            ->with('success', 'Assessment submitted.');
    }
}
