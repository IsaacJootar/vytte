<x-admin-layout title="Geographic Usage">

    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Geographic Usage</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Aggregate assessment counts by country and region. No workspace or project details are shown.</p>
        </div>
    </div>

    {{-- Summary stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
        <x-stat-card tone="blue" label="Assessments Mapped" :value="number_format($totalAssessments)" />
        <x-stat-card tone="slate" label="Countries" :value="$countryCount" />
        <div class="col-span-2 sm:col-span-1 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-2xl p-4">
            <p class="text-xs text-amber-700 dark:text-amber-400 font-semibold mb-1">Data scope</p>
            <p class="text-xs text-amber-700 dark:text-amber-400 leading-relaxed">Target location only — no workspace, project, or respondent identity is shown or derivable from this view.</p>
        </div>
    </div>

    @if (empty($countries))
        <div class="section-card px-6 py-12 text-center">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No location data yet</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Assessments will appear here once targets have a country set.</p>
        </div>
    @else
        <div class="section-card">

            {{-- Table header --}}
            <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-700 grid grid-cols-[1fr_auto_auto] gap-4 items-center">
                <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Country / Region</span>
                <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide text-right">Assessments</span>
                <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide text-right w-6"></span>
            </div>

            {{-- Country rows --}}
            @foreach ($countries as $countryData)
                @php
                    $barPct = $maxCount > 0 ? round(($countryData['assessment_count'] / $maxCount) * 100) : 0;
                    $hasRegions = ! empty($countryData['regions']);
                    $regionMax = $hasRegions ? max(array_column($countryData['regions'], 'assessment_count')) : 1;
                @endphp
                <div x-data="{ open: false }" class="border-b border-slate-100 dark:border-slate-700 last:border-0">

                    {{-- Country row --}}
                    <div class="px-5 py-3.5 grid grid-cols-[1fr_auto_auto] gap-4 items-center
                        {{ $hasRegions ? 'cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors' : '' }}"
                        @if ($hasRegions) x-on:click="open = !open" @endif>

                        <div class="min-w-0">
                            <div class="flex items-center gap-2 mb-1.5">
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $countryData['country'] }}</span>
                                @if ($hasRegions)
                                    <span class="text-[10px] text-slate-400 dark:text-slate-500">{{ count($countryData['regions']) }} {{ Str::plural('region', count($countryData['regions'])) }}</span>
                                @endif
                            </div>
                            <div class="h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden max-w-xs">
                                <div class="h-full bg-vytte-600 dark:bg-vytte-500 rounded-full" style="width: {{ $barPct }}%"></div>
                            </div>
                        </div>

                        <span class="text-sm font-bold text-slate-700 dark:text-slate-200 tabular-nums text-right">
                            {{ number_format($countryData['assessment_count']) }}
                        </span>

                        <div class="w-6 flex justify-end">
                            @if ($hasRegions)
                                <svg x-bind:class="open ? 'rotate-180' : ''"
                                     class="w-4 h-4 text-slate-400 transition-transform duration-150"
                                     viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 011.06 0L10 11.94l3.72-3.72a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.22 9.28a.75.75 0 010-1.06z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </div>
                    </div>

                    {{-- Region sub-rows --}}
                    @if ($hasRegions)
                        <div x-show="open" x-collapse class="bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-700">
                            @foreach ($countryData['regions'] as $regionData)
                                @php $regionBarPct = round(($regionData['assessment_count'] / $regionMax) * 100); @endphp
                                <div class="px-5 pl-10 py-2.5 grid grid-cols-[1fr_auto_auto] gap-4 items-center border-b border-slate-100 dark:border-slate-700/50 last:border-0">
                                    <div class="min-w-0">
                                        <span class="text-xs font-medium text-slate-700 dark:text-slate-300 block mb-1">{{ $regionData['region'] }}</span>
                                        <div class="h-1 bg-slate-200 dark:bg-slate-600 rounded-full overflow-hidden max-w-[180px]">
                                            <div class="h-full bg-vytte-400 dark:bg-vytte-600 rounded-full" style="width: {{ $regionBarPct }}%"></div>
                                        </div>
                                    </div>
                                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300 tabular-nums text-right">
                                        {{ number_format($regionData['assessment_count']) }}
                                    </span>
                                    <div class="w-6"></div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                </div>
            @endforeach

        </div>

        <p class="mt-3 text-[11px] text-slate-400 dark:text-slate-500 text-right">
            Counts include all assessment statuses (in progress and complete).
        </p>
    @endif

</x-admin-layout>
