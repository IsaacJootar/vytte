<?php

namespace App\Livewire;

use App\Models\Assessment;
use App\Models\AssessmentModule;
use App\Models\AssessmentModuleScope;
use App\Models\Question;
use App\Models\QuestionOptionTranslation;
use App\Models\QuestionTranslation;
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
        $this->isComplete = $assessment->status === 'COMPLETE';
        $this->loadQuestions();
        $this->loadExistingResponses();
        $this->checkConsentRequired();
    }

    private function loadQuestions(): void
    {
        $scopeRows = AssessmentModuleScope::where('assessment_id', $this->assessment->assessment_id)
            ->where('in_scope', true)
            ->orderBy('module_id')
            ->get();

        if ($scopeRows->isEmpty()) {
            return;
        }

        $moduleIds = $scopeRows->pluck('module_id')->toArray();
        $this->moduleCount = count($moduleIds);

        // Load module codes for section headers
        $moduleCodes = AssessmentModule::whereIn('module_id', $moduleIds)
            ->pluck('module_code', 'module_id');

        $questions = Question::with(['options', 'moduleDomain'])
            ->whereIn('module_id', $moduleIds)
            ->where('is_active', true)
            ->get()
            ->sort(function (Question $a, Question $b) use ($moduleIds) {
                $aPos = array_search($a->module_id, $moduleIds);
                $bPos = array_search($b->module_id, $moduleIds);
                if ($aPos !== $bPos) {
                    return $aPos - $bPos;
                }
                $aDomain = $a->moduleDomain?->domain_number ?? 0;
                $bDomain = $b->moduleDomain?->domain_number ?? 0;
                if ($aDomain !== $bDomain) {
                    return $aDomain - $bDomain;
                }

                return $a->display_order - $b->display_order;
            })
            ->values();

        $locale = App::getLocale();

        $questionTranslations = collect();
        $optionTranslations = collect();

        if ($locale !== 'en' && $questions->isNotEmpty()) {
            $questionIds = $questions->pluck('question_id');
            $optionIds = $questions->flatMap(fn ($q) => $q->options->pluck('option_id'));

            $questionTranslations = QuestionTranslation::where('locale', $locale)
                ->whereIn('question_id', $questionIds)
                ->pluck('question_text', 'question_id');

            $optionTranslations = QuestionOptionTranslation::where('locale', $locale)
                ->whereIn('option_id', $optionIds)
                ->pluck('option_label', 'option_id');
        }

        $this->questionData = $questions->map(fn (Question $q) => [
            'question_id' => $q->question_id,
            'question_code' => $q->question_code,
            'question_text' => $questionTranslations->get($q->question_id, $q->question_text),
            'is_scored' => $q->is_scored,
            'module_id' => $q->module_id,
            'module_code' => $moduleCodes[$q->module_id] ?? '',
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
        $scopeModuleIds = AssessmentModuleScope::where('assessment_id', $this->assessment->assessment_id)
            ->where('in_scope', true)
            ->pluck('module_id');

        if ($scopeModuleIds->isEmpty()) {
            return;
        }

        $consentModule = AssessmentModule::whereIn('module_id', $scopeModuleIds)
            ->where('requires_consent', true)
            ->first();

        $this->needsConsent = $consentModule !== null;
        $this->consentModuleId = $consentModule?->module_id;

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

        $moduleIds = AssessmentModuleScope::where('assessment_id', $this->assessment->assessment_id)
            ->where('in_scope', true)
            ->pluck('module_id');

        $validSelection = Question::where('question_id', $questionId)
            ->whereIn('module_id', $moduleIds)
            ->where('is_active', true)
            ->whereHas('options', fn ($query) => $query->where('option_id', $optionId))
            ->exists();

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
        $this->authorizeAssessmentAccess();
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
}
