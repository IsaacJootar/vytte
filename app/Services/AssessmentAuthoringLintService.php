<?php

namespace App\Services;

use App\Models\DepartmentFrameworkVersion;

class AssessmentAuthoringLintService
{
    /** @return list<array{severity: string, code: string, message: string, question_id?: string}> */
    public function lint(DepartmentFrameworkVersion $framework): array
    {
        $framework->loadMissing(['sections.questionPlacements.questionVersion.questionType']);
        $findings = [];

        if (blank($framework->purpose)) {
            $findings[] = $this->finding('WARNING', 'PURPOSE_MISSING', 'State the intended use so reviewers can judge whether the questions fit the decision.');
        }
        if (blank($framework->source_authority)) {
            $findings[] = $this->finding('WARNING', 'SOURCE_MISSING', 'Name the underlying source or authority.');
        }
        foreach ($framework->sections as $section) {
            if (blank($section->instructions)) {
                $findings[] = $this->finding('INFO', 'SECTION_INSTRUCTIONS_MISSING', 'Consider adding respondent instructions for “'.$section->section_name.'”.');
            }
            foreach ($section->questionPlacements as $placement) {
                $version = $placement->questionVersion;
                $text = trim((string) ($placement->local_display_text ?: $version?->question_text));
                $questionId = (string) $placement->question_id;
                if (mb_strlen($text) > 220) {
                    $findings[] = $this->finding('WARNING', 'QUESTION_LONG', 'Shorten “'.str($text)->limit(70).'” or move explanation into help text.', $questionId);
                }
                if (preg_match('/\b(and|or)\b/i', $text) && preg_match('/\?\s*$/', $text)) {
                    $findings[] = $this->finding('INFO', 'DOUBLE_BARREL_CHECK', 'Check whether “'.str($text)->limit(70).'” asks more than one thing.', $questionId);
                }
                if (preg_match('/\b(obviously|clearly|best|shouldn.t you|don.t you agree)\b/i', $text)) {
                    $findings[] = $this->finding('WARNING', 'LEADING_WORDING', 'Rewrite potentially leading wording in “'.str($text)->limit(70).'”.', $questionId);
                }
                if ($version?->questionType?->type_code === 'NUMERIC' && blank($version->numeric_config['unit'] ?? null)) {
                    $findings[] = $this->finding('WARNING', 'NUMERIC_UNIT_MISSING', 'Add a unit to the numeric question “'.str($text)->limit(70).'”.', $questionId);
                }
                $labels = collect($version?->options ?? [])->pluck('option_label')->map(fn ($label) => mb_strtolower(trim((string) $label)));
                if ($labels->duplicates()->isNotEmpty()) {
                    $findings[] = $this->finding('WARNING', 'DUPLICATE_OPTIONS', 'Remove duplicate answer choices from “'.str($text)->limit(70).'”.', $questionId);
                }
            }
        }

        if ($findings === []) {
            $findings[] = $this->finding('PASS', 'BASELINE_CLEAR', 'No deterministic wording, structure, or input-contract issues were found. Human subject and methodology review is still required.');
        }

        return $findings;
    }

    public function sourceHash(DepartmentFrameworkVersion $framework): string
    {
        return hash('sha256', json_encode(app(FrameworkContentService::class)->frameworkPayload($framework), JSON_THROW_ON_ERROR));
    }

    private function finding(string $severity, string $code, string $message, ?string $questionId = null): array
    {
        return array_filter(compact('severity', 'code', 'message', 'questionId'), fn ($value) => $value !== null);
    }
}
