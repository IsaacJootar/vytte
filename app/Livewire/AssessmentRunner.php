<?php

namespace App\Livewire;

use App\Models\Assessment;
use App\Models\RespondentConsent;
use App\Models\Response;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AssessmentRunner extends Component
{
    public const CONSENT_TEXT = 'This assessment asks questions about health and community topics. Taking part is voluntary. Your answers will be kept anonymous and will not be linked to your name or identity. The information is collected only to improve health services in this area. You can stop at any time, or skip any question you do not wish to answer, without any consequence.';

    #[Locked]
    public Assessment $assessment;

    public int $currentIndex = 0;

    public array $questionData = [];

    public array $savedResponses = [];

    public array $savedTextResponses = [];

    public array $savedNumericResponses = [];

    public array $savedEvidenceNotes = [];

    public string $lastSavedAt = '';

    public bool $isComplete = false;

    public bool $needsConsent = false;

    public bool $consentGiven = false;

    public ?int $consentModuleId = null;

    public int $moduleCount = 1;

    public function mount(Assessment $assessment): void
    {
        $this->assessment = $assessment;
        $this->authorizeAssessmentAccess();
        abort_if(
            $assessment->snapshot?->collection_config['allows_multi_respondent'] ?? false,
            404,
        );
        $this->isComplete = $assessment->status === Assessment::STATUS_COMPLETE;
        $this->loadQuestions();
        $this->loadExistingResponses();
        $this->checkConsentRequired();
    }

    private function loadQuestions(): void
    {
        $snapshot = $this->assessment->snapshot()->first();
        if (! $snapshot) {
            return;
        }

        $locale = App::getLocale();
        $this->moduleCount = count($snapshot->payload);
        $this->questionData = collect($snapshot->payload)
            ->sortBy('display_order')
            ->flatMap(fn ($module) => collect($module['questions'])
                ->sortBy('display_order')
                ->map(fn ($question) => [
                    'question_id' => $question['question_id'],
                    'question_code' => $question['question_code'],
                    'question_text' => $question['translations'][$locale] ?? $question['question_text'],
                    'is_scored' => $question['is_scored'],
                    'response_type' => $question['response_type'],
                    'module_id' => $module['module_id'],
                    'module_code' => $module['module_code'],
                    'domain_label' => $question['domain_label'] ?? '',
                    'domain_number' => $question['domain_number'] ?? 0,
                    'numeric_config' => $question['numeric_config'] ?? null,
                    'options' => collect($question['options'])->map(fn ($option) => [
                        'option_id' => $option['option_id'],
                        'option_label' => $option['translations'][$locale] ?? $option['option_label'],
                    ])->all(),
                ]))
            ->values()
            ->all();
    }

    private function loadExistingResponses(): void
    {
        $responses = Response::where('assessment_id', $this->assessment->assessment_id)
            ->whereNull('respondent_id')
            ->whereNull('public_response_session_id')
            ->get();

        foreach ($responses as $response) {
            $this->savedResponses[$response->question_id] = $response->value_option_id;
            if ($response->value_text !== null) {
                $this->savedTextResponses[$response->question_id] = $response->value_text;
            }
            if ($response->value_numeric !== null) {
                $this->savedNumericResponses[$response->question_id] = (float) $response->value_numeric;
            }
            if ($response->evidence_note !== null) {
                $this->savedEvidenceNotes[$response->question_id] = $response->evidence_note;
            }
        }
    }

    private function checkConsentRequired(): void
    {
        $snapshot = $this->assessment->snapshot()->first();
        if (! $snapshot || ! collect($snapshot->payload)->every(fn ($module) => array_key_exists('requires_consent', $module))) {
            return;
        }

        $consentModule = collect($snapshot->payload)->firstWhere('requires_consent', true);
        $this->needsConsent = $consentModule !== null;
        $this->consentModuleId = $consentModule ? (int) $consentModule['module_id'] : null;

        if ($this->needsConsent) {
            $this->consentGiven = RespondentConsent::where('assessment_id', $this->assessment->assessment_id)
                ->where('consented_by', auth()->id())
                ->exists();
        }
    }

    public function giveConsent(): void
    {
        $this->authorizeAssessmentAccess();

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
        $this->authorizeAssessmentAccess();

        if ($this->isComplete) {
            return;
        }

        $snapshotQuestion = $this->snapshotQuestion($questionId);
        $validSelection = $snapshotQuestion
            && collect($snapshotQuestion['options'] ?? [])->contains('option_id', $optionId);

        if (! $validSelection) {
            return;
        }

        if ($this->needsConsent) {
            $hasConsent = RespondentConsent::where('assessment_id', $this->assessment->assessment_id)
                ->where('consented_by', auth()->id())
                ->exists();
            if (! $hasConsent) {
                return;
            }
        }

        Response::updateOrCreate(
            [
                'assessment_id' => $this->assessment->assessment_id,
                'question_id' => $questionId,
                'respondent_id' => null,
                'public_response_session_id' => null,
            ],
            [
                'value_option_id' => $optionId,
                'value_text' => null,
                'value_numeric' => null,
                'answered_at' => now(),
            ]
        );

        $this->savedResponses[$questionId] = $optionId;
        unset($this->savedTextResponses[$questionId], $this->savedNumericResponses[$questionId]);
        $this->lastSavedAt = now()->format('g:i A');

        // Auto-advance to next question
        if ($this->currentIndex < count($this->questionData) - 1) {
            $this->currentIndex++;
        }
    }

    public function goToQuestion(int $index): void
    {
        $this->authorizeAssessmentAccess();
        $this->currentIndex = max(0, min($index, count($this->questionData) - 1));
    }

    public function saveText(string $questionId, string $value): void
    {
        $this->authorizeAssessmentAccess();

        if ($this->isComplete) {
            return;
        }

        $snapshotQuestion = $this->snapshotQuestion($questionId);
        $validQuestion = $snapshotQuestion
            && ($snapshotQuestion['response_type'] ?? null) === 'OPEN_ENDED';

        if (! $validQuestion) {
            return;
        }

        if (! $this->hasRequiredConsent()) {
            return;
        }

        $value = trim($value);
        if ($value === '') {
            $response = Response::where('assessment_id', $this->assessment->assessment_id)
                ->where('question_id', $questionId)
                ->whereNull('respondent_id')
                ->whereNull('public_response_session_id')
                ->first();
            if ($response) {
                if (blank($response->evidence_note)) {
                    $response->delete();
                } else {
                    $response->update(['value_text' => null]);
                }
            }
            unset($this->savedTextResponses[$questionId]);

            return;
        }

        $value = mb_substr($value, 0, 5000);
        Response::updateOrCreate(
            [
                'assessment_id' => $this->assessment->assessment_id,
                'question_id' => $questionId,
                'respondent_id' => null,
                'public_response_session_id' => null,
            ],
            ['value_text' => $value, 'value_numeric' => null, 'value_option_id' => null, 'answered_at' => now()]
        );
        $this->savedTextResponses[$questionId] = $value;
        unset($this->savedResponses[$questionId], $this->savedNumericResponses[$questionId]);
        $this->lastSavedAt = now()->format('g:i A');
    }

    public function saveNumeric(string $questionId, mixed $value): void
    {
        $this->authorizeAssessmentAccess();

        if ($this->isComplete || ! $this->hasRequiredConsent()) {
            return;
        }

        $snapshotQuestion = $this->snapshotQuestion($questionId);
        $isNumeric = $snapshotQuestion
            && ($snapshotQuestion['response_type'] ?? null) === 'NUMERIC';
        if (! $isNumeric) {
            return;
        }

        if ($value === '' || $value === null) {
            $response = Response::where('assessment_id', $this->assessment->assessment_id)
                ->where('question_id', $questionId)
                ->whereNull('respondent_id')
                ->whereNull('public_response_session_id')
                ->first();
            if ($response) {
                if (blank($response->evidence_note)) {
                    $response->delete();
                } else {
                    $response->update(['value_numeric' => null]);
                }
            }
            unset($this->savedNumericResponses[$questionId]);

            return;
        }

        if (! is_numeric($value) || ! is_finite((float) $value) || abs((float) $value) > 99999999999.9999) {
            $this->addError("numeric.{$questionId}", 'Enter a valid number.');

            return;
        }

        $number = (float) $value;
        $config = $snapshotQuestion['numeric_config'] ?? [];
        if (($config['min'] ?? null) !== null && $number < (float) $config['min']) {
            $this->addError("numeric.{$questionId}", 'The value is below the allowed minimum.');

            return;
        }
        if (($config['max'] ?? null) !== null && $number > (float) $config['max']) {
            $this->addError("numeric.{$questionId}", 'The value is above the allowed maximum.');

            return;
        }
        $step = $snapshotQuestion['numeric_config']['step'] ?? null;
        $base = ($config['min'] ?? null) !== null ? (float) $config['min'] : 0.0;
        if ($step !== null && (float) $step > 0) {
            $steps = ($number - $base) / (float) $step;
            if (abs($steps - round($steps)) > 0.000001) {
                $this->addError("numeric.{$questionId}", 'Enter a value using the allowed increment.');

                return;
            }
        }

        $this->resetErrorBag("numeric.{$questionId}");
        Response::updateOrCreate(
            [
                'assessment_id' => $this->assessment->assessment_id,
                'question_id' => $questionId,
                'respondent_id' => null,
                'public_response_session_id' => null,
            ],
            ['value_numeric' => $number, 'value_text' => null, 'value_option_id' => null, 'answered_at' => now()]
        );
        $this->savedNumericResponses[$questionId] = $number;
        unset($this->savedResponses[$questionId], $this->savedTextResponses[$questionId]);
        $this->lastSavedAt = now()->format('g:i A');
    }

    public function saveEvidenceNote(string $questionId, string $value): void
    {
        $this->authorizeAssessmentAccess();

        if ($this->isComplete) {
            return;
        }

        $validQuestion = $this->snapshotQuestion($questionId) !== null
            && $this->hasRequiredConsent();

        if (! $validQuestion) {
            return;
        }

        $value = trim($value);
        $response = Response::where('assessment_id', $this->assessment->assessment_id)
            ->where('question_id', $questionId)
            ->whereNull('respondent_id')
            ->whereNull('public_response_session_id')
            ->first();

        if ($value === '') {
            if ($response) {
                if ($response->value_option_id === null && blank($response->value_text) && $response->value_numeric === null) {
                    $response->delete();
                } else {
                    $response->update(['evidence_note' => null]);
                }
            }
            unset($this->savedEvidenceNotes[$questionId]);

            return;
        }

        $value = mb_substr($value, 0, 5000);
        Response::updateOrCreate(
            [
                'assessment_id' => $this->assessment->assessment_id,
                'question_id' => $questionId,
                'respondent_id' => null,
                'public_response_session_id' => null,
            ],
            ['evidence_note' => $value]
        );
        $this->savedEvidenceNotes[$questionId] = $value;
        $this->lastSavedAt = now()->format('g:i A');
    }

    public function canSubmit(): bool
    {
        foreach ($this->questionData as $q) {
            $answered = isset($this->savedResponses[$q['question_id']])
                || filled($this->savedTextResponses[$q['question_id']] ?? null)
                || array_key_exists($q['question_id'], $this->savedNumericResponses);
            if ($q['is_scored'] && ! $answered) {
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
                || filled($this->savedTextResponses[$q['question_id']] ?? null)
                || array_key_exists($q['question_id'], $this->savedNumericResponses)
        ));
    }

    public function render(): View
    {
        return view('livewire.assessment-runner');
    }

    private function authorizeAssessmentAccess(): void
    {
        abort_unless(auth()->check() && app()->bound('current.workspace'), 403);

        $workspaceId = app('current.workspace')->workspace_id;
        $projectBelongsToWorkspace = $this->assessment->project()
            ->withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->exists();

        abort_unless($projectBelongsToWorkspace, 404);
    }

    private function snapshotQuestion(string $questionId): ?array
    {
        $snapshot = $this->assessment->snapshot()->first();
        if (! $snapshot) {
            return null;
        }

        return collect($snapshot->payload)
            ->flatMap(fn ($module) => $module['questions'] ?? [])
            ->firstWhere('question_id', $questionId);
    }

    private function hasRequiredConsent(): bool
    {
        return ! $this->needsConsent || RespondentConsent::where('assessment_id', $this->assessment->assessment_id)
            ->where('consented_by', auth()->id())
            ->exists();
    }
}
