<?php

namespace App\Livewire;

use App\Models\DepartmentFrameworkVersion;
use App\Services\AssessmentLogicService;
use App\Services\FrameworkContentService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AssessmentPreviewSimulator extends Component
{
    #[Locked]
    public DepartmentFrameworkVersion $assessment;

    #[Locked]
    public array $allQuestions = [];

    public array $visibleQuestions = [];

    public array $answers = [];

    public function mount(DepartmentFrameworkVersion $assessment): void
    {
        $this->assessment = $assessment;
        $payload = $assessment->status === DepartmentFrameworkVersion::STATUS_DRAFT
            ? app(FrameworkContentService::class)->frameworkPayload($assessment)
            : ($assessment->published_payload ?? app(FrameworkContentService::class)->frameworkPayload($assessment));
        $this->allQuestions = collect($payload['questions'] ?? [])
            ->sortBy('display_order')
            ->map(fn (array $question): array => [
                'question_id' => $question['question_id'],
                'question_text' => $question['question_text'],
                'response_type' => $question['response_type'],
                'display_order' => (int) $question['display_order'],
                'section_id' => $question['section_id'] ?? null,
                'section_name' => $question['section_name'] ?? null,
                'section_instructions' => $question['section_instructions'] ?? null,
                'section_estimated_minutes' => $question['section_estimated_minutes'] ?? null,
                'section_respondent_role' => $question['section_respondent_role'] ?? null,
                'numeric_config' => $question['numeric_config'] ?? null,
                'applicability' => $question['applicability'] ?? null,
                'options' => collect($question['options'] ?? [])->map(fn (array $option): array => [
                    'option_id' => (int) $option['option_id'],
                    'option_label' => $option['option_label'],
                ])->values()->all(),
            ])
            ->values()
            ->all();
        $this->refreshVisibility();
    }

    public function selectOption(string $questionId, int $optionId): void
    {
        $question = $this->question($questionId);
        if (! $question || ($question['response_type'] ?? null) === 'MULTI_SELECT'
            || ! collect($question['options'] ?? [])->contains('option_id', $optionId)) {
            return;
        }
        $this->answers[$questionId] = ['state' => 'ANSWERED', 'option_ids' => [$optionId]];
        $this->refreshVisibility();
    }

    public function toggleMultiOption(string $questionId, int $optionId): void
    {
        $question = $this->question($questionId);
        if (! $question || ($question['response_type'] ?? null) !== 'MULTI_SELECT'
            || ! collect($question['options'] ?? [])->contains('option_id', $optionId)) {
            return;
        }
        $selected = collect($this->answers[$questionId]['option_ids'] ?? [])->map(fn ($id) => (int) $id);
        $selected = $selected->contains($optionId) ? $selected->reject(fn ($id) => $id === $optionId) : $selected->push($optionId);
        $this->answers[$questionId] = ['state' => 'ANSWERED', 'option_ids' => $selected->unique()->sort()->values()->all()];
        $this->refreshVisibility();
    }

    public function setNumeric(string $questionId, mixed $value): void
    {
        $question = $this->question($questionId);
        if (! $question || ($question['response_type'] ?? null) !== 'NUMERIC' || ! is_numeric($value)) {
            return;
        }
        $this->answers[$questionId] = ['state' => 'ANSWERED', 'number' => (float) $value];
        $this->refreshVisibility();
    }

    public function setText(string $questionId, string $value): void
    {
        $question = $this->question($questionId);
        if (! $question || ($question['response_type'] ?? null) !== 'OPEN_ENDED') {
            return;
        }
        $this->answers[$questionId] = ['state' => 'ANSWERED', 'text' => trim($value)];
        $this->refreshVisibility();
    }

    public function setResponseState(string $questionId, string $state): void
    {
        if (! $this->question($questionId) || ! in_array($state, ['NOT_APPLICABLE', 'UNKNOWN', 'NOT_ASSESSED', 'NOT_OBSERVED', 'DECLINED'], true)) {
            return;
        }
        $this->answers[$questionId] = ['state' => $state];
        $this->refreshVisibility();
    }

    public function resetSimulation(): void
    {
        $this->answers = [];
        $this->refreshVisibility();
    }

    public function render(): View
    {
        return view('livewire.assessment-preview-simulator', [
            'hiddenCount' => count($this->allQuestions) - count($this->visibleQuestions),
        ]);
    }

    private function question(string $questionId): ?array
    {
        return collect($this->allQuestions)->firstWhere('question_id', $questionId);
    }

    private function refreshVisibility(): void
    {
        $facts = collect($this->allQuestions)->mapWithKeys(function (array $question): array {
            $answer = $this->answers[$question['question_id']] ?? [];

            return [$question['question_id'] => [
                'state' => $answer['state'] ?? 'ANSWERED',
                'option_ids' => $answer['option_ids'] ?? [],
                'number' => $answer['number'] ?? null,
                'text' => $answer['text'] ?? null,
                'has_answer' => ! empty($answer['option_ids'] ?? [])
                    || array_key_exists('number', $answer)
                    || filled($answer['text'] ?? null),
            ]];
        })->all();

        $this->visibleQuestions = app(AssessmentLogicService::class)->visibleQuestions($this->allQuestions, $facts);
    }
}
