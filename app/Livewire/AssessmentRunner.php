<?php

namespace App\Livewire;

use App\Models\Assessment;
use App\Models\AssessmentModule;
use App\Models\AssessmentModuleScope;
use App\Models\Question;
use App\Models\RespondentConsent;
use App\Models\Response;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AssessmentRunner extends Component
{
    public const CONSENT_TEXT = 'This assessment asks questions about health and community topics. Taking part is voluntary. Your answers will be kept anonymous and will not be linked to your name or identity. The information is collected only to improve health services in this area. You can stop at any time, or skip any question you do not wish to answer, without any consequence.';

    public Assessment $assessment;

    public int $currentIndex = 0;

    public array $questionData = [];

    public array $savedResponses = [];

    public string $lastSavedAt = '';

    public bool $isComplete = false;

    public bool $needsConsent = false;

    public bool $consentGiven = false;

    public ?int $consentModuleId = null;

    public function mount(Assessment $assessment): void
    {
        $this->assessment = $assessment;
        $this->isComplete = $assessment->status === 'COMPLETE';
        $this->loadQuestions();
        $this->loadExistingResponses();
        $this->checkConsentRequired();
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

    private function checkConsentRequired(): void
    {
        $scope = AssessmentModuleScope::where('assessment_id', $this->assessment->assessment_id)
            ->where('in_scope', true)
            ->first();

        if (! $scope) {
            return;
        }

        $module = AssessmentModule::find($scope->module_id);
        $this->needsConsent = $module?->requires_consent ?? false;
        $this->consentModuleId = $scope->module_id;

        if ($this->needsConsent) {
            $this->consentGiven = RespondentConsent::where('assessment_id', $this->assessment->assessment_id)->exists();
        }
    }

    public function giveConsent(): void
    {
        if (! $this->needsConsent || $this->consentGiven || $this->isComplete) {
            return;
        }

        RespondentConsent::create([
            'assessment_id' => $this->assessment->assessment_id,
            'module_id' => $this->consentModuleId,
            'consent_text' => self::CONSENT_TEXT,
            'consented_by' => auth()->user()->user_id,
            'consented_at' => now(),
        ]);

        $this->consentGiven = true;
    }

    public function selectOption(string $questionId, int $optionId): void
    {
        if ($this->isComplete) {
            return;
        }

        if ($this->needsConsent && ! $this->consentGiven) {
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
