<x-app-layout :title="'Benchmark'">

    <div class="mb-6">
        <p class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide">Benchmark</p>
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight mt-0.5">Facility comparison</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            How your facilities compare with each other, using each one's latest completed assessment. Only your own workspace is shown.
        </p>
    </div>

    @if (empty($facilities))
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 px-5 py-12 text-center">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Nothing to compare yet</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500 max-w-sm mx-auto">Complete an assessment on at least one facility to see it here. Comparison gets more useful with more facilities.</p>
        </div>
    @else
        {{-- Facility league table --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden mb-5">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Facilities</h2>
                @if ($workspaceAverage !== null)
                    <span class="text-xs text-slate-400 dark:text-slate-500">Workspace average: <span class="font-bold text-slate-700 dark:text-slate-200">{{ number_format($workspaceAverage, 1) }}</span></span>
                @endif
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-700">
                        <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Rank</th>
                        <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Facility</th>
                        <th class="px-5 py-2.5 text-right text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Score</th>
                        <th class="px-5 py-2.5 text-right text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">vs Average</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($facilities as $f)
                        <tr>
                            <td class="px-5 py-3 text-xs text-slate-400 dark:text-slate-500 tabular-nums">{{ $f['rank'] }}</td>
                            <td class="px-5 py-3">
                                <a href="{{ route('projects.show', $f['project_id']) }}" class="font-medium text-slate-800 dark:text-slate-200 hover:text-vytte-700 dark:hover:text-vytte-400">{{ $f['project_name'] }}</a>
                            </td>
                            <td class="px-5 py-3 text-right font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($f['score'], 1) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums font-semibold {{ $f['vs_average'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ $f['vs_average'] >= 0 ? '+' : '' }}{{ number_format($f['vs_average'], 1) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Where the workspace is collectively strong / weak --}}
        @if (! empty($domainComparison))
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Across all facilities, by area</h2>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Average score per domain — where you are collectively strong and weak.</p>
                </div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach ($domainComparison as $dc)
                            @php $avg = $dc['average']; $col = $avg >= 70 ? '#15803D' : ($avg >= 45 ? '#B45309' : '#B91C1C'); @endphp
                            <tr>
                                <td class="px-5 py-3 font-medium text-slate-800 dark:text-slate-200">{{ $dc['domain_name'] }}</td>
                                <td class="px-5 py-3">
                                    <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                        <div class="h-full rounded-full" style="width: {{ min(100, $avg) }}%; background: {{ $col }}"></div>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right font-bold tabular-nums" style="color: {{ $col }}">{{ number_format($avg, 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

</x-app-layout>
