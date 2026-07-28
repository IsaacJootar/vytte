<x-app-layout title="Set up assessment">
    <div class="max-w-3xl mx-auto">

        <a href="{{ route('assessments.show', $assessment) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 mb-3">
            ← Back to assessment
        </a>

        {{-- Title --}}
        <div class="mb-5">
            <p class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide">Set up · {{ $subject }}</p>
            <h1 class="mt-0.5 text-2xl font-bold text-slate-900 dark:text-white">
                @switch($step)
                    @case(1) Review this assessment @break
                    @case(2) Add your own questions @break
                    @case(3) How will it be answered? @break
                    @default Ready to go
                @endswitch
            </h1>
        </div>

        {{-- Step indicator --}}
        @php $steps = ['Review', 'Your questions', 'Collect answers', 'Finish']; @endphp
        <ol class="mb-6 flex items-center gap-2 text-xs font-semibold">
            @foreach ($steps as $i => $label)
                @php $n = $i + 1; $state = $n < $step ? 'done' : ($n === $step ? 'current' : 'todo'); @endphp
                <li class="flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full
                        {{ $state === 'current' ? 'bg-vytte-700 text-white' : ($state === 'done' ? 'bg-vytte-100 text-vytte-700 dark:bg-vytte-900/40 dark:text-vytte-300' : 'bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-500') }}">
                        @if ($state === 'done')
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        @else
                            {{ $n }}
                        @endif
                    </span>
                    <span class="{{ $state === 'todo' ? 'hidden sm:inline text-slate-400 dark:text-slate-500' : ($state === 'current' ? 'text-slate-900 dark:text-white' : 'hidden sm:inline text-slate-500 dark:text-slate-400') }}">{{ $label }}</span>
                </li>
                @if (! $loop->last)
                    <li class="h-px flex-1 bg-slate-200 dark:bg-slate-700" aria-hidden="true"></li>
                @endif
            @endforeach
        </ol>

        {{-- ===================== STEP 1 — REVIEW ===================== --}}
        @if ($step === 1)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <div class="flex flex-wrap gap-x-8 gap-y-3 text-sm">
                    <div>
                        <p class="text-xs text-slate-400 dark:text-slate-500">Assessment</p>
                        <p class="font-bold text-slate-900 dark:text-white">{{ $subject }}</p>
                    </div>
                    @if ($assessment->target)
                        <div>
                            <p class="text-xs text-slate-400 dark:text-slate-500">Target</p>
                            <p class="font-bold text-slate-900 dark:text-white">{{ $assessment->target->name }}</p>
                        </div>
                    @endif
                    <div>
                        <p class="text-xs text-slate-400 dark:text-slate-500">Vytte questions</p>
                        <p class="font-bold text-slate-900 dark:text-white">{{ $questionCount ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 dark:text-slate-500">Areas covered</p>
                        <p class="font-bold text-slate-900 dark:text-white">{{ $areas->count() ?: 1 }}</p>
                    </div>
                </div>
                @if ($areas->isNotEmpty())
                    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ $areas->join(' · ') }}</p>
                @endif
            </div>
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                These are the official Vytte questions. Next you can add your own — they're scored separately and never change the Vytte score.
            </p>

            {{-- Preview the actual questions and segments, so the user can judge them before adding. --}}
            @if ($assessment->snapshot)
                <div class="mt-4 rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800" x-data="{ open: false }">
                    <button type="button" @click="open = ! open" class="w-full flex items-center justify-between gap-3 px-5 py-3.5 text-left">
                        <span class="text-sm font-semibold text-slate-900 dark:text-white">Preview the questions</span>
                        <svg :class="open && 'rotate-180'" class="w-4 h-4 flex-shrink-0 text-slate-400 transition-transform" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                    </button>
                    <div x-show="open" x-cloak class="border-t border-slate-100 dark:border-slate-700 px-5 py-4 space-y-4">
                        @foreach (collect($assessment->snapshot->payload)->sortBy('display_order') as $module)
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-vytte-700 dark:text-vytte-400">{{ $module['module_name'] ?? $module['module_code'] }}</p>
                                @php $lastSection = null; @endphp
                                <ul class="mt-1.5 space-y-1">
                                    @foreach (collect($module['questions'] ?? [])->sortBy('display_order') as $q)
                                        @if (($q['section_label'] ?? '') !== '' && ($q['section_label'] ?? '') !== $lastSection)
                                            <li class="pt-1.5 text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ $q['section_label'] }}</li>
                                            @php $lastSection = $q['section_label']; @endphp
                                        @endif
                                        <li class="flex gap-2 text-xs text-slate-600 dark:text-slate-300">
                                            <span class="text-slate-300 dark:text-slate-600">•</span>
                                            <span>{{ $q['question_text'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Two clear choices — nobody is pushed into adding questions. --}}
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                <a href="{{ route('assessments.setup', ['assessment' => $assessment, 'step' => 3]) }}"
                   class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 dark:border-slate-600 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                    Continue without adding →
                </a>
                <a href="{{ route('assessments.setup', ['assessment' => $assessment, 'step' => 2]) }}"
                   class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-vytte-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-800 transition-colors">
                    Add my own questions →
                </a>
            </div>

        {{-- ===================== STEP 2 — YOUR QUESTIONS ===================== --}}
        @elseif ($step === 2)
            <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                Add questions that matter to your context, one at a time — scored on their own (0–100) in a separate
                “Tailored by your team” section. @if ($customCount > 0)<span class="font-semibold text-slate-700 dark:text-slate-200">{{ $customCount }} saved so far.</span>@endif
            </p>

            @include('assessments.partials.custom-questions-form', ['wizard' => true, 'redirectTo' => 'setup'])

            <div class="mt-6 border-t border-slate-100 dark:border-slate-700 pt-4">
                <a href="{{ route('assessments.setup', ['assessment' => $assessment, 'step' => 1]) }}" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">← Back</a>
            </div>

        {{-- ===================== STEP 3 — COLLECT ANSWERS ===================== --}}
        @elseif ($step === 3)
            <div class="grid gap-4 sm:grid-cols-2">
                {{-- Self --}}
                <a href="{{ route('assessments.setup', ['assessment' => $assessment, 'step' => 4, 'mode' => 'self']) }}"
                   class="flex flex-col rounded-2xl border-2 border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800 hover:border-vytte-500 dark:hover:border-vytte-500 transition-colors">
                    <div class="w-10 h-10 rounded-xl bg-vytte-50 dark:bg-vytte-900/30 flex items-center justify-center mb-3">
                        <svg class="w-5 h-5 text-vytte-600 dark:text-vytte-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zM6 12a4 4 0 00-4 4v1h16v-1a4 4 0 00-4-4H6z"/></svg>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Just me / my team</h3>
                    <p class="mt-1 flex-1 text-xs text-slate-500 dark:text-slate-400">One person works through the questions now. Best when you're assessing on the ground.</p>
                    <span class="mt-4 text-sm font-semibold text-vytte-700 dark:text-vytte-400">Choose this →</span>
                </a>

                {{-- Share — available for every assessment. --}}
                <a href="{{ route('assessments.setup', ['assessment' => $assessment, 'step' => 4, 'mode' => 'share']) }}"
                   class="flex flex-col rounded-2xl border-2 border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800 hover:border-vytte-500 dark:hover:border-vytte-500 transition-colors">
                    <div class="w-10 h-10 rounded-xl bg-vytte-50 dark:bg-vytte-900/30 flex items-center justify-center mb-3">
                        <svg class="w-5 h-5 text-vytte-600 dark:text-vytte-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/></svg>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Share a link for others</h3>
                    <p class="mt-1 flex-1 text-xs text-slate-500 dark:text-slate-400">Send a link so several people answer independently. Their answers are combined into one result you review and finalise.</p>
                    <span class="mt-4 text-sm font-semibold text-vytte-700 dark:text-vytte-400">Choose this →</span>
                </a>
            </div>

            <div class="mt-6 border-t border-slate-100 dark:border-slate-700 pt-4">
                <a href="{{ route('assessments.setup', ['assessment' => $assessment, 'step' => 2]) }}" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">← Back</a>
            </div>

        {{-- ===================== STEP 4 — FINISH ===================== --}}
        @else
            <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                @if ($mode === 'share')
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Share it for others to answer</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Publishing opens the assessment and lets you create a link to send. You review and finalise the combined result when enough people have answered.</p>
                    <div class="mt-5 flex flex-col sm:flex-row gap-3">
                        <a href="{{ route('assessments.respondent-collection', $assessment) }}"
                           class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-vytte-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-800 transition-colors">
                            Publish & create link →
                        </a>
                        <a href="{{ route('assessments.show', ['assessment' => $assessment, 'saved' => 1]) }}"
                           class="inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-700 px-5 py-2.5 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                            Save as draft for later
                        </a>
                    </div>
                @else
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Answer it yourself</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Work through the questions now. Your report appears the moment you submit. You can stop and come back — your answers are saved.</p>
                    <div class="mt-5 flex flex-col sm:flex-row gap-3">
                        <a href="{{ route('assessments.run', $assessment) }}"
                           class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-vytte-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-800 transition-colors">
                            {{ $hasResponses ? 'Continue answering' : 'Start answering now' }} →
                        </a>
                        <a href="{{ route('assessments.show', ['assessment' => $assessment, 'saved' => 1]) }}"
                           class="inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-700 px-5 py-2.5 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                            Save as draft for later
                        </a>
                    </div>
                @endif
            </div>

            <div class="mt-6 border-t border-slate-100 dark:border-slate-700 pt-4">
                <a href="{{ route('assessments.setup', ['assessment' => $assessment, 'step' => 3]) }}" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">← Back</a>
            </div>
        @endif

    </div>
</x-app-layout>
