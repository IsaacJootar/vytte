<x-app-layout title="Overall results">
    <div class="mb-5">
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Overall results</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">See the latest completed results across all the settings in this workspace.</p>
    </div>

    @if ($headline['assessed_settings'] === 0)
        <x-empty-state
            icon="squares-2x2"
            title="No completed results yet"
            message="Complete an assessment to see its result here alongside your other settings."
            :action="route('projects.index')"
            action-label="Go to projects" />
    @else
        <x-help-callout id="overall-results" title="What this page is for">
            <ul class="space-y-1 text-xs text-slate-600 dark:text-slate-300">
                <li><span class="font-semibold">Reports</span> gives you the full detail for one assessment.</li>
                <li><span class="font-semibold">Overall results</span> shows the latest completed result for every assessed setting.</li>
                <li>Vytte calculates an average only when settings used the same assessment and scoring rules. Unlike results are never ranked together.</li>
            </ul>
        </x-help-callout>

        <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat-card tone="blue" label="Settings assessed" :value="$headline['assessed_settings']"
                         :sub="$headline['total_settings'].' settings in total'" />
            <x-stat-card tone="strong" label="Completed assessments" :value="$headline['completed_assessments']"
                         sub="All completed reports" />
            <x-stat-card :tone="$headline['open_actions'] > 0 ? 'moderate' : 'slate'" label="Open actions" :value="$headline['open_actions']"
                         sub="Still to be completed" />
            <x-stat-card :tone="$headline['overdue_actions'] > 0 ? 'weak' : 'slate'" label="Overdue actions" :value="$headline['overdue_actions']"
                         sub="Need attention" />
        </div>

        @if ($headline['open_actions'] > 0)
            <div class="mb-5 flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-amber-800 dark:bg-amber-900/20">
                <div>
                    <p class="text-sm font-semibold text-amber-900 dark:text-amber-100">Follow through on the findings</p>
                    <p class="mt-0.5 text-xs text-amber-800 dark:text-amber-200">You have {{ $headline['open_actions'] }} open {{ Str::plural('action', $headline['open_actions']) }}{{ $headline['overdue_actions'] > 0 ? ', including '.$headline['overdue_actions'].' overdue' : '' }}.</p>
                </div>
                <a href="{{ route('actions.hub') }}" class="inline-flex justify-center rounded-lg bg-vytte-700 px-4 py-2 text-sm font-semibold text-white hover:bg-vytte-800">View actions</a>
            </div>
        @endif

        <section aria-labelledby="results-by-type-heading">
            <div class="mb-3">
                <h2 id="results-by-type-heading" class="text-base font-bold text-slate-900 dark:text-white">Results by assessment type</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Each setting's latest completed result. Like-for-like results are grouped together automatically.</p>
            </div>

            <div class="space-y-5">
                @foreach ($comparison_groups as $group)
                    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
                        <header class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between dark:border-slate-700">
                            <div>
                                <h3 class="font-semibold text-slate-900 dark:text-white">{{ $group['name'] }}</h3>
                                @if ($group['comparable'])
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">These {{ count($group['rows']) }} settings used the same assessment and scoring rules, so their results can be compared.</p>
                                @else
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Shown on its own. Vytte will not rank or average it with a different assessment type.</p>
                                @endif
                            </div>
                            @if ($group['comparable'])
                                <div class="rounded-xl bg-vytte-50 px-4 py-2 text-right dark:bg-vytte-900/20">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-vytte-600 dark:text-vytte-400">Group average</p>
                                    <p class="text-xl font-black tabular-nums text-vytte-800 dark:text-vytte-200">{{ number_format($group['average'], 1) }}</p>
                                </div>
                            @endif
                        </header>

                        <div class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach ($group['rows'] as $row)
                                @php
                                    $scoreTone = $row['score'] >= 70 ? 'bg-emerald-600' : ($row['score'] >= 45 ? 'bg-amber-500' : 'bg-red-600');
                                    $difference = $row['difference_from_average'];
                                @endphp
                                <div class="grid gap-3 px-5 py-4 md:grid-cols-[minmax(0,1fr)_minmax(12rem,0.8fr)_auto] md:items-center">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $row['setting_name'] }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                            {{ $row['performance_stage'] ?? 'Performance stage unavailable' }}
                                            @if ($row['completed_at']) · completed {{ $row['completed_at']->format('d M Y') }} @endif
                                        </p>
                                    </div>
                                    <div>
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="text-slate-500 dark:text-slate-400">Score</span>
                                            <span class="font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($row['score'], 1) }}</span>
                                        </div>
                                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700" aria-hidden="true">
                                            <div class="h-full rounded-full {{ $scoreTone }}" style="width: {{ min(100, max(0, $row['score'])) }}%"></div>
                                        </div>
                                        @if ($difference !== null)
                                            <p class="mt-1 text-right text-[11px] {{ $difference >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}">
                                                {{ $difference >= 0 ? '+' : '' }}{{ number_format($difference, 1) }} compared with this group's average
                                            </p>
                                        @endif
                                    </div>
                                    <a href="{{ route('assessments.results', $row['assessment_id']) }}" class="inline-flex justify-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">View report</a>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="mt-5 grid gap-5 sm:grid-cols-2" aria-labelledby="coverage-heading">
            <h2 id="coverage-heading" class="sr-only">Settings covered</h2>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-800">
                <h3 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">Settings by type</h3>
                @forelse ($coverage['by_type'] as $type => $count)
                    <div class="flex items-center justify-between border-b border-slate-200 py-1.5 text-sm last:border-0 dark:border-slate-700">
                        <span class="text-slate-600 dark:text-slate-300">{{ ucwords(strtolower(str_replace('_', ' ', $type))) }}</span>
                        <span class="font-semibold tabular-nums text-slate-800 dark:text-slate-200">{{ $count }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">No settings recorded yet.</p>
                @endforelse
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-800">
                <h3 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">Settings by country</h3>
                @forelse ($coverage['by_country'] as $country => $count)
                    <div class="flex items-center justify-between border-b border-slate-200 py-1.5 text-sm last:border-0 dark:border-slate-700">
                        <span class="text-slate-600 dark:text-slate-300">{{ $country }}</span>
                        <span class="font-semibold tabular-nums text-slate-800 dark:text-slate-200">{{ $count }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">Add countries to settings to see coverage here.</p>
                @endforelse
            </div>
        </section>
    @endif
</x-app-layout>
