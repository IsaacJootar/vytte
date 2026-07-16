<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentModule;
use App\Models\Question;
use App\Models\QuestionOptionTranslation;
use App\Models\QuestionTranslation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModuleTranslationController extends Controller
{
    private const SUPPORTED_LOCALES = ['fr'];

    public function edit(AssessmentModule $module, string $locale = 'fr'): View
    {
        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = 'fr';
        }

        $questions = Question::with(['options'])
            ->where('module_id', $module->module_id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        $questionIds = $questions->pluck('question_id');
        $optionIds = $questions->flatMap(fn ($q) => $q->options->pluck('option_id'));

        $questionTranslations = QuestionTranslation::where('locale', $locale)
            ->whereIn('question_id', $questionIds)
            ->pluck('question_text', 'question_id');

        $optionTranslations = QuestionOptionTranslation::where('locale', $locale)
            ->whereIn('option_id', $optionIds)
            ->pluck('option_label', 'option_id');

        return view('admin.modules.translations', compact(
            'module', 'locale', 'questions', 'questionTranslations', 'optionTranslations'
        ));
    }

    public function update(Request $request, AssessmentModule $module, string $locale = 'fr'): RedirectResponse
    {
        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            abort(422);
        }

        DB::transaction(function () use ($request, $locale) {
            $questionTexts = $request->input('questions', []);
            foreach ($questionTexts as $questionId => $text) {
                $text = trim($text);
                if ($text === '') {
                    QuestionTranslation::where('question_id', $questionId)->where('locale', $locale)->delete();
                } else {
                    QuestionTranslation::updateOrCreate(
                        ['question_id' => $questionId, 'locale' => $locale],
                        ['question_text' => $text]
                    );
                }
            }

            $optionLabels = $request->input('options', []);
            foreach ($optionLabels as $optionId => $label) {
                $label = trim($label);
                if ($label === '') {
                    QuestionOptionTranslation::where('option_id', $optionId)->where('locale', $locale)->delete();
                } else {
                    QuestionOptionTranslation::updateOrCreate(
                        ['option_id' => $optionId, 'locale' => $locale],
                        ['option_label' => $label]
                    );
                }
            }
        });

        return redirect()->route('admin.modules.translations.edit', [$module, $locale])
            ->with('success', 'Translations saved.');
    }
}
