<?php

namespace App\Livewire;

use App\Models\Assessment;
use App\Models\AssessmentModule;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentRespondentToken;
use App\Models\PublicResponseSession;
use App\Models\Question;
use App\Models\QuestionOptionTranslation;
use App\Models\QuestionTranslation;
use App\Models\RespondentConsent;
use App\Models\Response;
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

    /** Retained for view and backwards compatibility; all modules are now loaded. */
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
        if (! $assessment) {
            $this->tokenValid = false;

            return;
        }

        $this->assessmentId = $assessment->assessment_id;
        $this->assessmentClosed = $assessment->status === 'COMPLETE';
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
        $validOptionIds = collect($question['options'] ?? [])->pluck('option_id')->map(fn ($id) => (int) $id);
        if (! $question || ! $validOptionIds->contains($optionId)) {
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
                'answered_at' => now(),
            ]
        );

        $this->savedResponses[$questionId] = $optionId;
        unset($this->savedTextResponses[$questionId]);
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
                'value_option_id' => null,
                'answered_at' => now(),
            ]
        );
        $this->savedTextResponses[$questionId] = $value;
        unset($this->savedResponses[$questionId]);
        $this->markSaved();
    }

    public function canSubmit(): bool
    {
        foreach ($this->questionData as $question) {
            $answered = isset($this->savedResponses[$question['question_id']])
                || filled($this->savedTextResponses[$question['question_id']] ?? null);
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
        ));
    }

    public function submit(): void
    {
        if (! $this->hasValidPublicContext() || ! $this->hasCompleteRequiredResponses()) {
            return;
        }

        $updated = PublicResponseSession::where('session_id', $this->respondentId)
            ->whereNull('submitted_at')
            ->update(['submitted_at' => now(), 'last_activity_at' => now()]);

        if ($updated === 1) {
            $this->isSubmitted = true;
        }
    }

    private function loadQuestions(): void
    {
        $this->questionData = $this->questionDefinitions($this->currentLocale);
    }

    private function questionDefinitions(string $locale): array
    {
        $assessment = Assessment::with('snapshot')->find($this->assessmentId);
        if ($assessment?->snapshot) {
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
                        'domain_label' => $question['domain_label'] ?? '',
                        'domain_number' => $question['domain_number'] ?? 0,
                        'options' => collect($question['options'] ?? [])->map(fn ($option) => [
                            'option_id' => (int) $option['option_id'],
                            'option_label' => Arr::get($option, "translations.{$locale}", $option['option_label']),
                        ])->all(),
                    ]))
                ->values()
                ->all();
        }

        $moduleNames = AssessmentModule::whereIn('module_id', $this->moduleIds)
            ->get()->keyBy('module_id');
        $questions = Question::with(['options', 'moduleDomain', 'questionType'])
            ->whereIn('module_id', $this->moduleIds)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (Question $question) => sprintf(
                '%05d-%05d-%05d',
                array_search($question->module_id, $this->moduleIds, true),
                $question->moduleDomain?->domain_number ?? 0,
                $question->display_order
            ))
            ->values();

        $questionTranslations = collect();
        $optionTranslations = collect();
        if ($locale !== 'en' && $questions->isNotEmpty()) {
            $questionTranslations = QuestionTranslation::where('locale', $locale)
                ->whereIn('question_id', $questions->pluck('question_id'))
                ->pluck('question_text', 'question_id');
            $optionTranslations = QuestionOptionTranslation::where('locale', $locale)
                ->whereIn('option_id', $questions->flatMap(fn ($question) => $question->options->pluck('option_id')))
                ->pluck('option_label', 'option_id');
        }

        return $questions->map(fn (Question $question) => [
            'question_id' => $question->question_id,
            'question_code' => $question->question_code,
            'question_text' => $questionTranslations->get($question->question_id, $question->question_text),
            'is_scored' => (bool) $question->is_scored,
            'response_type' => $question->questionType?->type_code,
            'module_id' => (int) $question->module_id,
            'module_code' => $moduleNames[$question->module_id]?->module_code ?? '',
            'module_name' => $moduleNames[$question->module_id]?->module_name ?? '',
            'domain_label' => $question->moduleDomain?->domain_label ?? '',
            'domain_number' => $question->moduleDomain?->domain_number ?? 0,
            'options' => $question->options->map(fn ($option) => [
                'option_id' => (int) $option->option_id,
                'option_label' => $optionTranslations->get($option->option_id, $option->option_label),
            ])->all(),
        ])->all();
    }

    private function authoritativeQuestion(string $questionId): ?array
    {
        return collect($this->questionDefinitions('en'))->firstWhere('question_id', $questionId);
    }

    private function loadExistingResponses(): void
    {
        $this->savedResponses = [];
        $this->savedTextResponses = [];
        $responses = Response::where('public_response_session_id', $this->respondentId)->get();
        foreach ($responses as $response) {
            if ($response->value_option_id !== null) {
                $this->savedResponses[$response->question_id] = $response->value_option_id;
            }
            if ($response->value_text !== null) {
                $this->savedTextResponses[$response->question_id] = $response->value_text;
            }
        }
    }

    private function resolveAvailableLocales(): array
    {
        $assessment = Assessment::with('snapshot')->find($this->assessmentId);
        if ($assessment?->snapshot) {
            $translatedLocales = collect($assessment->snapshot->payload)
                ->flatMap(fn ($module) => collect($module['questions'] ?? [])->flatMap(function ($question) {
                    return array_merge(
                        array_keys($question['translations'] ?? []),
                        collect($question['options'] ?? [])->flatMap(fn ($option) => array_keys($option['translations'] ?? []))->all()
                    );
                }))->unique()->values()->all();
        } else {
            $questionIds = Question::whereIn('module_id', $this->moduleIds)->where('is_active', true)->pluck('question_id');
            $translatedLocales = QuestionTranslation::whereIn('question_id', $questionIds)
                ->distinct()->pluck('locale')->all();
        }

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
        if ($snapshot && collect($snapshot->payload)->every(fn ($module) => array_key_exists('requires_consent', $module))) {
            return collect($snapshot->payload)
                ->where('requires_consent', true)
                ->pluck('module_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return AssessmentModule::whereIn('module_id', $this->moduleIds)
            ->where('requires_consent', true)
            ->pluck('module_id')->map(fn ($id) => (int) $id)->all();
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

            return $question['response_type'] === 'OPEN_ENDED'
                ? filled($response->value_text)
                : $response->value_option_id !== null;
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
            ->where('status', 'IN_PROGRESS')->exists();

        return $tokenRecord?->isUsable() === true && $publicSession && $assessmentOpen;
    }

    public function render(): View
    {
        return view('livewire.public-respondent-runner');
    }
}
