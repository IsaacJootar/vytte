<x-app-layout title="Portfolio">

    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Portfolio</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Your whole program in one place — every completed assessment, harmonised.</p>
    </div>

    @if ($headline['assessed_targets'] === 0)
        <x-empty-state
            icon="squares-2x2"
            title="Nothing to harmonise yet"
            message="The portfolio fills in as your targets complete assessments. Run and finish an assessment to see the program picture."
            :action="route('projects.index')"
            action-label="Go to projects" />
    @else
        <x-help-callout id="portfolio" title="How to read this page">
            <ul class="text-xs text-slate-600 dark:text-slate-300 space-y-1 list-disc list-inside">
                <li>Every number here is a <span class="font-semibold">program-level average</span> across your targets' latest assessments — a management view, not one facility's official score.</li>
                <li>Start with <span class="font-semibold">Where to act</span> to see the areas weakest across the whole program.</li>
                <li>The <span class="font-semibold">heatmap</span> shows which areas are strong or weak across which targets at a glance.</li>
            </ul>
        </x-help-callout>

        {{-- Program headline --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <x-stat-card tone="blue" label="Program score"
                         :value="$headline['program_average'] !== null ? number_format($headline['program_average'], 1) : '—'"
                         sub="Average across assessed targets" />
            <x-stat-card tone="slate" label="Assessed targets" :value="$headline['assessed_targets']"
                         :sub="$headline['total_targets'].' targets in total'" />
            <x-stat-card tone="strong" label="Completed assessments" :value="$headline['completed_assessments']" sub="Across the workspace" />
            <x-stat-card :tone="$where_to_act['overdue_actions'] > 0 ? 'weak' : 'moderate'" label="Open actions" :value="$where_to_act['open_actions']"
                         :sub="$where_to_act['overdue_actions'].' overdue'" />
        </div>

        {{-- Maturity mix --}}
        <div class="mb-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Where your targets sit on the maturity ladder</h2>
            <div class="grid grid-cols-5 gap-2 text-center">
                @foreach ($headline['maturity'] as $level => $m)
                    <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-2">
                        <p class="text-lg font-black text-slate-900 dark:text-white tabular-nums">{{ $m['count'] }}</p>
                        <p class="text-[10px] font-bold text-vytte-700 dark:text-vytte-400">L{{ $level }}</p>
                        <p class="text-[10px] text-slate-400 dark:text-slate-500 leading-tight">{{ $m['name'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Where to act --}}
        <div class="mb-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Where to act</h2>
            <p class="text-xs text-slate-400 dark:text-slate-500 mb-3">The areas weakest across the whole program — where attention pays off most.</p>
            @if (! empty($where_to_act['weakest_areas']))
                <div class="flex flex-col gap-2">
                    @foreach ($where_to_act['weakest_areas'] as $area)
                        @php $c = $area['average'] >= 45 ? '#B45309' : '#B91C1C'; @endphp
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2">
                            <span class="text-sm text-slate-700 dark:text-slate-200">{{ $area['domain_name'] }}</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">program average
                                <span class="font-bold tabular-nums" style="color: {{ $c }}">{{ number_format($area['average'], 1) }}</span>
                                · {{ $area['facility_count'] }} {{ Str::plural('target', $area['facility_count']) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-slate-500 dark:text-slate-400">No weak areas across the program — every measured area averages 60 or above.</p>
            @endif
        </div>

        {{-- Target league table --}}
        <div class="mb-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-3">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Targets, ranked</h2>
                <a href="{{ route('benchmark.index') }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:underline">Full comparison →</a>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-700">
                        <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Rank</th>
                        <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Target</th>
                        <th class="px-5 py-2.5 text-right text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Score</th>
                        <th class="px-5 py-2.5 text-right text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">vs Average</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($league as $row)
                        @php
                            $band = $row['score'] >= 70 ? '#15803D' : ($row['score'] >= 45 ? '#B45309' : '#B91C1C');
                            $delta = $row['vs_average'];
                        @endphp
                        <tr>
                            <td class="px-5 py-3 text-xs text-slate-400 dark:text-slate-500 tabular-nums">{{ $row['rank'] }}</td>
                            <td class="px-5 py-3 text-slate-700 dark:text-slate-200">{{ $row['project_name'] }}</td>
                            <td class="px-5 py-3 text-right font-bold tabular-nums" style="color: {{ $band }}">{{ number_format($row['score'], 1) }}</td>
                            <td class="px-5 py-3 text-right text-xs tabular-nums {{ $delta === null ? 'text-slate-400' : ($delta >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400') }}">
                                {{ $delta === null ? '—' : ($delta >= 0 ? '+' : '').number_format($delta, 1) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Domain heatmap --}}
        @if (! empty($heatmap['rows']) && ! empty($heatmap['domains']))
            <div class="mb-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Area heatmap</h2>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Each target's latest score per area — green is strong, red is weak.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-700">
                                <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide sticky left-0 bg-white dark:bg-slate-800">Target</th>
                                @foreach ($heatmap['domains'] as $d)
                                    <th class="px-3 py-2.5 text-center text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide whitespace-nowrap">{{ $d['code'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach ($heatmap['rows'] as $row)
                                <tr>
                                    <td class="px-5 py-3 text-slate-700 dark:text-slate-200 sticky left-0 bg-white dark:bg-slate-800 whitespace-nowrap">{{ $row['target'] }}</td>
                                    @foreach ($heatmap['domains'] as $d)
                                        @php $v = $row['cells'][$d['code']] ?? null; @endphp
                                        <td class="px-3 py-3 text-center">
                                            @if ($v !== null)
                                                @php $bg = $v >= 70 ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-800 dark:text-emerald-300' : ($v >= 45 ? 'bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-300' : 'bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-300'); @endphp
                                                <span class="inline-flex items-center justify-center min-w-[2.25rem] px-2 py-0.5 rounded text-xs font-bold tabular-nums {{ $bg }}">{{ $v }}</span>
                                            @else
                                                <span class="text-xs text-slate-300 dark:text-slate-600">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Portfolio trend --}}
        @if (count($trend) >= 2)
            <div class="mb-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Program trajectory</h2>
                <p class="text-xs text-slate-400 dark:text-slate-500 mb-3">Average score of all assessments completed each month.</p>
                @include('exports.charts.trend-line', ['points' => $trend])
            </div>
        @endif

        {{-- Coverage --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Coverage by target type</h2>
                @forelse ($coverage['by_type'] as $type => $count)
                    <div class="flex items-center justify-between py-1.5 text-sm border-b border-slate-50 dark:border-slate-700/50 last:border-0">
                        <span class="text-slate-600 dark:text-slate-300">{{ ucwords(strtolower(str_replace('_', ' ', $type))) }}</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200 tabular-nums">{{ $count }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">No targets yet.</p>
                @endforelse
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Coverage by country</h2>
                @forelse ($coverage['by_country'] as $country => $count)
                    <div class="flex items-center justify-between py-1.5 text-sm border-b border-slate-50 dark:border-slate-700/50 last:border-0">
                        <span class="text-slate-600 dark:text-slate-300">{{ $country }}</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200 tabular-nums">{{ $count }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">No countries recorded yet.</p>
                @endforelse
            </div>
        </div>
    @endif

</x-app-layout>
