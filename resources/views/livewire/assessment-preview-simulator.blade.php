<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-vytte-200 bg-vytte-50 p-4 dark:border-vytte-900 dark:bg-vytte-950/40">
        <div>
            <p class="text-sm font-semibold text-vytte-900 dark:text-vytte-100">Interactive simulation — nothing is saved</p>
            <p class="mt-1 text-xs text-vytte-700 dark:text-vytte-300">Try different answers and response states. Conditional questions update using the exact frozen logic.</p>
        </div>
        <div class="flex items-center gap-2">
            @if ($hiddenCount > 0)<span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-vytte-700 dark:bg-slate-900">{{ $hiddenCount }} currently hidden</span>@endif
            <button type="button" wire:click="resetSimulation" class="rounded-lg border border-vytte-300 px-3 py-1.5 text-xs font-semibold text-vytte-800 dark:border-vytte-800 dark:text-vytte-200">Reset</button>
        </div>
    </div>

    <div class="max-w-3xl space-y-4">
        @foreach ($visibleQuestions as $question)
            @php
                $previous = $loop->index > 0 ? $visibleQuestions[$loop->index - 1] : null;
                $showSection = ! $previous || ($previous['section_id'] ?? null) !== ($question['section_id'] ?? null);
                $answer = $answers[$question['question_id']] ?? [];
            @endphp
            @if ($showSection)
                <div class="rounded-xl border border-vytte-100 bg-vytte-50 p-4 dark:border-vytte-900 dark:bg-vytte-950/30">
                    <h2 class="text-sm font-bold text-vytte-900 dark:text-vytte-100">{{ $question['section_name'] }}</h2>
                    @if ($question['section_instructions'])<p class="mt-1 text-sm text-vytte-800 dark:text-vytte-200">{{ $question['section_instructions'] }}</p>@endif
                    @if ($question['section_estimated_minutes'] || $question['section_respondent_role'])
                        <p class="mt-1 text-xs text-vytte-700 dark:text-vytte-300">{{ $question['section_estimated_minutes'] ? $question['section_estimated_minutes'].' minutes' : '' }}{{ $question['section_estimated_minutes'] && $question['section_respondent_role'] ? ' · ' : '' }}{{ $question['section_respondent_role'] ? 'Best answered by '.$question['section_respondent_role'] : '' }}</p>
                    @endif
                </div>
            @endif

            <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $question['question_text'] }}</p>
                    @if (($question['applicability']['type'] ?? null) === 'response_rule')<span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Conditional</span>@endif
                </div>
                <div class="mt-4 space-y-2">
                    @if (($question['response_type'] ?? null) === 'MULTI_SELECT')
                        @foreach ($question['options'] ?? [] as $option)
                            @php $selected = in_array($option['option_id'], $answer['option_ids'] ?? [], true); @endphp
                            <button type="button" wire:click="toggleMultiOption('{{ $question['question_id'] }}', {{ $option['option_id'] }})" class="flex w-full items-center gap-2 rounded-xl border px-3 py-2 text-left text-sm {{ $selected ? 'border-vytte-700 bg-vytte-700 text-white' : 'border-slate-200 text-slate-700 dark:border-slate-700 dark:text-slate-200' }}"><span class="flex h-4 w-4 items-center justify-center rounded border border-current">{{ $selected ? '✓' : '' }}</span>{{ $option['option_label'] }}</button>
                        @endforeach
                    @elseif (in_array($question['response_type'] ?? null, ['SINGLE_SELECT', 'LIKERT'], true))
                        @foreach ($question['options'] ?? [] as $option)
                            @php $selected = in_array($option['option_id'], $answer['option_ids'] ?? [], true); @endphp
                            <button type="button" wire:click="selectOption('{{ $question['question_id'] }}', {{ $option['option_id'] }})" class="w-full rounded-xl border px-3 py-2 text-left text-sm {{ $selected ? 'border-vytte-700 bg-vytte-700 text-white' : 'border-slate-200 text-slate-700 dark:border-slate-700 dark:text-slate-200' }}">{{ $option['option_label'] }}</button>
                        @endforeach
                    @elseif (($question['response_type'] ?? null) === 'NUMERIC')
                        <input type="number" step="any" value="{{ $answer['number'] ?? '' }}" wire:change="setNumeric('{{ $question['question_id'] }}', $event.target.value)" placeholder="Enter a number" class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900">
                    @else
                        <textarea rows="3" wire:change="setText('{{ $question['question_id'] }}', $event.target.value)" class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900" placeholder="Type a test answer">{{ $answer['text'] ?? '' }}</textarea>
                    @endif
                </div>
                <details class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-700">
                    <summary class="cursor-pointer text-xs font-semibold text-slate-500">Try a non-answer state</summary>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach (['NOT_APPLICABLE' => 'Not applicable', 'UNKNOWN' => 'Unknown', 'NOT_ASSESSED' => 'Not assessed', 'NOT_OBSERVED' => 'Not observed', 'DECLINED' => 'Prefer not to answer'] as $state => $label)
                            <button type="button" wire:click="setResponseState('{{ $question['question_id'] }}', '{{ $state }}')" class="rounded-lg border px-2.5 py-1 text-xs font-semibold {{ ($answer['state'] ?? null) === $state ? 'border-vytte-600 bg-vytte-50 text-vytte-800 dark:bg-vytte-950 dark:text-vytte-200' : 'border-slate-200 text-slate-500 dark:border-slate-700' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </details>
            </div>
        @endforeach
    </div>
</div>
