@php
    $labelTone = [
        'HIGH_NEED_READY' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
        'HIGH_NEED_NOT_READY' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        'LOWER_NEED_READY' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
        'LOWER_NEED_NOT_READY' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
    ];
    $bandText = ['strong' => 'text-emerald-700 dark:text-emerald-300', 'fair' => 'text-amber-700 dark:text-amber-300', 'weak' => 'text-red-700 dark:text-red-300', 'none' => 'text-slate-400'];
@endphp
<x-app-layout :title="'Programme overview · ' . $programme">

    <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Programme overview</p>
            <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $programme }}</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $assessed }} {{ $assessed === 1 ? 'school or PHC' : 'schools and PHCs' }} assessed{{ $in_progress > 0 ? ', '.$in_progress.' still in progress' : '' }}</p>
        </div>
        <a href="{{ route('portfolio.index') }}"
           class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
            Back to portfolio
        </a>
    </div>

    @if ($assessed === 0)
        <div class="rounded-2xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-800">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">No completed assessments yet</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">The overview fills in as each school or PHC finishes its assessment.</p>
        </div>
    @else
        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Schools and PHCs assessed</p>
                <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $assessed }}</p>
            </div>
            <div class="rounded-2xl p-4 {{ $labelTone['HIGH_NEED_READY'] }}">
                <p class="text-xs font-semibold">{{ $labels['HIGH_NEED_READY'] }}</p>
                <p class="mt-1 text-3xl font-bold tabular-nums">{{ $counts['HIGH_NEED_READY'] }}</p>
            </div>
            <div class="rounded-2xl p-4 {{ $labelTone['HIGH_NEED_NOT_READY'] }}">
                <p class="text-xs font-semibold">{{ $labels['HIGH_NEED_NOT_READY'] }}</p>
                <p class="mt-1 text-3xl font-bold tabular-nums">{{ $counts['HIGH_NEED_NOT_READY'] }}</p>
            </div>
            <div class="rounded-2xl p-4 {{ $labelTone['LOWER_NEED_NOT_READY'] }}">
                <p class="text-xs font-semibold">{{ $labels['LOWER_NEED_READY'] }} or {{ strtolower($labels['LOWER_NEED_NOT_READY']) }}</p>
                <p class="mt-1 text-3xl font-bold tabular-nums">{{ $counts['LOWER_NEED_READY'] + $counts['LOWER_NEED_NOT_READY'] }}</p>
            </div>
        </div>

        <div class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
            <div class="border-b border-slate-100 px-5 py-3 dark:border-slate-700">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Where to start</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Schools and PHCs in the order to act: highest need and ready first.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="text-left text-[11px] uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-2 font-semibold">School or PHC</th>
                            <th class="px-3 py-2 font-semibold">Area council</th>
                            <th class="px-3 py-2 text-right font-semibold">Provision</th>
                            <th class="px-3 py-2 text-right font-semibold">Readiness</th>
                            <th class="px-3 py-2 font-semibold">Label</th>
                            <th class="px-3 py-2 text-right font-semibold">Must-haves missing</th>
                            <th class="px-5 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-5 py-3 font-medium text-slate-900 dark:text-white">{{ $row['name'] }}<span class="ml-2 text-xs font-normal text-slate-400">{{ $row['type'] }}</span></td>
                                <td class="px-3 py-3 text-slate-600 dark:text-slate-300">{{ $row['area_council'] }}</td>
                                <td class="px-3 py-3 text-right font-bold tabular-nums {{ $bandText[$row['provision_band']] }}">{{ $row['provision'] !== null ? round($row['provision']) : '—' }}</td>
                                <td class="px-3 py-3 text-right font-bold tabular-nums {{ $bandText[$row['readiness_band']] }}">{{ $row['readiness'] !== null ? round($row['readiness']) : '—' }}</td>
                                <td class="px-3 py-3">
                                    @if ($row['classification'])
                                        <span class="rounded-md px-2 py-0.5 text-xs font-semibold {{ $labelTone[$row['classification']['key']] ?? '' }}">{{ $row['classification']['label'] }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right tabular-nums text-slate-600 dark:text-slate-300">{{ $row['flag_count'] }}</td>
                                <td class="px-5 py-3 text-right"><a href="{{ route('assessments.results', $row['assessment']) }}" class="text-xs font-semibold text-vytte-700 hover:text-vytte-800">View report</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ([['By area council', $by_area_council, 'Area council'], ['By type', $by_site_type, 'Type']] as [$title, $groups, $heading])
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
                    <div class="border-b border-slate-100 px-5 py-3 dark:border-slate-700"><h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ $title }}</h2></div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[420px] text-sm">
                            <thead class="text-left text-[11px] uppercase tracking-wide text-slate-400">
                                <tr>
                                    <th class="px-5 py-2 font-semibold">{{ $heading }}</th>
                                    <th class="px-2 py-2 text-right font-semibold">Count</th>
                                    <th class="px-2 py-2 text-right font-semibold">Start</th>
                                    <th class="px-2 py-2 text-right font-semibold">Prepare</th>
                                    <th class="px-5 py-2 text-right font-semibold">Avg. prov. / ready</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                @foreach ($groups as $group)
                                    <tr>
                                        <td class="px-5 py-2.5 font-medium capitalize text-slate-900 dark:text-white">{{ $group['name'] }}</td>
                                        <td class="px-2 py-2.5 text-right tabular-nums">{{ $group['sites'] }}</td>
                                        <td class="px-2 py-2.5 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ $group['start_here'] }}</td>
                                        <td class="px-2 py-2.5 text-right tabular-nums text-amber-700 dark:text-amber-300">{{ $group['prepare_first'] }}</td>
                                        <td class="px-5 py-2.5 text-right tabular-nums text-slate-600 dark:text-slate-300">{{ $group['provision'] ?? '—' }} / {{ $group['readiness'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($common_gaps)
            <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Most common problems across schools and PHCs</h2>
                <ul class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-700">
                    @foreach ($common_gaps as $gap)
                        <li class="flex items-center justify-between gap-4 py-2">
                            <span class="text-slate-700 dark:text-slate-200">{{ $gap['question'] }}</span>
                            <span class="shrink-0 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $gap['sites'] }} of {{ $assessed }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif

    @if ($survey)
        <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">What girls and women told us</h2>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                {{ $survey['respondents'] }} {{ $survey['respondents'] === 1 ? 'answer' : 'answers' }} from {{ $survey['schools'] }} {{ $survey['schools'] === 1 ? 'school or PHC' : 'schools and PHCs' }}, added together.
            </p>
            <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-700">
                @foreach ($survey['indicators'] as $indicator)
                    <li class="flex items-center justify-between gap-4 py-2.5">
                        <div class="min-w-0">
                            <p class="text-sm text-slate-800 dark:text-slate-100">{{ $indicator['label'] }}</p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500">{{ $indicator['direction'] === 'DOWN' ? 'Lower is better' : 'Higher is better' }} · {{ $indicator['n'] }} {{ $indicator['n'] === 1 ? 'answer' : 'answers' }}</p>
                        </div>
                        <p class="shrink-0 text-xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $indicator['value'] !== null ? round($indicator['value']).'%' : '—' }}</p>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4 overflow-hidden rounded-xl border border-slate-100 dark:border-slate-700">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
                        <tr><th class="px-3 py-2 font-semibold">School or PHC</th><th class="px-3 py-2 font-semibold">Area council</th><th class="px-3 py-2 text-right font-semibold">Answers</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach ($survey['rows'] as $row)
                            <tr>
                                <td class="px-3 py-2"><a href="{{ route('assessments.results', $row['assessment']) }}" class="font-medium text-vytte-700 hover:underline dark:text-vytte-400">{{ $row['name'] }}</a></td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $row['area_council'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['respondents'] }}@unless ($row['enough']) <span class="text-xs text-amber-600">(few)</span>@endunless</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-app-layout>
