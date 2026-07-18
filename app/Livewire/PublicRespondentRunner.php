<?php

namespace App\Livewire;

use App\Models\Assessment;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentRespondentToken;
use App\Models\PublicResponseSession;
use App\Models\RespondentConsent;
use App\Models\Response;
use App\Services\RespondentSubmissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PublicRespondentRunner extends Component
{
    public const CONSENT_TEXT = 'This assessment asks questions about health and community topics. Taking part is voluntary. Your answers will be kept anonymous and will not be linked to your name or identity. The information is collected only to improve health services in this area. You can stop at any time, or skip any question you do not wish to answer, without any consequence.';

    #[Locked]
    public string $token = '';

    #[Locked]
    public string $assessmentId = '';

    /** @var array<int, int> */
    #[Locked]
    public array $moduleIds = [];

    #[Locked]
    public int $moduleId = 0;

    public int $moduleCount = 0;

    public bool $tokenValid = true;

    public bool $assessmentClosed = false;

    public bool $isSubmitted = false;

    #[Locked]
    public string $respondentId = '';

    public array $availableLocales = [];

    public string $currentLocale = 'en';

    public bool $languageChosen = false;

    public bool $needsConsent = false;

    public bool $consentGiven = false;

    public array $questionData = [];

    public array $savedResponses = [];

    public array $savedTextResponses = [];

    public array $savedNumericResponses = [];

    public string $lastSavedAt = '';

    public int $currentIndex = 0;

    public function mount(string $token): void
    {
        $this->token = $token;
        $tokenRecord = AssessmentRespondentToken::where('token', $token)->first();

        if (! $tokenRecord?->isUsable()) {
            $this->tokenValid = false;

            return;
        }

        $assessment = Assessment::find($tokenRecord->assessment_id);
        if (! $assessment || ! $assessment->snapshot) {
            $this->tokenValid = false;

            return;
        }

        $this->assessmentId = $assessment->assessment_id;
        $this->assessmentClosed = $assessment->status === Assessment::STATUS_COMPLETE;
        if ($this->assessmentClosed) {
            return;
        }

        $this->moduleIds = AssessmentModuleScope::where('assessment_id', $this->assessmentId)
            ->where('in_scope', true)
            ->orderBy('module_id')
            ->pluck('module_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->moduleCount = count($this->moduleIds);
        $this->moduleId = $this->moduleIds[0] ?? 0;

        if ($this->moduleIds === []) {
            $this->tokenValid = false;

            return;
        }

        $publicSession = $this->resolveOrCreateResponseSession($tokenRecord);
        $this->respondentId = $publicSession->session_id;
        $this->isSubmitted = $publicSession->submitted_at !== null;
        if ($this->isSubmitted) {
            return;
        }

        $this->availableLocales = $this->resolveAvailableLocales();
        if (count($this->availableLocales) === 1) {
            $this->languageChosen = true;
            $this->currentLocale = 'en';
            $publicSession->update(['locale' => 'en']);
        } elseif ($publicSession->locale !== null) {
            $this->currentLocale = $publicSession->locale;
            $this->languageChosen = true;
        }

        $consentModuleIds = $this->consentModuleIds();
        $this->needsConsent = $consentModuleIds !== [];
        $this->consentGiven = ! $this->needsConsent || RespondentConsent::where(
            'public_response_session_id',
            $this->respondentId
        )->whereIn('module_id', $consentModuleIds)->distinct()->count('module_id') === count($consentModuleIds);

        if ($this->languageChosen && $this->consentGiven) {
            $this->loadQuestions();
            $this->loadExistingResponses();
        }
    }

    public function selectLocale(string $locale): void
    {
        if (! $this->hasValidPublicContext()) {
            return;
        }

        $allowed = array_column($this->availableLocales, 'code');
        $this->currentLocale = in_array($locale, $allowed, true) ? $locale : 'en';
        $this->languageChosen = true;
        $this->responseSession()?->update([
            'locale' => $this->currentLocale,
            'last_activity_at' => now(),
        ]);

        if ($this->consentGiven) {
            $this->loadQuestions();
            $this->loadExistingResponses();
        }
    }

    public function giveConsent(): void
    {
        if (! $this->hasValidPublicContext() || ! $this->needsConsent || $this->consentGiven) {
            return;
        }

        DB::transaction(function (): void {
            foreach ($this->consentModuleIds() as $moduleId) {
                RespondentConsent::firstOrCreate(
                    [
                        'assessment_id' => $this->assessmentId,
                        'module_id' => $moduleId,
                        'public_response_session_id' => $this->respondentId,
                    ],
                    [
                        'respondent_session_id' => $this->respondentId,
                        'consent_text' => self::CONSENT_TEXT,
                        'consented_by' => null,
                        'consented_at' => now(),
                    ]
                );
            }
            $this->touchResponseSession();
        });

        $this->consentGiven = true;
        if ($this->languageChosen) {
            $this->loadQuestions();
            $this->loadExistingResponses();
        }
    }

    public function selectOption(string $questionId, int $optionId): void
    {
        if (! $this->hasValidPublicContext() || ! $this->consentGiven) {
            return;
        }

        $question = $this->authoritativeQuestion($questionId);
        if (! $question) {
            return;
        }
        $validOptionIds = collect($question['options'] ?? [])->pluck('option_id')->map(fn ($id) => (int) $id);
        if (! $validOptionIds->contains($optionId)) {
            return;
        }

        Response::updateOrCreate(
            [
                'assessment_id' => $this->assessmentId,
                'question_id' => $questionId,
                'respondent_id' => $this->respondentId,
            ],
            [
                'public_response_session_id' => $this->respondentId,
                'value_option_id' => $optionId,
                'value_text' => null,
                'value_numeric' => null,
                'answered_at' => now(),
            ]
        );

        $this->savedResponses[$questionId] = $optionId;
        unset($this->savedTextResponses[$questionId], $this->savedNumericResponses[$questionId]);
        $this->markSaved();
        if ($this->currentIndex < count($this->questionData) - 1) {
            $this->currentIndex++;
        }
    }

    public function goToQuestion(int $index): void
    {
        if ($this->hasValidPublicContext()) {
            $this->currentIndex = max(0, min($index, count($this->questionData) - 1));
        }
    }

    public function saveText(string $questionId, string $value): void
    {
        if (! $this->hasValidPublicContext() || ! $this->consentGiven) {
            return;
        }

        $question = $this->authoritativeQuestion($questionId);
        if (! $question || $question['response_type'] !== 'OPEN_ENDED') {
            return;
        }

        $value = trim($value);
        if ($value === '') {
            Response::where('assessment_id', $this->assessmentId)
                ->where('question_id', $questionId)
                ->where('public_response_session_id', $this->respondentId)
                ->delete();
            unset($this->savedTextResponses[$questionId]);

            return;
        }

        $value = mb_substr($value, 0, 5000);
        Response::updateOrCreate(
            [
                'assessment_id' => $this->assessmentId,
                'question_id' => $questionId,
                'respondent_id' => $this->respondentId,
            ],
            [
                'public_response_session_id' => $this->respondentId,
                'value_text' => $value,
                'value_numeric' => null,
                'value_option_id' => null,
                'answered_at' => now(),
            ]
        );
        $this->savedTextResponses[$questionId] = $value;
        unset($this->savedResponses[$questionId], $this->savedNumericResponses[$questionId]);
        $this->markSaved();
    }

    public function saveNumeric(string $questionId, mixed $value): void
    {
        if (! $this->hasValidPublicContext() || ! $this->consentGiven) {
            return;
        }

        $question = $this->authoritativeQuestion($questionId);
        if (! $question || $question['response_type'] !== 'NUMERIC') {
            return;
        }

        if ($value === '' || $value === null) {
            Response::where('assessment_id', $this->assessmentId)
                ->where('question_id', $questionId)
                ->where('public_response_session_id', $this->respondentId)
                ->delete();
            unset($this->savedNumericResponses[$questionId]);

            return;
        }

        if (! is_numeric($value) || ! is_finite((float) $value) || abs((float) $value) > 99999999999.9999) {
            $this->addError("numeric.{$questionId}", 'Enter a valid number.');

            return;
        }

        $number = (float) $value;
        $config = $question['numeric_config'] ?? [];
        if (($config['min'] ?? null) !== null && $number < (float) $config['min']) {
            $this->addError("numeric.{$questionId}", 'The value is below the allowed minimum.');

            return;
        }
        if (($config['max'] ?? null) !== null && $number > (float) $config['max']) {
            $this->addError("numeric.{$questionId}", 'The value is above the allowed maximum.');

            return;
        }
        $step = $config['step'] ?? null;
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
                'assessment_id' => $this->assessmentId,
                'question_id' => $questionId,
                'respondent_id' => $this->respondentId,
            ],
            [
                'public_response_session_id' => $this->respondentId,
                'value_numeric' => $number,
                'value_text' => null,
                'value_option_id' => null,
                'answered_at' => now(),
            ]
        );
        $this->savedNumericResponses[$questionId] = $number;
        unset($this->savedResponses[$questionId], $this->savedTextResponses[$questionId]);
        $this->markSaved();
    }

    public function canSubmit(): bool
    {
        foreach ($this->questionData as $question) {
            $answered = isset($this->savedResponses[$question['question_id']])
                || filled($this->savedTextResponses[$question['question_id']] ?? null)
                || array_key_exists($question['question_id'], $this->savedNumericResponses);
            if ($question['is_scored'] && ! $answered) {
                return false;
            }
        }

        return $this->questionData !== [];
    }

    public function answeredCount(): int
    {
        return count(array_filter(
            $this->questionData,
            fn ($question) => isset($this->savedResponses[$question['question_id']])
                || filled($this->savedTextResponses[$question['question_id']] ?? null)
                || array_key_exists($question['question_id'], $this->savedNumericResponses)
        ));
    }

    public function submit(RespondentSubmissionService $submission): void
    {
        if (! $this->hasValidPublicContext() || ! $this->hasCompleteRequiredResponses()) {
            return;
        }

        $session = PublicResponseSession::where('session_id', $this->respondentId)
            ->whereNull('submitted_at')
            ->first();
        if (! $session) {
            return;
        }

        $submission->submit($session);
        $this->isSubmitted = true;
    }

    private function loadQuestions(): void
    {
        $this->questionData = $this->questionDefinitions($this->currentLocale);
    }

    private function questionDefinitions(string $locale): array
    {
        $assessment = Assessment::with('snapshot')->find($this->assessmentId);
        if (! $assessment?->snapshot) {
            return [];
        }

        return collect($assessment->snapshot->payload)
            ->filter(fn ($module) => in_array((int) $module['module_id'], $this->moduleIds, true))
            ->sortBy('display_order')
            ->flatMap(fn ($module) => collect($module['questions'] ?? [])
                ->sortBy('display_order')
                ->map(fn ($question) => [
                    'question_id' => $question['question_id'],
                    'question_code' => $question['question_code'],
                    'question_text' => Arr::get($question, "translations.{$locale}", $question['question_text']),
                    'is_scored' => (bool) $question['is_scored'],
                    'response_type' => $question['response_type'],
                    'module_id' => (int) $module['module_id'],
                    'module_code' => $module['module_code'],
                    'module_name' => $module['module_name'] ?? $module['module_code'],
                    'section_label' => $question['section_label'] ?? '',
                    'section_number' => $question['section_number'] ?? 0,
                    'numeric_config' => $question['numeric_config'] ?? null,
                    'options' => collect($question['options'] ?? [])->map(fn ($option) => [
                        'option_id' => (int) $option['option_id'],
                        'option_label' => Arr::get($option, "translations.{$locale}", $option['option_label']),
                    ])->all(),
                ]))
            ->values()
            ->all();
    }

    private function authoritativeQuestion(string $questionId): ?array
    {
        return collect($this->questionDefinitions('en'))->firstWhere('question_id', $questionId);
    }

    private function loadExistingResponses(): void
    {
        $this->savedResponses = [];
        $this->savedTextResponses = [];
        $this->savedNumericResponses = [];
        $responses = Response::where('public_response_session_id', $this->respondentId)->get();
        foreach ($responses as $response) {
            if ($response->value_option_id !== null) {
                $this->savedResponses[$response->question_id] = $response->value_option_id;
            }
            if ($response->value_text !== null) {
                $this->savedTextResponses[$response->question_id] = $response->value_text;
            }
            if ($response->value_numeric !== null) {
                $this->savedNumericResponses[$response->question_id] = (float) $response->value_numeric;
            }
        }
    }

    private function resolveAvailableLocales(): array
    {
        $assessment = Assessment::with('snapshot')->find($this->assessmentId);
        if (! $assessment?->snapshot) {
            return [['code' => 'en', 'label' => 'English']];
        }

        $translatedLocales = collect($assessment->snapshot->payload)
            ->flatMap(fn ($module) => collect($module['questions'] ?? [])->flatMap(function ($question) {
                return array_merge(
                    array_keys($question['translations'] ?? []),
                    collect($question['options'] ?? [])->flatMap(fn ($option) => array_keys($option['translations'] ?? []))->all()
                );
            }))->unique()->values()->all();

        $labels = ['fr' => 'Français'];
        $locales = [['code' => 'en', 'label' => 'English']];
        foreach ($translatedLocales as $locale) {
            if (isset($labels[$locale])) {
                $locales[] = ['code' => $locale, 'label' => $labels[$locale]];
            }
        }

        return $locales;
    }

    private function resolveOrCreateResponseSession(AssessmentRespondentToken $tokenRecord): PublicResponseSession
    {
        $sessionKey = "respondent.{$this->token}.session_id";
        $sessionId = session($sessionKey);
        $publicSession = $sessionId ? PublicResponseSession::where('session_id', $sessionId)
            ->where('token', $this->token)
            ->where('assessment_id', $this->assessmentId)
            ->first() : null;

        if ($publicSession) {
            return $publicSession;
        }

        return DB::transaction(function () use ($sessionKey, $tokenRecord): PublicResponseSession {
            $publicSession = PublicResponseSession::create([
                'session_id' => (string) Str::uuid(),
                'token' => $this->token,
                'assessment_id' => $this->assessmentId,
                'started_at' => now(),
                'last_activity_at' => now(),
            ]);
            $tokenRecord->increment('use_count');
            $tokenRecord->update(['last_used_at' => now()]);
            session()->put($sessionKey, $publicSession->session_id);

            return $publicSession;
        });
    }

    private function consentModuleIds(): array
    {
        $snapshot = Assessment::with('snapshot')->find($this->assessmentId)?->snapshot;
        if (! $snapshot || ! collect($snapshot->payload)->every(fn ($module) => array_key_exists('requires_consent', $module))) {
            return [];
        }

        return collect($snapshot->payload)
            ->where('requires_consent', true)
            ->pluck('module_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function hasCompleteRequiredResponses(): bool
    {
        $required = collect($this->questionDefinitions('en'))->where('is_scored', true);
        if ($required->isEmpty()) {
            return false;
        }

        $responses = Response::where('public_response_session_id', $this->respondentId)
            ->whereIn('question_id', $required->pluck('question_id'))
            ->get()->keyBy('question_id');

        return $required->every(function ($question) use ($responses): bool {
            $response = $responses->get($question['question_id']);
            if (! $response) {
                return false;
            }

            return match ($question['response_type']) {
                'OPEN_ENDED' => filled($response->value_text),
                'NUMERIC' => $response->value_numeric !== null,
                default => $response->value_option_id !== null,
            };
        });
    }

    private function responseSession(): ?PublicResponseSession
    {
        return $this->respondentId === '' ? null : PublicResponseSession::find($this->respondentId);
    }

    private function touchResponseSession(): void
    {
        $this->responseSession()?->update(['last_activity_at' => now()]);
    }

    private function markSaved(): void
    {
        $this->lastSavedAt = now()->format('g:i A');
        $this->touchResponseSession();
    }

    private function hasValidPublicContext(): bool
    {
        if ($this->token === '' || $this->assessmentId === '' || $this->respondentId === '' || $this->isSubmitted) {
            return false;
        }

        $tokenRecord = AssessmentRespondentToken::where('token', $this->token)
            ->where('assessment_id', $this->assessmentId)->first();
        $publicSession = PublicResponseSession::where('session_id', $this->respondentId)
            ->where('token', $this->token)
            ->where('assessment_id', $this->assessmentId)
            ->whereNull('submitted_at')->exists();
        $assessmentOpen = Assessment::where('assessment_id', $this->assessmentId)
            ->where('status', Assessment::STATUS_IN_PROGRESS)->exists();

        return $tokenRecord?->isUsable() === true && $publicSession && $assessmentOpen;
    }

    public function render(): View
    {
        return view('livewire.public-respondent-runner');
    }
}
