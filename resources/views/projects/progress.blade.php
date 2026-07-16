<x-app-layout :title="'Progress · ' . $project->name">

    {{-- Back + header --}}
    <div class="mb-6">
        <a href="{{ route('projects.show', $project) }}"
           class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors mb-2">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 010 1.06L8.06 10l3.72 3.72a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd"/>
            </svg>
            {{ $project->name }}
        </a>
        <p class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide">Progress</p>
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight mt-0.5">{{ $project->name }}</h1>
    </div>

    @if ($assessments->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 px-5 py-12 flex flex-col items-center text-center">
            <div class="w-10 h-10 rounded-xl bg-vytte-50 dark:bg-vytte-900/30 flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-vytte-500 dark:text-vytte-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No completed assessments yet</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500 max-w-xs">Submit at least one assessment to start tracking progress for this project.</p>
            <a href="{{ route('assessments.create', $project) }}"
               class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 bg-vytte-700 text-white text-sm font-semibold rounded-lg hover:bg-vytte-800 transition-colors">
                Start Assessment
            </a>
        </div>
    @else

        {{-- Assessment runs table --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Assessment Runs</h2>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">{{ $assessments->count() }} completed {{ Str::plural('assessment', $assessments->count()) }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-700">
                            <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">#</th>
                            <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Date</th>
                            <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Module</th>
                            <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Maturity Level</th>
                            <th class="px-5 py-2.5 text-right text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Score</th>
                            <th class="px-5 py-2.5 text-right text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Band</th>
                            <th class="px-5 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach ($assessments as $i => $a)
                            @php
                                $aScore = $a->score?->overall_score !== null ? (float) $a->score->overall_score : null;
                                $aMaturity = $a->score?->maturityLevel;
                                $aModule = $a->moduleScope->first()?->module;
                            @endphp
                            <tr>
                                <td class="px-5 py-3 text-xs text-slate-400 dark:text-slate-500 tabular-nums">{{ $i + 1 }}</td>
                                <td class="px-5 py-3 text-slate-700 dark:text-slate-200 whitespace-nowrap">
                                    {{ $a->completed_at?->format('d M Y') ?? '—' }}
                                </td>
                                <td class="px-5 py-3 text-slate-700 dark:text-slate-200">
                                    {{ $aModule?->module_name ?? '—' }}
                                </td>
                                <td class="px-5 py-3">
                                    @if ($aMaturity)
                                        <span class="inline-flex items-center gap-1 text-xs text-slate-700 dark:text-slate-300">
                                            <span class="font-bold text-vytte-700 dark:text-vytte-400">L{{ $aMaturity->level_number }}</span>
                                            {{ $aMaturity->level_name }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400 dark:text-slate-500">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right font-bold tabular-nums text-slate-900 dark:text-white">
                                    {{ $aScore !== null ? number_format($aScore, 1) : '—' }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <x-score-pill :score="$aScore" />
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('assessments.results', $a) }}"
                                       class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:text-vytte-900 dark:hover:text-vytte-200 transition-colors whitespace-nowrap">
                                        View →
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Domain scores matrix (when ≥ 2 assessments with domain scores exist) --}}
        @if ($assessments->count() >= 2 && $domainScoresByAssessment->isNotEmpty())
            <div class="mt-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Domain Score History</h2>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Score per domain across all runs</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-700">
                                <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide sticky left-0 bg-white dark:bg-slate-800">Domain</th>
                                @foreach ($assessments as $i => $a)
                                    <th class="px-4 py-2.5 text-center text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide whitespace-nowrap">
                                        Run {{ $i + 1 }}<br>
                                        <span class="font-normal normal-case text-slate-400 dark:text-slate-500">{{ $a->completed_at?->format('d M Y') ?? '—' }}</span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach ($allDomains as $domain)
                                <tr>
                                    <td class="px-5 py-3 sticky left-0 bg-white dark:bg-slate-800">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded flex-shrink-0">{{ $domain->domain_code }}</span>
                                            <span class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $domain->domain_name }}</span>
                                        </div>
                                    </td>
                                    @foreach ($assessments as $a)
                                        @php
                                            $assessmentDomains = $domainScoresByAssessment->get($a->assessment_id, collect());
                                            $domainRow = $assessmentDomains->firstWhere('domain_id', $domain->domain_id);
                                            $ds = $domainRow && $domainRow->score !== null ? (float) $domainRow->score : null;
                                        @endphp
                                        <td class="px-4 py-3 text-center">
                                            @if ($ds !== null)
                                                @php
                                                    $dsColor = $ds >= 70 ? '#15803D' : ($ds >= 45 ? '#B45309' : '#B91C1C');
                                                    $dsBg = $ds >= 70 ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-800 dark:text-emerald-300' : ($ds >= 45 ? 'bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-300' : 'bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-300');
                                                @endphp
                                                <span class="inline-flex items-center justify-center min-w-[2.5rem] px-2 py-0.5 rounded text-xs font-bold tabular-nums {{ $dsBg }}">
                                                    {{ number_format($ds, 0) }}
                                                </span>
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

        {{-- Compare section (when ≥ 2 completed assessments) --}}
        @if ($assessments->count() >= 2)
            <div class="mt-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Compare Two Runs</h2>
                <p class="text-xs text-slate-400 dark:text-slate-500 mb-4">Select any two assessments to see a side-by-side domain comparison.</p>
                <form method="GET" action="{{ route('projects.compare', $project) }}"
                      class="flex flex-col sm:flex-row items-start sm:items-end gap-3">
                    <div class="flex-1 min-w-0">
                        <label for="compare_a" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">First assessment (baseline)</label>
                        <select name="a" id="compare_a"
                                class="w-full text-sm border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-vytte-400">
                            @foreach ($assessments as $i => $a)
                                <option value="{{ $a->assessment_id }}">
                                    Run {{ $i + 1 }} — {{ $a->completed_at?->format('d M Y') }}
                                    @if ($a->score?->overall_score !== null)
                                        ({{ number_format((float) $a->score->overall_score, 1) }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-shrink-0 text-sm font-semibold text-slate-400 dark:text-slate-500 pb-2 hidden sm:block">vs</div>
                    <div class="flex-1 min-w-0">
                        <label for="compare_b" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Second assessment (latest)</label>
                        <select name="b" id="compare_b"
                                class="w-full text-sm border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-vytte-400">
                            @foreach ($assessments->reverse() as $i => $a)
                                <option value="{{ $a->assessment_id }}">
                                    Run {{ $assessments->count() - $i }} — {{ $a->completed_at?->format('d M Y') }}
                                    @if ($a->score?->overall_score !== null)
                                        ({{ number_format((float) $a->score->overall_score, 1) }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit"
                            class="flex-shrink-0 inline-flex items-center gap-2 px-4 py-2 bg-vytte-700 text-white text-sm font-semibold rounded-lg hover:bg-vytte-800 transition-colors focus:outline-none focus:ring-2 focus:ring-vytte-400 focus:ring-offset-1">
                        Compare
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </form>
            </div>
        @endif

    @endif

</x-app-layout>
