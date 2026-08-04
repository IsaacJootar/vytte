<x-app-layout title="Local questions">
    <div class="max-w-2xl mx-auto">
        <div class="mb-5">
            <p class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide">Last step</p>
            <h1 class="mt-0.5 text-xl font-bold text-slate-900 dark:text-white">{{ $section->section_title ?: 'Local context' }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                These local questions add context to the same report. They never change the published assessment score or benchmark.
            </p>
        </div>

        @if ($section->instructions)
            <div class="mb-4 rounded-xl border border-vytte-100 bg-vytte-50 px-4 py-3 text-sm text-vytte-900 dark:border-vytte-900 dark:bg-vytte-950/30 dark:text-vytte-100">{{ $section->instructions }}</div>
        @endif

        <form method="POST" action="{{ route('assessments.custom.finish', $assessment) }}" class="flex flex-col gap-3">
            @csrf
            @foreach ($section->questions as $q)
                <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $q['text'] }}</p>
                    <div class="mt-3">
                        @php $type = $q['response_type'] ?? \App\Support\LocalQuestionFormat::YES_NO; @endphp
                        @if (in_array($type, [\App\Support\LocalQuestionFormat::YES_NO, \App\Support\LocalQuestionFormat::YES_NO_NA], true))
                            <div class="flex flex-wrap gap-3">
                            @foreach (['YES' => 'Yes', 'NO' => 'No', ...($type === \App\Support\LocalQuestionFormat::YES_NO_NA ? ['NOT_APPLICABLE' => 'Not applicable'] : [])] as $val => $lbl)
                                <label class="inline-flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300">
                                    <input type="radio" name="answers[{{ $q['id'] }}]" value="{{ $val }}" class="text-vytte-600 focus:ring-vytte-500">
                                    {{ $lbl }}
                                </label>
                            @endforeach
                            </div>
                        @elseif ($type === \App\Support\LocalQuestionFormat::SCALE_5)
                            <div class="flex flex-wrap items-center gap-3">
                            @for ($n = 1; $n <= 5; $n++)
                                <label class="inline-flex items-center gap-1 text-sm text-slate-600 dark:text-slate-300">
                                    <input type="radio" name="answers[{{ $q['id'] }}]" value="{{ $n }}" class="text-vytte-600 focus:ring-vytte-500">
                                    {{ $n }}
                                </label>
                            @endfor
                            </div>
                            <div class="mt-1 flex justify-between text-[11px] text-slate-400"><span>1 = lowest</span><span>5 = highest</span></div>
                        @elseif ($type === \App\Support\LocalQuestionFormat::SINGLE_SELECT)
                            <div class="space-y-2">
                                @foreach ($q['choices'] ?? [] as $choice)
                                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input type="radio" name="answers[{{ $q['id'] }}]" value="{{ $choice }}" class="text-vytte-600 focus:ring-vytte-500">{{ $choice }}</label>
                                @endforeach
                            </div>
                        @elseif ($type === \App\Support\LocalQuestionFormat::MULTI_SELECT)
                            <div class="space-y-2">
                                @foreach ($q['choices'] ?? [] as $choice)
                                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input type="checkbox" name="answers[{{ $q['id'] }}][]" value="{{ $choice }}" class="rounded border-slate-300 text-vytte-600 focus:ring-vytte-500">{{ $choice }}</label>
                                @endforeach
                            </div>
                        @elseif ($type === \App\Support\LocalQuestionFormat::NUMERIC)
                            <div class="flex items-center gap-2">
                                <input type="number" step="any" name="answers[{{ $q['id'] }}]" @if (($q['numeric_min'] ?? null) !== null) min="{{ $q['numeric_min'] }}" @endif @if (($q['numeric_max'] ?? null) !== null) max="{{ $q['numeric_max'] }}" @endif
                                       class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                                @if ($q['numeric_unit'] ?? null)<span class="text-sm text-slate-500">{{ $q['numeric_unit'] }}</span>@endif
                            </div>
                        @else
                            <textarea name="answers[{{ $q['id'] }}]" rows="3" maxlength="5000" placeholder="Write your answer"
                                      class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white"></textarea>
                        @endif
                    </div>
                </div>
            @endforeach

            <div class="mt-3 flex items-center gap-3">
                <button type="submit" class="rounded-xl bg-vytte-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-800 transition-colors">Submit assessment</button>
                {{-- Optional: finish without answering the tailored questions. --}}
                <button type="submit" name="skip" value="1" @click="document.querySelectorAll('input[type=radio]:checked').forEach(r => r.checked = false)"
                        class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">Skip and finish</button>
            </div>
        </form>
    </div>
</x-app-layout>
