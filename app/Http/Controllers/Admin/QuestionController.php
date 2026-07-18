<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function update(Request $request, Question $question): RedirectResponse
    {
        $validated = $request->validate([
            'question_text' => ['required', 'string'],
        ]);

        $question->update($validated);

        $nextVersionNumber = ((int) $question->versions()->max('version_number')) + 1;
        QuestionVersion::create([
            'question_id' => $question->question_id,
            'version_number' => $nextVersionNumber,
            'status' => QuestionVersion::STATUS_DRAFT,
            'question_text' => $validated['question_text'],
            'type_id' => $question->type_id,
            'options' => $question->options->map(fn ($option) => [
                'option_id' => $option->option_id,
                'option_label' => $option->option_label,
                'option_order' => $option->option_order,
                'score_weight' => $option->score_weight,
                'critical_failure' => (bool) $option->is_flagged_pain_point,
            ])->values()->all(),
            'numeric_config' => $question->questionType?->type_code === 'NUMERIC' ? [
                'unit' => $question->numeric_unit,
                'min' => $question->numeric_min !== null ? (float) $question->numeric_min : null,
                'max' => $question->numeric_max !== null ? (float) $question->numeric_max : null,
                'step' => $question->numeric_step !== null ? (float) $question->numeric_step : null,
            ] : null,
            'numeric_bands' => $question->numericBands->map(fn ($band) => [
                'min_value' => $band->min_value !== null ? (float) $band->min_value : null,
                'max_value' => $band->max_value !== null ? (float) $band->max_value : null,
                'score_weight' => (float) $band->score_weight,
                'band_order' => $band->band_order,
            ])->values()->all(),
            'requires_observation' => (bool) $question->requires_observation,
            'methodology_notes' => 'Draft replacement version created from platform curation edit.',
        ]);

        return back()->with('success', 'Draft question version created.');
    }

    public function toggleActive(Question $question): RedirectResponse
    {
        $question->update(['is_active' => ! $question->is_active]);

        $status = $question->is_active ? 'enabled' : 'disabled';

        return back()->with('success', "Question {$status}.");
    }
}
