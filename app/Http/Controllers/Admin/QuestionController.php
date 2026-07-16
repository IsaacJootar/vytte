<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
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

        return back()->with('success', 'Question updated.');
    }

    public function toggleActive(Question $question): RedirectResponse
    {
        $question->update(['is_active' => ! $question->is_active]);

        $status = $question->is_active ? 'enabled' : 'disabled';

        return back()->with('success', "Question {$status}.");
    }
}
