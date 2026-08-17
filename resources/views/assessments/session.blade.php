<x-app-layout title="Respondent answers">
    <div class="max-w-3xl mx-auto">
        <a href="{{ route('assessments.respondent-collection', $assessment) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 mb-3">
            ← Back to responses
        </a>

        <div class="mb-5">
            <p class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide">Respondent answers · read only</p>
            <h1 class="mt-0.5 text-xl font-bold text-slate-900 dark:text-white">One respondent's submission</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $assessment->target?->name }} · submitted {{ $responseSession->submitted_at?->format('d M Y, H:i') }}
            </p>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            @php $currentSection = null; @endphp
            <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse ($answers as $a)
                    @if ($a['section'] && $a['section'] !== $currentSection)
                        @php $currentSection = $a['section']; @endphp
                        <li class="px-5 pt-4 pb-1 bg-slate-50 dark:bg-slate-900/40">
                            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ $currentSection }}</span>
                        </li>
                    @endif
                    <li class="flex items-start justify-between gap-4 px-5 py-3">
                        <span class="text-sm text-slate-700 dark:text-slate-200">{{ $a['question'] }}</span>
                        <span class="text-sm font-semibold text-slate-900 dark:text-white text-right flex-shrink-0 max-w-[45%]">{{ $a['answer'] }}</span>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">This respondent submitted no answers.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-app-layout>
