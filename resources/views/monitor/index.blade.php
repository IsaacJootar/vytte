<x-app-layout title="Monitor Responses">

    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Monitor Responses</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Assessments currently open and collecting responses from respondents.</p>
    </div>

    @if ($assessments->isEmpty())
        <x-empty-state
            icon="signal"
            title="Nothing collecting right now"
            message="When you share an assessment for others to answer, it appears here so you can watch responses arrive."
            :action="route('assessments.index')"
            action-label="Go to assessments" />
    @else
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach ($assessments as $assessment)
                    @php
                        $title = $assessment->target?->name ?? $assessment->project?->name ?? 'Assessment';
                        $done = $assessment->completed_sessions_count;
                        $total = $assessment->public_response_sessions_count;
                    @endphp
                    <li>
                        <a href="{{ route('assessments.monitor', $assessment) }}"
                           class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $title }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $assessment->project?->name }}{{ $assessment->isClosed() ? ' · collection closed' : ' · collecting' }}</p>
                            </div>
                            <div class="flex items-center gap-4 flex-shrink-0">
                                <div class="text-right">
                                    <p class="text-sm font-bold text-slate-900 dark:text-white tabular-nums">{{ $done }}<span class="text-slate-400 dark:text-slate-500 font-normal"> / {{ $total }}</span></p>
                                    <p class="text-[10px] uppercase tracking-wide text-slate-400 dark:text-slate-500">completed</p>
                                </div>
                                <span class="text-xs font-semibold text-vytte-700 dark:text-vytte-400">Monitor →</span>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($assessments->hasPages())
            <div class="mt-5">{{ $assessments->links() }}</div>
        @endif
    @endif

</x-app-layout>
