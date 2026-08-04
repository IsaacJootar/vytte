<?php

namespace App\Http\Controllers;

use App\Services\Reporting\CustomSectionScoringService;
use App\Support\AnswerFormat;
use App\Support\LocalQuestionFormat;
use Illuminate\Contracts\View\View;

/**
 * A plain-language explanation of how Vytte scoring actually works, open to every signed-in
 * user rather than hidden in code or restricted to Platform Admin. The worked examples run
 * through the real CustomSectionScoringService so this page can never drift from the actual
 * scoring rule.
 */
class ScoringModelController extends Controller
{
    public function index(CustomSectionScoringService $scorer): View
    {
        $localFormats = LocalQuestionFormat::all();
        $contributionFormats = AnswerFormat::all();

        $yesNoExamples = collect(['YES', 'NO'])->map(function (string $value) use ($scorer) {
            $result = $scorer->score(
                [['id' => 'q', 'text' => '', 'response_type' => LocalQuestionFormat::YES_NO, 'good_answer' => 'YES']],
                ['q' => $value]
            );

            return ['label' => $value === 'YES' ? 'Yes' : 'No', 'score' => $result['questions'][0]['score']];
        });

        $scaleExamples = collect(range(1, 5))->map(function (int $value) use ($scorer) {
            $result = $scorer->score(
                [['id' => 'q', 'text' => '', 'response_type' => LocalQuestionFormat::SCALE_5, 'score_direction' => 'HIGHER_IS_BETTER']],
                ['q' => (string) $value]
            );

            return ['label' => (string) $value, 'score' => $result['questions'][0]['score']];
        });

        return view('scoring-model.index', compact('localFormats', 'contributionFormats', 'yesNoExamples', 'scaleExamples'));
    }
}
