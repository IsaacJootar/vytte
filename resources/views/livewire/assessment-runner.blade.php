<div>

    {{-- Completed banner --}}
    @if ($isComplete)
        <div class="mb-5 flex items-center gap-3 px-4 py-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-800 font-medium">
            <svg class="w-4 h-4 text-green-600 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
            </svg>
            This assessment has been submitted and is read-only.
        </div>
    @endif

    @if (empty($questionData))
        <div class="bg-white rounded-xl border border-slate-200 px-5 py-12 flex flex-col items-center text-center">
            <p class="text-sm font-semibold text-slate-700">No questions found</p>
            <p class="mt-1 text-xs text-slate-400">This module has no active questions.</p>
        </div>
    @else

        {{-- Progress header --}}
        <div class="mb-5">
            <div class="flex items-center justify-between text-xs text-slate-500 mb-1.5">
                <span class="font-semibold">Question {{ $currentIndex + 1 }} of {{ count($questionData) }}</span>
                <span>{{ $this->answeredCount() }} answered</span>
            </div>
            <div class="h-1.5 bg-slate-200 rounded-full overflow-hidden">
                @php $pct = count($questionData) > 0 ? ($this->answeredCount() / count($questionData)) * 100 : 0; @endphp
                <div class="h-full bg-vytte-600 rounded-full transition-all duration-300"
                     style="width: {{ $pct }}%"></div>
            </div>
        </div>

        @php $q = $questionData[$currentIndex]; @endphp

        {{-- Domain header (show when on first question of a domain) --}}
        @php
            $prevDomain = $currentIndex > 0 ? $questionData[$currentIndex - 1]['domain_number'] : null;
            $showDomainHeader = $currentIndex === 0 || $q['domain_number'] !== $prevDomain;
        @endphp
        @if ($showDomainHeader && $q['domain_label'])
            <div class="mb-3 flex items-center gap-2">
                <span class="w-5 h-5 rounded-full bg-vytte-100 text-vytte-700 text-[10px] font-bold flex items-center justify-center flex-shrink-0">
                    {{ $q['domain_number'] }}
                </span>
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">{{ $q['domain_label'] }}</span>
            </div>
        @endif

        {{-- Question card --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-4">

            {{-- Question code + text --}}
            <p class="text-[10px] font-mono text-slate-400 mb-2">{{ $q['question_code'] }}</p>
            <p class="text-base font-semibold text-slate-900 leading-snug mb-5">{{ $q['question_text'] }}</p>

            @if (! $q['is_scored'])
                <p class="text-xs text-amber-600 font-medium mb-3">This question is not scored — your answer is recorded as a note.</p>
            @endif

            {{-- Answer options --}}
            @if (! empty($q['options']))
                <div class="space-y-2">
                    @foreach ($q['options'] as $option)
                        @php $isSelected = ($savedResponses[$q['question_id']] ?? null) === $option['option_id']; @endphp
                        <button
                            wire:click="selectOption('{{ $q['question_id'] }}', {{ $option['option_id'] }})"
                            @if ($isComplete) disabled @endif
                            class="w-full text-left px-4 py-3 rounded-xl border text-sm font-medium transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-vytte-400 focus:ring-offset-1
                                {{ $isSelected
                                    ? 'bg-vytte-700 border-vytte-700 text-white shadow-sm'
                                    : 'bg-white border-slate-200 text-slate-800 hover:border-vytte-400 hover:bg-vytte-50' }}
                                {{ $isComplete ? 'cursor-default' : 'cursor-pointer' }}">
                            {{ $option['option_label'] }}
                        </button>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-slate-400 italic">Open-ended — no answer options.</p>
            @endif
        </div>

        {{-- Navigation --}}
        <div class="flex items-center justify-between gap-3">
            <button
                wire:click="goToQuestion({{ $currentIndex - 1 }})"
                @if ($currentIndex === 0) disabled @endif
                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg transition-colors hover:border-slate-300 disabled:opacity-40 disabled:cursor-not-allowed">
                <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd"/>
                </svg>
                Previous
            </button>

            {{-- Saved indicator --}}
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
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg transition-colors hover:border-slate-300">
                    Next
                    <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
                    </svg>
                </button>
            @else
                <div class="w-24"></div>
            @endif
        </div>

        {{-- Submit section (shown at last question or when all answered) --}}
        @if ($this->canSubmit() && ! $isComplete)
            <div class="mt-6 pt-5 border-t border-slate-200">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">All questions answered</p>
                        <p class="text-xs text-slate-500 mt-0.5">Submit to finalise the assessment. This cannot be undone.</p>
                    </div>
                    <form method="POST" action="{{ route('assessments.submit', $assessment) }}">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-vytte-700 text-white text-sm font-semibold rounded-lg hover:bg-vytte-800 transition-colors duration-150">
                            Submit Assessment
                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Question dot navigation (for review / jumping) --}}
        <div class="mt-5 pt-4 border-t border-slate-100">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-2">Jump to question</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($questionData as $idx => $item)
                    <button
                        wire:click="goToQuestion({{ $idx }})"
                        title="Q{{ $idx + 1 }}"
                        class="w-7 h-7 rounded-lg text-[11px] font-bold transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-vytte-400
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
