<?php

namespace App\Livewire;

use App\Models\Assessment;
use App\Models\AssessmentModuleScope;
use App\Models\Question;
use App\Models\Response;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AssessmentRunner extends Component
{
    public Assessment $assessment;

    public int $currentIndex = 0;

    public array $questionData = [];

    public array $savedResponses = [];

    public string $lastSavedAt = '';

    public bool $isComplete = false;

    public function mount(Assessment $assessment): void
    {
        $this->assessment = $assessment;
        $this->isComplete = $assessment->status === 'COMPLETE';
        $this->loadQuestions();
        $this->loadExistingResponses();
    }

    private function loadQuestions(): void
    {
        $scope = AssessmentModuleScope::where('assessment_id', $this->assessment->assessment_id)
            ->where('in_scope', true)
            ->first();

        if (! $scope) {
            return;
        }

        $questions = Question::with(['options', 'moduleDomain'])
            ->where('module_id', $scope->module_id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        $this->questionData = $questions->map(fn (Question $q) => [
            'question_id' => $q->question_id,
            'question_code' => $q->question_code,
            'question_text' => $q->question_text,
            'is_scored' => $q->is_scored,
            'domain_label' => $q->moduleDomain?->domain_label ?? '',
            'domain_number' => $q->moduleDomain?->domain_number ?? 0,
            'options' => $q->options->map(fn ($o) => [
                'option_id' => $o->option_id,
                'option_label' => $o->option_label,
            ])->toArray(),
        ])->toArray();
    }

    private function loadExistingResponses(): void
    {
        $responses = Response::where('assessment_id', $this->assessment->assessment_id)
            ->whereNull('respondent_id')
            ->whereNotNull('value_option_id')
            ->get();

        foreach ($responses as $response) {
            $this->savedResponses[$response->question_id] = $response->value_option_id;
        }
    }

    public function selectOption(string $questionId, int $optionId): void
    {
        if ($this->isComplete) {
            return;
        }

        Response::updateOrCreate(
            [
                'assessment_id' => $this->assessment->assessment_id,
                'question_id' => $questionId,
                'respondent_id' => null,
            ],
            [
                'value_option_id' => $optionId,
                'answered_at' => now(),
            ]
        );

        $this->savedResponses[$questionId] = $optionId;
        $this->lastSavedAt = now()->format('g:i A');

        // Auto-advance to next question
        if ($this->currentIndex < count($this->questionData) - 1) {
            $this->currentIndex++;
        }
    }

    public function goToQuestion(int $index): void
    {
        $this->currentIndex = max(0, min($index, count($this->questionData) - 1));
    }

    public function canSubmit(): bool
    {
        foreach ($this->questionData as $q) {
            if ($q['is_scored'] && ! isset($this->savedResponses[$q['question_id']])) {
                return false;
            }
        }

        return count($this->questionData) > 0;
    }

    public function answeredCount(): int
    {
        return count(array_filter(
            $this->questionData,
            fn ($q) => isset($this->savedResponses[$q['question_id']])
        ));
    }

    public function render(): View
    {
        return view('livewire.assessment-runner');
    }
}
