<x-app-layout title="Assessment Portfolio">
    <div class="mb-5">
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Assessment Portfolio</h1>
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
                <li><span class="font-semibold">Assessment Portfolio</span> brings together completed work across your settings.</li>
                <li>Vytte calculates an average only when settings used the same assessment and scoring rules. Unlike results are never ranked together.</li>
            </ul>
        </x-help-callout>

        <div class="mb-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat-card tone="blue" label="Settings assessed" :value="$headline['assessed_settings']"
                         :sub="$headline['total_settings'].' settings in total'" />
            <x-stat-card tone="strong" label="Completed assessments" :value="$headline['completed_assessments']"
                         :sub="$headline['repeated_compatible_assessments'].' settings with a repeated compatible run'" />
            <x-stat-card :tone="$headline['open_actions'] > 0 ? 'moderate' : 'slate'" label="Open actions" :value="$headline['open_actions']"
                         sub="Still to be completed" />
            <x-stat-card :tone="$headline['overdue_actions'] > 0 ? 'weak' : 'slate'" label="Overdue actions" :value="$headline['overdue_actions']"
                         sub="Need attention" />
        </div>

        @if ($headline['repeated_compatible_assessments'] > 0)
            <div class="mb-5 grid grid-cols-3 gap-3">
                <x-stat-card :tone="$headline['improving_settings'] > 0 ? 'strong' : 'slate'" label="Improving" :value="$headline['improving_settings']" sub="Settings" />
                <x-stat-card tone="slate" label="Unchanged" :value="$headline['unchanged_settings']" sub="Settings" />
                <x-stat-card :tone="$headline['declining_settings'] > 0 ? 'weak' : 'slate'" label="Declining" :value="$headline['declining_settings']" sub="Settings" />
            </div>
        @endif

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
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $group['why'] }}</p>
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
                        @if (! empty($group['domain_comparison']))
                            <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-700">
                                <p class="mb-2 text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Domain averages across this group</p>
                                <div class="flex flex-wrap gap-x-4 gap-y-1">
                                    @foreach ($group['domain_comparison'] as $dc)
                                        <span class="text-xs text-slate-600 dark:text-slate-300">{{ $dc['domain_name'] }} <span class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($dc['average'], 1) }}</span></span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        @if (! empty($progress))
            <section class="mt-5" aria-labelledby="progress-heading">
                <div class="mb-3">
                    <h2 id="progress-heading" class="text-base font-bold text-slate-900 dark:text-white">Progress over time</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Settings with a repeated compatible assessment. Open a setting's own Progress page for its full history.</p>
                </div>
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
                    <div class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach ($progress as $p)
                            @php
                                $dirColor = $p['direction'] === 'UP' ? 'text-emerald-700 dark:text-emerald-400' : ($p['direction'] === 'DOWN' ? 'text-red-700 dark:text-red-400' : 'text-slate-500 dark:text-slate-400');
                                $arrow = $p['direction'] === 'UP' ? '▲' : ($p['direction'] === 'DOWN' ? '▼' : '—');
                            @endphp
                            <a href="{{ route('projects.progress', $p['project_id']) }}" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $p['setting_name'] }}</span>
                                <span class="flex items-center gap-2 text-xs">
                                    <span class="text-slate-400 dark:text-slate-500">{{ $p['runs'] }} runs</span>
                                    <span class="font-bold tabular-nums {{ $dirColor }}">{{ $arrow }} {{ $p['overall_delta'] !== null ? ($p['overall_delta'] > 0 ? '+' : '').number_format($p['overall_delta'], 1) : '—' }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (! empty($domain_trends))
            <section class="mt-5" aria-labelledby="domain-trends-heading">
                <div class="mb-3">
                    <h2 id="domain-trends-heading" class="text-base font-bold text-slate-900 dark:text-white">Domain trends</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">How many settings' compatible series are improving, stable, or declining in each domain.</p>
                </div>
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-700">
                                <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Domain</th>
                                <th class="px-4 py-2.5 text-center text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wide">Improving</th>
                                <th class="px-4 py-2.5 text-center text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Stable</th>
                                <th class="px-4 py-2.5 text-center text-[10px] font-bold text-red-600 dark:text-red-400 uppercase tracking-wide">Declining</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach ($domain_trends as $dt)
                                <tr>
                                    <td class="px-5 py-2.5 text-slate-700 dark:text-slate-200">{{ $dt['domain_name'] }}</td>
                                    <td class="px-4 py-2.5 text-center font-semibold tabular-nums text-emerald-700 dark:text-emerald-400">{{ $dt['improving'] }}</td>
                                    <td class="px-4 py-2.5 text-center font-semibold tabular-nums text-slate-500 dark:text-slate-400">{{ $dt['stable'] }}</td>
                                    <td class="px-4 py-2.5 text-center font-semibold tabular-nums text-red-700 dark:text-red-400">{{ $dt['declining'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($issue_movement['tracked_settings'] > 0)
            <section class="mt-5" aria-labelledby="issue-movement-heading">
                <div class="mb-3">
                    <h2 id="issue-movement-heading" class="text-base font-bold text-slate-900 dark:text-white">Issue movement</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Exact assessed issues across {{ $issue_movement['tracked_settings'] }} {{ Str::plural('setting', $issue_movement['tracked_settings']) }} with a repeated compatible assessment.</p>
                </div>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <x-stat-card :tone="$issue_movement['new'] > 0 ? 'weak' : 'slate'" label="New issues" :value="$issue_movement['new']" />
                    <x-stat-card :tone="$issue_movement['persistent'] > 0 ? 'moderate' : 'slate'" label="Still open" :value="$issue_movement['persistent']" />
                    <x-stat-card :tone="$issue_movement['improving'] > 0 ? 'blue' : 'slate'" label="Improving" :value="$issue_movement['improving']" />
                    <x-stat-card :tone="$issue_movement['resolved'] > 0 ? 'strong' : 'slate'" label="Resolved" :value="$issue_movement['resolved']" />
                </div>
                @if ($issue_movement['not_comparable'] > 0)
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        {{ $issue_movement['not_comparable'] }} currently open {{ Str::plural('issue', $issue_movement['not_comparable']) }} not counted above — an earlier assessment exists but used a different methodology.
                    </p>
                @endif
            </section>
        @endif

        <section class="mt-5" aria-labelledby="follow-through-heading">
            <div class="mb-3">
                <h2 id="follow-through-heading" class="text-base font-bold text-slate-900 dark:text-white">Action follow-through</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Across the whole workspace. Action completion is implementation evidence — resolution is confirmed only by a later compatible assessment.</p>
            </div>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <x-stat-card tone="slate" label="Open" :value="$action_follow_through['open']" />
                <x-stat-card tone="blue" label="In progress" :value="$action_follow_through['in_progress']" />
                <x-stat-card tone="strong" label="Done" :value="$action_follow_through['done']" />
                <x-stat-card tone="strong" label="Verified" :value="$action_follow_through['verified']" />
                <x-stat-card :tone="$action_follow_through['overdue'] > 0 ? 'weak' : 'slate'" label="Overdue" :value="$action_follow_through['overdue']" />
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

        @if (! empty($coverage['not_yet_assessed']) || $coverage['insufficient_history'] > 0 || ($coverage['overdue_reassessments'] ?? 0) > 0)
            <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800" aria-labelledby="follow-up-heading">
                <h2 id="follow-up-heading" class="mb-3 text-sm font-bold text-slate-900 dark:text-white">Where to focus next</h2>
                <ul class="space-y-1.5 text-sm text-slate-600 dark:text-slate-300">
                    @if (! empty($coverage['not_yet_assessed']))
                        <li>{{ count($coverage['not_yet_assessed']) }} {{ Str::plural('setting', count($coverage['not_yet_assessed'])) }} not yet assessed: {{ collect($coverage['not_yet_assessed'])->pluck('name')->take(5)->join(', ') }}{{ count($coverage['not_yet_assessed']) > 5 ? ', …' : '' }}</li>
                    @endif
                    @if ($coverage['insufficient_history'] > 0)
                        <li>{{ $coverage['insufficient_history'] }} {{ Str::plural('setting', $coverage['insufficient_history']) }} with only a baseline result — not enough history yet for a trend.</li>
                    @endif
                    @if (($coverage['overdue_reassessments'] ?? 0) > 0)
                        <li>{{ $coverage['overdue_reassessments'] }} scheduled {{ Str::plural('reassessment', $coverage['overdue_reassessments']) }} overdue.</li>
                    @endif
                </ul>
            </section>
        @endif
    @endif
</x-app-layout>
