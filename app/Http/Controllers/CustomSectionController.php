<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\LocalCustomSection;
use App\Services\Reporting\CustomSectionScoringService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The workspace's own "Tailored by your team" section on an assessment.
 *
 * Additive only: a workspace adds its own questions to sit alongside the governed ones. They
 * are never mixed into the official Vytte score — they live in their own lane and are scored
 * and reported separately. Governed questions can never be removed, so the official score
 * always measures the same standard set and stays comparable.
 */
class CustomSectionController extends Controller
{
    public function edit(Assessment $assessment): View
    {
        $this->authorize('update', $assessment);
        $section = $assessment->localCustomSections()->first();

        return view('assessments.custom-section', compact('assessment', 'section'));
    }

    public function save(Request $request, Assessment $assessment): RedirectResponse
    {
        $this->authorize('update', $assessment);

        $validated = $request->validate([
            'section_title' => ['nullable', 'string', 'max:180'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'questions' => ['array', 'max:50'],
            'questions.*.id' => ['nullable', 'string'],
            'questions.*.text' => ['required', 'string', 'max:500'],
            'questions.*.type' => ['required', 'in:YES_NO,SCALE_5'],
            'questions.*.good' => ['nullable', 'in:YES,NO'],
            'questions.*.reversed' => ['nullable'],
        ]);

        // Keep each question's id stable across edits so any answers stay attached to it.
        $questions = collect($validated['questions'] ?? [])->map(fn ($q) => [
            'id' => ! empty($q['id']) ? $q['id'] : (string) Str::uuid(),
            'text' => $q['text'],
            'response_type' => $q['type'],
            'good_answer' => $q['type'] === 'YES_NO' ? ($q['good'] ?? 'YES') : null,
            'reversed' => $q['type'] === 'SCALE_5' ? (bool) ($q['reversed'] ?? false) : false,
        ])->values()->all();

        LocalCustomSection::updateOrCreate(
            ['assessment_id' => $assessment->assessment_id],
            [
                'workspace_id' => app('current.workspace')->workspace_id,
                'section_title' => ($validated['section_title'] ?? null) ?: 'Tailored by your team',
                'instructions' => $validated['instructions'] ?? null,
                'questions' => $questions,
                'created_by' => auth()->id(),
            ],
        );

        return redirect()->route('assessments.custom.edit', $assessment)
            ->with('success', 'Your questions were saved.');
    }

    /**
     * Record answers to the tailored questions and compute their private 0-100 score.
     */
    public function saveAnswers(Request $request, Assessment $assessment, CustomSectionScoringService $scorer): RedirectResponse
    {
        $this->authorize('update', $assessment);

        $section = $assessment->localCustomSections()->firstOrFail();

        $validated = $request->validate([
            'answers' => ['array'],
            'answers.*' => ['nullable', 'string', 'max:10'],
        ]);

        $answers = collect($validated['answers'] ?? [])->filter(fn ($v) => $v !== null && $v !== '')->all();
        $result = $scorer->score($section->questions ?? [], $answers);

        $section->update([
            'answers' => $answers,
            'custom_score' => $result['overall'],
            'scored_at' => now(),
        ]);

        return redirect()->route('assessments.results', ['assessment' => $assessment, 'tab' => 'overview'])
            ->with('success', 'Your tailored section was scored.');
    }
}
