<x-app-layout title="Tailored by your team">
    <div class="max-w-2xl mx-auto">
        <div class="mb-5">
            <p class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide">Last step</p>
            <h1 class="mt-0.5 text-xl font-bold text-slate-900 dark:text-white">{{ $section->section_title ?: 'Tailored by your team' }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Your own questions. Answer them to finish — they're scored on their own and never change the official score.
            </p>
        </div>

        <form method="POST" action="{{ route('assessments.custom.finish', $assessment) }}" class="flex flex-col gap-3">
            @csrf
            @foreach ($section->questions as $q)
                <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $q['text'] }}</p>
                    <div class="mt-2 flex flex-wrap gap-3">
                        @if (($q['response_type'] ?? 'YES_NO') === 'YES_NO')
                            @foreach (['YES' => 'Yes', 'NO' => 'No'] as $val => $lbl)
                                <label class="inline-flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300">
                                    <input type="radio" name="answers[{{ $q['id'] }}]" value="{{ $val }}" class="text-vytte-600 focus:ring-vytte-500">
                                    {{ $lbl }}
                                </label>
                            @endforeach
                        @else
                            @for ($n = 1; $n <= 5; $n++)
                                <label class="inline-flex items-center gap-1 text-sm text-slate-600 dark:text-slate-300">
                                    <input type="radio" name="answers[{{ $q['id'] }}]" value="{{ $n }}" class="text-vytte-600 focus:ring-vytte-500">
                                    {{ $n }}
                                </label>
                            @endfor
                            <span class="text-[11px] text-slate-400 dark:text-slate-500">(1 = low, 5 = high{{ ! empty($q['reversed']) ? ', reversed' : '' }})</span>
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
