<?php

namespace App\Livewire;

use App\Models\Assessment;
use App\Models\AssessmentModule;
use App\Models\AssessmentModuleScope;
use App\Models\AssessmentRespondentToken;
use App\Models\Question;
use App\Models\QuestionOptionTranslation;
use App\Models\QuestionTranslation;
use App\Models\RespondentConsent;
use App\Models\Response;
use Illuminate\Contracts\View\View;
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

    #[Locked]
    public int $moduleId = 0;

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

    public string $lastSavedAt = '';

    public int $currentIndex = 0;

    public function mount(string $token): void
    {
        $this->token = $token;

        $tokenRecord = AssessmentRespondentToken::where('token', $token)->first();

        if (! $tokenRecord || ($tokenRecord->expires_at && $tokenRecord->expires_at->isPast())) {
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

        $sessionBase = "respondent.{$token}";
        $this->respondentId = session("{$sessionBase}.respondent_id") ?? (string) Str::uuid();
        session()->put("{$sessionBase}.respondent_id", $this->respondentId);

        $this->isSubmitted = (bool) session("{$sessionBase}.submitted", false);

        if ($this->isSubmitted || $this->assessmentClosed) {
            return;
        }

        $scope = AssessmentModuleScope::where('assessment_id', $this->assessmentId)
            ->where('in_scope', true)
            ->first();

        if (! $scope) {
            $this->tokenValid = false;

            return;
        }

        $this->moduleId = $scope->module_id;
        $this->availableLocales = $this->resolveAvailableLocales($this->moduleId);

        if (count($this->availableLocales) === 1) {
            $this->languageChosen = true;
            $this->currentLocale = 'en';
        } else {
            $this->currentLocale = session("{$sessionBase}.locale", 'en');
            $this->languageChosen = session()->has("{$sessionBase}.locale");
        }

        $module = AssessmentModule::find($this->moduleId);
        $this->needsConsent = $module?->requires_consent ?? false;

        if ($this->needsConsent) {
            $this->consentGiven = RespondentConsent::where('assessment_id', $this->assessmentId)
                ->where('respondent_session_id', $this->respondentId)
                ->exists();
        }

        if ($this->languageChosen && (! $this->needsConsent || $this->consentGiven)) {
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

        if (! in_array($locale, $allowed, true)) {
            $locale = 'en';
        }

        $this->currentLocale = $locale;
        $this->languageChosen = true;
        session()->put("respondent.{$this->token}.locale", $locale);

        if (! $this->needsConsent || $this->consentGiven) {
            $this->loadQuestions();
        }
    }

    public function giveConsent(): void
    {
        if (! $this->hasValidPublicContext()) {
            return;
        }

        if (! $this->needsConsent || $this->consentGiven || $this->moduleId === 0) {
            return;
        }

        RespondentConsent::create([
            'assessment_id' => $this->assessmentId,
            'module_id' => $this->moduleId,
            'consent_text' => self::CONSENT_TEXT,
            'consented_by' => null,
            'respondent_session_id' => $this->respondentId,
            'consented_at' => now(),
        ]);

        $this->consentGiven = true;
        $this->loadQuestions();
        $this->loadExistingResponses();
    }

    public function selectOption(string $questionId, int $optionId): void
    {
        if (! $this->hasValidPublicContext()) {
            return;
        }

        if ($this->isSubmitted || $this->assessmentClosed) {
            return;
        }

        $validSelection = Question::where('question_id', $questionId)
            ->where('module_id', $this->moduleId)
            ->where('is_active', true)
            ->whereHas('options', fn ($query) => $query->where('option_id', $optionId))
            ->exists();

        if (! $validSelection) {
            return;
        }

        if ($this->needsConsent) {
            $hasConsent = RespondentConsent::where('assessment_id', $this->assessmentId)
                ->where('respondent_session_id', $this->respondentId)
                ->exists();

            if (! $hasConsent) {
                return;
            }
        }

        Response::updateOrCreate(
            [
                'assessment_id' => $this->assessmentId,
                'question_id' => $questionId,
                'respondent_id' => $this->respondentId,
            ],
            [
                'value_option_id' => $optionId,
                'answered_at' => now(),
            ]
        );

        $this->savedResponses[$questionId] = $optionId;
        $this->lastSavedAt = now()->format('g:i A');

        if ($this->currentIndex < count($this->questionData) - 1) {
            $this->currentIndex++;
        }
    }

    public function goToQuestion(int $index): void
    {
        if (! $this->hasValidPublicContext()) {
            return;
        }

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

    public function submit(): void
    {
        if (! $this->hasValidPublicContext()) {
            return;
        }

        if ($this->isSubmitted || $this->assessmentClosed || ! $this->canSubmit()) {
            return;
        }

        session()->put("respondent.{$this->token}.submitted", true);
        $this->isSubmitted = true;
    }

    private function loadQuestions(): void
    {
        if ($this->moduleId === 0) {
            return;
        }

        $questions = Question::with(['options', 'moduleDomain'])
            ->where('module_id', $this->moduleId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        $questionTranslations = collect();
        $optionTranslations = collect();

        if ($this->currentLocale !== 'en' && $questions->isNotEmpty()) {
            $questionIds = $questions->pluck('question_id');
            $optionIds = $questions->flatMap(fn ($q) => $q->options->pluck('option_id'));

            $questionTranslations = QuestionTranslation::where('locale', $this->currentLocale)
                ->whereIn('question_id', $questionIds)
                ->pluck('question_text', 'question_id');

            $optionTranslations = QuestionOptionTranslation::where('locale', $this->currentLocale)
                ->whereIn('option_id', $optionIds)
                ->pluck('option_label', 'option_id');
        }

        $this->questionData = $questions->map(fn (Question $q) => [
            'question_id' => $q->question_id,
            'question_code' => $q->question_code,
            'question_text' => $questionTranslations->get($q->question_id, $q->question_text),
            'is_scored' => $q->is_scored,
            'domain_label' => $q->moduleDomain?->domain_label ?? '',
            'domain_number' => $q->moduleDomain?->domain_number ?? 0,
            'options' => $q->options->map(fn ($o) => [
                'option_id' => $o->option_id,
                'option_label' => $optionTranslations->get($o->option_id, $o->option_label),
            ])->toArray(),
        ])->toArray();
    }

    private function loadExistingResponses(): void
    {
        if (empty($this->respondentId)) {
            return;
        }

        $responses = Response::where('assessment_id', $this->assessmentId)
            ->where('respondent_id', $this->respondentId)
            ->whereNotNull('value_option_id')
            ->get();

        foreach ($responses as $response) {
            $this->savedResponses[$response->question_id] = $response->value_option_id;
        }
    }

    private function resolveAvailableLocales(int $moduleId): array
    {
        $questionIds = Question::where('module_id', $moduleId)
            ->where('is_active', true)
            ->pluck('question_id');

        $translatedLocales = QuestionTranslation::whereIn('question_id', $questionIds)
            ->distinct()
            ->pluck('locale')
            ->toArray();

        $localeLabels = ['fr' => 'Français'];
        $locales = [['code' => 'en', 'label' => 'English']];

        foreach ($translatedLocales as $locale) {
            if (isset($localeLabels[$locale])) {
                $locales[] = ['code' => $locale, 'label' => $localeLabels[$locale]];
            }
        }

        return $locales;
    }

    private function hasValidPublicContext(): bool
    {
        if ($this->token === '' || $this->assessmentId === '' || $this->respondentId === '') {
            return false;
        }

        $tokenRecord = AssessmentRespondentToken::where('token', $this->token)
            ->where('assessment_id', $this->assessmentId)
            ->first();

        if (! $tokenRecord || ($tokenRecord->expires_at && $tokenRecord->expires_at->isPast())) {
            return false;
        }

        $assessment = Assessment::find($this->assessmentId);

        return $assessment !== null && $assessment->status !== 'COMPLETE';
    }

    public function render(): View
    {
        return view('livewire.public-respondent-runner');
    }
}
