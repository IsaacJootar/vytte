<div>
    @if (! $tokenValid)

        <div class="text-center py-12">
            <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-red-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
            </div>
            <h2 class="text-base font-semibold text-slate-800 mb-1">This link is not valid</h2>
            <p class="text-sm text-slate-500">The assessment link you followed may have expired or been deactivated.</p>
        </div>

    @elseif ($assessmentClosed)

        <div class="text-center py-12">
            <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/>
                </svg>
            </div>
            <h2 class="text-base font-semibold text-slate-800 mb-1">This assessment is closed</h2>
            <p class="text-sm text-slate-500">Responses are no longer being collected for this assessment.</p>
        </div>

    @elseif ($isSubmitted)

        <div class="text-center py-12">
            <div class="w-14 h-14 rounded-full bg-green-50 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-green-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                </svg>
            </div>
            <h2 class="text-lg font-bold text-slate-800 mb-2">Thank you!</h2>
            <p class="text-sm text-slate-500 max-w-xs mx-auto">Your responses have been submitted. Your answers are anonymous and will be used to improve services in this community.</p>
        </div>

    @elseif (count($availableLocales) > 1 && ! $languageChosen)

        <div class="bg-white rounded-2xl border border-slate-200 p-6 text-center">
            <h2 class="text-base font-semibold text-slate-800 mb-1">Choose your language</h2>
            <p class="text-sm text-slate-500 mb-6">Choisissez votre langue</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                @foreach ($availableLocales as $locale)
                    <button
                        wire:click="selectLocale('{{ $locale['code'] }}')"
                        class="flex-1 sm:flex-none px-8 py-3 text-sm font-semibold rounded-xl border-2 transition-colors duration-150
                            {{ $currentLocale === $locale['code']
                                ? 'border-vytte-700 bg-vytte-700 text-white'
                                : 'border-slate-200 text-slate-700 hover:border-vytte-400 hover:bg-vytte-50' }}">
                        {{ $locale['label'] }}
                    </button>
                @endforeach
            </div>
        </div>

    @elseif ($needsConsent && ! $consentGiven)

        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <div class="flex items-start gap-4 mb-5">
                <div class="w-10 h-10 rounded-full bg-amber-50 border border-amber-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-5 h-5 text-amber-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-semibold text-slate-800">Before we begin</h2>
                    <p class="text-sm text-slate-500 mt-0.5">Please read and agree to the following before answering.</p>
                </div>
            </div>

            <div class="bg-slate-50 rounded-xl border border-slate-200 p-4 mb-5 text-sm text-slate-700 leading-relaxed">
                {{ \App\Livewire\PublicRespondentRunner::CONSENT_TEXT }}
            </div>

            <div x-data="{ agreed: false }">
                <label class="flex items-start gap-3 cursor-pointer mb-5">
                    <input type="checkbox" x-model="agreed"
                           class="mt-0.5 h-4 w-4 rounded border-slate-300 text-vytte-600 focus:ring-vytte-500">
                    <span class="text-sm text-slate-700 select-none">
                        I understand and agree to take part in this assessment.
                    </span>
                </label>
                <button
                    wire:click="giveConsent"
                    :disabled="!agreed"
                    :class="agreed ? 'bg-vytte-700 hover:bg-vytte-800 text-white' : 'bg-slate-200 text-slate-400 cursor-not-allowed'"
                    class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-lg transition-colors w-full sm:w-auto justify-center">
                    Continue
                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
        </div>

    @elseif (empty($questionData))

        <div class="text-center py-12">
            <p class="text-sm text-slate-500">No questions found for this assessment.</p>
        </div>

    @else

        {{-- Progress bar --}}
        <div class="mb-5">
            <div class="flex items-center justify-between text-xs text-slate-500 mb-1.5">
                <span class="font-semibold">Question {{ $currentIndex + 1 }} of {{ count($questionData) }}</span>
                <span>{{ $this->answeredCount() }} answered</span>
            </div>
            <div class="h-1.5 bg-slate-200 rounded-full overflow-hidden">
                @php $pct = count($questionData) > 0 ? ($this->answeredCount() / count($questionData)) * 100 : 0; @endphp
                <div class="h-full bg-vytte-600 rounded-full transition-all duration-300" style="width: {{ $pct }}%"></div>
            </div>
        </div>

        @php $q = $questionData[$currentIndex]; @endphp

        {{-- Domain header --}}
        @php $prevDomain = $currentIndex > 0 ? $questionData[$currentIndex - 1]['domain_number'] : null; @endphp
        @if (($currentIndex === 0 || $q['domain_number'] !== $prevDomain) && $q['domain_label'])
            <div class="mb-3 flex items-center gap-2">
                <span class="w-5 h-5 rounded-full bg-vytte-100 text-vytte-700 text-[10px] font-bold flex items-center justify-center flex-shrink-0">{{ $q['domain_number'] }}</span>
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">{{ $q['domain_label'] }}</span>
            </div>
        @endif

        {{-- Question card --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-4">
            <p class="text-base font-semibold text-slate-900 leading-snug mb-5">{{ $q['question_text'] }}</p>

            @if (! empty($q['options']))
                <div class="space-y-2">
                    @foreach ($q['options'] as $option)
                        @php $isSelected = ($savedResponses[$q['question_id']] ?? null) === $option['option_id']; @endphp
                        <button
                            wire:click="selectOption('{{ $q['question_id'] }}', {{ $option['option_id'] }})"
                            class="w-full text-left px-4 py-3 rounded-xl border text-sm font-medium transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-400 focus-visible:ring-offset-1
                                {{ $isSelected
                                    ? 'bg-vytte-700 border-vytte-700 text-white shadow-sm'
                                    : 'bg-white border-slate-200 text-slate-800 hover:border-vytte-400 hover:bg-vytte-50' }}">
                            {{ $option['option_label'] }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Nav --}}
        <div class="flex items-center justify-between gap-3">
            <button
                wire:click="goToQuestion({{ $currentIndex - 1 }})"
                @disabled($currentIndex === 0)
                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:border-slate-300 disabled:opacity-40 disabled:cursor-not-allowed">
                <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd"/>
                </svg>
                Previous
            </button>

            <div class="flex-1 flex justify-center">
                @if ($lastSavedAt)
                    <span class="inline-flex items-center gap-1 text-[11px] text-slate-400">
                        <svg class="w-3 h-3 text-green-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                        </svg>
                        Saved {{ $lastSavedAt }}
                    </span>
                @endif
            </div>

            @if ($currentIndex < count($questionData) - 1)
                <button
                    wire:click="goToQuestion({{ $currentIndex + 1 }})"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:border-slate-300">
                    Next
                    <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
                    </svg>
                </button>
            @else
                <div class="w-24"></div>
            @endif
        </div>

        {{-- Submit section --}}
        @if ($this->canSubmit())
            <div class="mt-6 pt-5 border-t border-slate-200">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">All questions answered</p>
                        <p class="text-xs text-slate-500 mt-0.5">You can submit your responses now.</p>
                    </div>
                    <button
                        wire:click="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-vytte-700 text-white text-sm font-semibold rounded-lg hover:bg-vytte-800 transition-colors">
                        Submit my answers
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </div>
        @endif

        {{-- Dot navigation --}}
        <div class="mt-5 pt-4 border-t border-slate-100">
            <div class="flex flex-wrap gap-1.5">
                @foreach ($questionData as $idx => $item)
                    <button
                        wire:click="goToQuestion({{ $idx }})"
                        title="Q{{ $idx + 1 }}"
                        class="w-7 h-7 rounded-lg text-[11px] font-bold transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-400
                            {{ $idx === $currentIndex
                                ? 'bg-vytte-700 text-white'
                                : (isset($savedResponses[$item['question_id']])
                                    ? 'bg-vytte-100 text-vytte-700'
                                    : 'bg-slate-100 text-slate-500 hover:bg-slate-200') }}">
                        {{ $idx + 1 }}
                    </button>
                @endforeach
            </div>
        </div>

    @endif
</div>
