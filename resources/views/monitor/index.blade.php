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
        @php
            // Where responses are coming from: completed responses grouped by the location of the
            // place each collection is for (a school or facility), so a gap in one area stands out.
            $byArea = $assessments->getCollection()
                ->groupBy(fn ($assessment) => $assessment->target?->sub_region ?: ($assessment->target?->region ?: 'Location not set'))
                ->map(fn ($group) => ['places' => $group->count(), 'completed' => $group->sum('completed_sessions_count')])
                ->sortKeys();
        @endphp
        @if ($byArea->count() > 1 || $byArea->keys()->first() !== 'Location not set')
            <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Where responses are coming from</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($byArea as $area => $summary)
                        <span class="inline-flex items-baseline gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 text-xs dark:bg-slate-700">
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $area }}</span>
                            <span class="tabular-nums text-slate-500 dark:text-slate-300">{{ $summary['completed'] }} completed · {{ $summary['places'] }} {{ \Illuminate\Support\Str::plural('place', $summary['places']) }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach ($assessments as $assessment)
                    @php
                        // Lead with the subject (the assessment taken), then the target it is for,
                        // so open collections are told apart at a glance.
                        $subject = $assessment->catalogueRelease?->release_name ?? 'Assessment';
                        $done = $assessment->completed_sessions_count;
                        $total = $assessment->public_response_sessions_count;
                        $place = collect([$assessment->target?->sub_region, $assessment->target?->region])->filter()->implode(', ');
                    @endphp
                    <li>
                        <a href="{{ route('assessments.respondent-collection', $assessment) }}"
                           class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $subject }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $assessment->target?->name ?? $assessment->project?->name }}{{ $place !== '' ? ' · '.$place : '' }}{{ $assessment->isClosed() ? ' · collection closed' : ' · collecting' }}</p>
                            </div>
                            <div class="flex items-center gap-4 flex-shrink-0">
                                <div class="text-right">
                                    <p class="text-sm font-bold text-slate-900 dark:text-white tabular-nums">{{ $done }}<span class="text-slate-400 dark:text-slate-500 font-normal"> / {{ $total }}</span></p>
                                    <p class="text-[10px] uppercase tracking-wide text-slate-400 dark:text-slate-500">completed</p>
                                </div>
                                <span class="text-xs font-semibold text-vytte-700 dark:text-vytte-400">Review →</span>
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
