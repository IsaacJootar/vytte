<x-app-layout :title="'Results · ' . $assessmentTitle">

    @php
        $target      = $assessment->target;
        $scoreRecord = $assessment->score;
        $overall     = $scoreRecord ? (float) $scoreRecord->overall_score : null;
        $calibStatus = $scoreRecord?->calibration_status ?? 'NOT_CALIBRATED';
        $maturity    = $scoreRecord?->maturityLevel;
    @endphp

    {{-- Print styles --}}
    <style>
        @media print {
            aside, nav, .no-print { display: none !important; }
            body { background: white !important; }
            .print-break-avoid { break-inside: avoid; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>

    {{-- Back + actions --}}
    <div class="mb-6 flex items-start justify-between gap-4 no-print">
        <a href="{{ route('projects.show', $assessment->project_id) }}"
           class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 010 1.06L8.06 10l3.72 3.72a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd"/>
            </svg>
            {{ $assessment->project?->name }}
        </a>
        <div class="flex items-center gap-2 flex-wrap">
            {{-- PDF export --}}
            <a href="{{ route('assessments.export.pdf', $assessment) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
                PDF
            </a>
            {{-- Office exports over the same frozen payload --}}
            <a href="{{ route('assessments.export.word', $assessment) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                Word
            </a>
            <a href="{{ route('assessments.export.excel', $assessment) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                Excel
            </a>
            <a href="{{ route('assessments.export.ppt', $assessment) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                Slides
            </a>
            {{-- Share link --}}
            <form method="POST" action="{{ route('assessments.share', $assessment) }}" class="inline">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M15 8a3 3 0 10-2.977-2.63l-4.94 2.47a3 3 0 100 4.319l4.94 2.47a3 3 0 10.895-1.789l-4.94-2.47a3.027 3.027 0 000-.74l4.94-2.47C13.456 7.68 14.19 8 15 8z"/>
                    </svg>
                    Share
                </button>
            </form>
            {{-- Print --}}
            <button onclick="window.print()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v5a2 2 0 002 2h1v1a1 1 0 001 1h8a1 1 0 001-1v-1h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a1 1 0 00-1-1H6a1 1 0 00-1 1zm2 0h6v3H7V4zm-1 9a1 1 0 000 2h8a1 1 0 000-2H6zm0-1V9h8v3H6z" clip-rule="evenodd"/>
                </svg>
                Print
            </button>
        </div>
    </div>

    {{-- Every live share link for this report, not just the one most recently created. --}}
    @if ($shareLinks->isNotEmpty())
        <div class="no-print mb-5 rounded-xl border border-vytte-200 bg-vytte-50 p-4 dark:border-vytte-800 dark:bg-vytte-900/20">
            <p class="text-sm font-semibold text-vytte-900 dark:text-vytte-300">Shared report links</p>
            <p class="mt-0.5 text-xs text-vytte-700 dark:text-vytte-400">
                Anyone holding one of these can read this report without signing in.
            </p>

            <div class="mt-3 space-y-3">
                @foreach ($shareLinks as $shareLink)
                    <div>
                        <x-share-link
                            :url="route('reports.shared.token', $shareLink->token)"
                            :message="'Here is the Vytte assessment report for '.($target?->name ?? 'our facility').':'"
                            :label="'Link '.$loop->iteration"
                            :hint="$shareLink->expires_at ? 'Expires '.$shareLink->expires_at->diffForHumans() : 'No expiry date'" />

                        <form method="POST" action="{{ route('assessments.share.revoke', [$assessment, $shareLink]) }}"
                              class="mt-1 text-right"
                              onsubmit="return confirm('Revoke this link? Anyone holding it will immediately lose access to the report.')">
                            @csrf @method('DELETE')
                            <button class="text-xs font-medium text-slate-400 transition-colors hover:text-red-600 dark:text-slate-500 dark:hover:text-red-400"
>
                                Revoke this link
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Header --}}
    <div class="mb-1">
        <p class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide">Assessment Results</p>
        <h1 class="text-xl font-bold text-slate-900 dark:text-white mt-0.5">{{ $assessmentTitle }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            {{ $target?->name }}
            @if ($assessment->completed_at)
                · Completed {{ $assessment->completed_at->format('d M Y') }}
            @endif
        </p>
    </div>

    {{-- Overall score hero --}}
    <div class="mt-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 print-break-avoid">
        <div class="flex flex-col sm:flex-row items-center gap-6">
            {{-- Arc meter --}}
            <div class="flex-shrink-0">
                <x-score-arc :score="$overall !== null ? (int) round($overall) : null" :size="160" :stroke="12">
                    <div class="text-center">
                        @if ($overall !== null)
                            <div class="text-3xl font-black" style="color: {{ $overall >= 70 ? '#15803D' : ($overall >= 45 ? '#B45309' : '#B91C1C') }}">
                                {{ number_format($overall, 1) }}
                            </div>
                            <div class="text-[10px] font-bold uppercase tracking-wide mt-0.5"
                                 style="color: {{ $overall >= 70 ? '#15803D' : ($overall >= 45 ? '#B45309' : '#B91C1C') }}">
                                {{ $overall >= 70 ? 'Strong' : ($overall >= 45 ? 'Moderate' : 'Weak') }}
                            </div>
                        @else
                            <div class="text-[11px] font-semibold text-slate-400 dark:text-slate-500 leading-tight text-center px-1">
                                Not yet<br>calibrated
                            </div>
                        @endif
                    </div>
                </x-score-arc>
            </div>

            {{-- Summary --}}
            <div class="flex-1 min-w-0">
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Overall Score</h2>

                @if ($calibStatus === 'NOT_CALIBRATED')
                    <div class="mt-2 flex items-start gap-2 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
                        <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                        </svg>
                        <p class="text-sm text-amber-800 dark:text-amber-300">Not enough responses to produce a score. Ensure all required questions are answered and resubmit.</p>
                    </div>
                @elseif ($calibStatus === 'PARTIAL')
                    <div class="mt-2 flex items-start gap-2 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
                        <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                        </svg>
                        <p class="text-sm text-amber-800 dark:text-amber-300">Partial score — some sub-indices have unanswered questions. Score is based on available data only.</p>
                    </div>
                @else
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Based on {{ $subIndexScores->count() }} sub-index{{ $subIndexScores->count() !== 1 ? 'es' : '' }}.
                    </p>
                @endif

                @if ($maturity)
                    <div class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 bg-slate-100 dark:bg-slate-700 rounded-lg">
                        <span class="text-xs text-slate-500 dark:text-slate-400">Maturity level</span>
                        <span class="text-xs font-bold text-slate-900 dark:text-white">{{ $maturity->level_number }} — {{ $maturity->level_name }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Sub-index breakdown --}}
    @if ($subIndexScores->isNotEmpty())
        <div class="mt-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden print-break-avoid">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Sub-index Breakdown</h2>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach ($subIndexScores as $row)
                    @php
                        $rowScore = $row->score !== null ? (float) $row->score : null;
                        $rowStatus = $row->calibration_status;
                    @endphp
                    <div class="flex items-center gap-4 px-5 py-4 print-break-avoid">
                        {{-- Mini arc --}}
                        <div class="flex-shrink-0">
                            <x-score-arc :score="$rowScore !== null ? (int) round($rowScore) : null" :size="64" :stroke="6">
                                <span class="text-[11px] font-bold"
                                      style="{{ $rowScore !== null ? 'color:' . ($rowScore >= 70 ? '#15803D' : ($rowScore >= 45 ? '#B45309' : '#B91C1C')) : 'color:#94A3B8' }}">
                                    {{ $rowScore !== null ? number_format($rowScore, 0) : '—' }}
                                </span>
                            </x-score-arc>
                        </div>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-bold text-slate-900 dark:text-white bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded">{{ $row->acronym }}</span>
                                <span class="text-sm font-semibold text-slate-800 dark:text-slate-200 truncate">{{ $row->full_name }}</span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500 truncate">{{ $row->domain_name }} ({{ $row->domain_code }})</p>
                            @if ($rowStatus === 'NOT_CALIBRATED')
                                <p class="mt-1 text-xs text-amber-700 dark:text-amber-400 font-medium">Not calibrated — no answers recorded for this sub-index.</p>
                            @elseif ($rowStatus === 'PARTIAL')
                                <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">Partial — score based on available answers only.</p>
                            @endif
                        </div>

                        {{-- Pill --}}
                        <div class="flex-shrink-0">
                            <x-score-pill :score="$rowScore" />
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Domain breakdown --}}
    {{-- Domain profile radar --}}
    @php
        $radarSeries = $domainScores->filter(fn ($r) => $r->score !== null)
            ->map(fn ($r) => ['label' => $r->domain_code, 'value' => (float) $r->score])->values()->all();
    @endphp
    @if (count($radarSeries) >= 3)
        <div class="mt-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 print-break-avoid flex flex-col items-center">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white self-start mb-2">Domain profile</h2>
            <x-viz.radar :series="$radarSeries" />
        </div>
    @endif

    @if ($domainScores->isNotEmpty())
        <div class="mt-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden print-break-avoid">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Domain Breakdown</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-700">
                            <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Domain</th>
                            <th class="px-5 py-2.5 text-right text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Score</th>
                            <th class="px-5 py-2.5 text-right text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach ($domainScores as $row)
                            @php $ds = $row->score !== null ? (float) $row->score : null; @endphp
                            <tr>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded">{{ $row->domain_code }}</span>
                                        <span class="font-medium text-slate-800 dark:text-slate-200">{{ $row->domain_name }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right font-bold text-slate-900 dark:text-white tabular-nums">
                                    {{ $ds !== null ? number_format($ds, 1) : '—' }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <x-score-pill :score="$ds" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Question drill-down: the individual questions behind each domain score. --}}
    @php $drilldown = $domainScores->filter(fn ($r) => ! empty($r->question_breakdown ?? null)); @endphp
    @if ($drilldown->isNotEmpty())
        <div class="mt-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 print-break-avoid">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Question drill-down</h2>
            <div class="flex flex-col gap-2">
                @foreach ($drilldown as $row)
                    <details class="rounded-xl border border-slate-200 dark:border-slate-600">
                        <summary class="flex items-center justify-between gap-2 px-3 py-2 cursor-pointer">
                            <span class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $row->domain_name }}</span>
                            <span class="text-xs text-slate-400 dark:text-slate-500">{{ count($row->question_breakdown) }} questions</span>
                        </summary>
                        <ul class="px-3 pb-3 pt-1 divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach ($row->question_breakdown as $q)
                                @php $qc = $q['score'] >= 70 ? '#15803D' : ($q['score'] >= 45 ? '#B45309' : '#B91C1C'); @endphp
                                <li class="flex items-start gap-3 py-1.5">
                                    <span class="text-xs font-bold tabular-nums flex-shrink-0 w-8 text-right" style="color: {{ $qc }}">{{ number_format($q['score'], 0) }}</span>
                                    <span class="text-xs text-slate-600 dark:text-slate-300">{{ $q['question_text'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Report intelligence: findings, insights and recommendations, read through a lens. --}}
    @php
        $headline = $intelligence['insights']['headline'] ?? null;
        $lead = collect($lensView['lead'] ?? []);
        $recommendations = collect($lensView['recommendations'] ?? []);

        $categoryStyle = fn ($category, $severity) => match ($category) {
            'CRITICAL_FINDING' => ['border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-900/20', 'text-red-600 dark:text-red-400', 'Critical'],
            'WEAKNESS' => $severity === 'HIGH'
                ? ['border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20', 'text-red-500 dark:text-red-400', 'Weak']
                : ['border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20', 'text-amber-500 dark:text-amber-400', 'Needs work'],
            'STRENGTH' => ['border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20', 'text-green-600 dark:text-green-400', 'Strength'],
            'OPPORTUNITY' => ['border-slate-200 bg-slate-50 dark:border-slate-600 dark:bg-slate-700/40', 'text-slate-500 dark:text-slate-400', 'Opportunity'],
            default => ['border-slate-200 bg-slate-50 dark:border-slate-600 dark:bg-slate-700/40', 'text-slate-400 dark:text-slate-500', 'Data gap'],
        };
    @endphp

    {{-- Lens selector — the same result, read for a different audience. --}}
    <div class="mt-5 no-print flex flex-wrap items-center gap-2">
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Read this report as:</span>
        @foreach ($lensOptions as $key => $meta)
            <a href="{{ route('assessments.results', $assessment) }}?lens={{ $key }}"
               class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors {{ $lensView['lens'] === $key ? 'border-vytte-600 bg-vytte-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' }}">
                {{ $meta['name'] }}
            </a>
        @endforeach
    </div>

    <div class="mt-3 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 print-break-avoid">
        <div class="flex items-baseline justify-between gap-3">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ $lensView['lens_name'] }}</h2>
            <span class="text-xs text-slate-400 dark:text-slate-500">{{ $lensView['lens_question'] }}</span>
        </div>

        @if ($headline)
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $headline }}</p>
        @endif

        @php $lensCats = collect($lensView['lens_insights'] ?? [])->pluck('category_name')->unique(); @endphp
        @if ($lensCats->isNotEmpty())
            <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Through this lens: {{ $lensCats->join(' · ') }}</p>
        @endif

        @if ($lead->isNotEmpty())
            <ul class="mt-4 flex flex-col gap-3">
                @foreach ($lead as $finding)
                    @php [$box, $icon, $label] = $categoryStyle($finding['category'], $finding['severity'] ?? null); @endphp
                    <li class="flex items-start gap-3 rounded-xl border p-3 {{ $box }}">
                        <svg class="mt-0.5 h-4 w-4 flex-shrink-0 {{ $icon }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/>
                        </svg>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $label }}</span>
                                @if (! empty($finding['expected_impact']))
                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-vytte-600 dark:text-vytte-400">{{ ucfirst(strtolower($finding['expected_impact'])) }} improvement potential</span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-sm text-slate-700 dark:text-slate-300">{{ $finding['statement'] }}</p>
                            @if (! empty($finding['why']))
                                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ $finding['why'] }}</p>
                            @endif
                            @if (! empty($finding['failed_indicators']))
                                <details class="mt-1.5">
                                    <summary class="text-xs font-medium text-slate-500 dark:text-slate-400 cursor-pointer">{{ count($finding['failed_indicators']) }} failing item{{ count($finding['failed_indicators']) !== 1 ? 's' : '' }}</summary>
                                    <ul class="mt-1 flex flex-col gap-1">
                                        @foreach (array_slice($finding['failed_indicators'], 0, 6) as $ind)
                                            <li class="text-xs text-slate-500 dark:text-slate-400 flex items-start gap-1.5">
                                                <span class="text-red-500 dark:text-red-400 font-bold tabular-nums">{{ number_format($ind['score'], 0) }}</span>
                                                <span>{{ $ind['question_text'] }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                            @if (! empty($finding['consequence']))
                                <p class="mt-1.5 text-xs italic text-red-600/80 dark:text-red-400/80">{{ $finding['consequence'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-3 text-sm text-slate-400 dark:text-slate-500">Nothing stands out under this lens.</p>
        @endif
    </div>

    {{-- Root causes — probable systemic causes inferred from the pattern of findings. --}}
    @php $rootCauses = collect($intelligence['root_causes'] ?? []); $risks = collect($intelligence['risks'] ?? []); @endphp
    @if ($rootCauses->isNotEmpty())
        <div class="mt-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 print-break-avoid">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Likely root causes</h2>
            <p class="text-xs text-slate-400 dark:text-slate-500 mb-3">Inferred from the pattern of findings — a probable cause, not a diagnosis.</p>
            <ul class="flex flex-col gap-3">
                @foreach ($rootCauses as $cause)
                    <li class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-600 dark:bg-slate-700/40">
                        @if (! empty($cause['is_upstream']))
                            <span class="text-[10px] font-bold uppercase tracking-wide text-vytte-600 dark:text-vytte-400">Upstream cause</span>
                        @endif
                        <p class="text-sm text-slate-700 dark:text-slate-300">{{ $cause['statement'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Risks — likelihood × impact, and what happens if nothing changes. --}}
    @if ($risks->isNotEmpty())
        <div class="mt-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 print-break-avoid">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Risks &amp; what happens if nothing changes</h2>
            @if ($risks->count() >= 2)
                <div class="mb-4 overflow-x-auto">
                    <x-viz.risk-matrix :risks="$risks->all()" />
                </div>
            @endif
            <ul class="flex flex-col gap-3">
                @foreach ($risks as $risk)
                    @php
                        $lvl = $risk['level'];
                        $lvlStyle = $lvl === 'HIGH' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' : ($lvl === 'MEDIUM' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300');
                    @endphp
                    <li class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-600 dark:bg-slate-700/40">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full {{ $lvlStyle }}">{{ $lvl }} risk</span>
                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ $risk['subject'] }}</span>
                            <span class="text-[11px] text-slate-400 dark:text-slate-500">{{ ucfirst(strtolower($risk['likelihood'])) }} likelihood · {{ ucfirst(strtolower($risk['impact'])) }} impact</span>
                        </div>
                        @if (! empty($risk['consequence']))
                            <p class="mt-1.5 text-xs text-slate-600 dark:text-slate-400">{{ $risk['consequence'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Insights — findings named as what they mean, using the governed insight categories. --}}
    @php
        $insightItems = collect($intelligence['insights']['items'] ?? []);
        // One row per (category, subject); group by category, ordered negative → neutral → positive.
        $polarityOrder = ['NEGATIVE' => 0, 'NEUTRAL' => 1, 'POSITIVE' => 2];
        $insightGroups = $insightItems
            ->unique(fn ($i) => $i['category_code'].'|'.$i['subject'])
            ->groupBy('category_code')
            ->sortBy(fn ($group, $code) => ($polarityOrder[$group->first()['polarity']] ?? 1).$code);
    @endphp
    @if ($insightGroups->isNotEmpty())
        <div class="mt-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 print-break-avoid">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Insights</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach ($insightGroups as $code => $group)
                    @php
                        $pol = $group->first()['polarity'];
                        $dot = $pol === 'POSITIVE' ? 'bg-green-500' : ($pol === 'NEGATIVE' ? 'bg-red-500' : 'bg-amber-500');
                    @endphp
                    <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-3">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full {{ $dot }}"></span>
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $group->first()['category_name'] }}</span>
                            <span class="text-[10px] text-slate-400 dark:text-slate-500">{{ $group->count() }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $group->pluck('subject')->unique()->join(', ') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Recommendations — each one cites the finding it came from. --}}
    @if ($recommendations->isNotEmpty())
        <div class="mt-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 print-break-avoid">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">What to do next</h2>
                <a href="{{ route('actions.index', $assessment->project_id) }}"
                   class="no-print inline-flex items-center gap-1 text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:text-vytte-900 dark:hover:text-vytte-200 transition-colors flex-shrink-0">
                    Action plan
                    <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd"/>
                    </svg>
                </a>
            </div>
            <ul class="flex flex-col gap-3">
                @foreach ($recommendations as $rec)
                    {{-- The index into the frozen recommendation list, so the action cites the real recommendation. --}}
                    @php $recIndex = collect($intelligence['recommendations'] ?? [])->search($rec, true); @endphp
                    <li class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-600 dark:bg-slate-700/40">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[10px] font-bold uppercase tracking-wide {{ $rec['horizon'] === 'IMMEDIATE' ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400' }}">
                                {{ $rec['horizon'] === 'IMMEDIATE' ? 'Do now' : 'Plan for' }}
                            </span>
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $rec['type'] }}</span>
                            @if (! empty($rec['expected_impact']))
                                <span class="text-[10px] font-semibold uppercase tracking-wide text-vytte-600 dark:text-vytte-400">{{ ucfirst(strtolower($rec['expected_impact'])) }} impact</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $rec['statement'] }}</p>
                        <p class="mt-1.5 text-xs text-slate-400 dark:text-slate-500">
                            Because: {{ $rec['from_finding']['statement'] }}
                        </p>
                        @if ($recIndex !== false)
                            <form method="POST" action="{{ route('actions.store', $assessment) }}" class="no-print mt-2">
                                @csrf
                                <input type="hidden" name="recommendation_index" value="{{ $recIndex }}">
                                <button type="submit"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:text-vytte-900 dark:hover:text-vytte-200 transition-colors">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path d="M10 5a.75.75 0 01.75.75v3.5h3.5a.75.75 0 010 1.5h-3.5v3.5a.75.75 0 01-1.5 0v-3.5h-3.5a.75.75 0 010-1.5h3.5v-3.5A.75.75 0 0110 5z"/>
                                    </svg>
                                    Add to action plan
                                </button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- AI products — purpose-built summaries over the findings above. Optional; the report
         does not depend on them, and none adds a fact the engine did not find. --}}
    @if ($aiAvailable || $narratives->isNotEmpty())
        <div class="mt-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 print-break-avoid">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-4 h-4 text-vytte-600 dark:text-vytte-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10 2a1 1 0 01.94.66l1.3 3.5 3.5 1.3a1 1 0 010 1.88l-3.5 1.3-1.3 3.5a1 1 0 01-1.88 0l-1.3-3.5-3.5-1.3a1 1 0 010-1.88l3.5-1.3 1.3-3.5A1 1 0 0110 2z"/>
                </svg>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">AI summaries</h2>
            </div>
            <p class="text-xs text-slate-400 dark:text-slate-500 mb-3">Purpose-built retellings of the findings above — a summary, never a new assessment.</p>

            <div class="flex flex-col gap-3">
                @foreach ($aiProducts as $key => $meta)
                    @php $existing = $narratives->get($key); @endphp
                    <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $meta['name'] }}</p>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500">{{ $meta['blurb'] }}</p>
                            </div>
                            @if ($aiAvailable)
                                <form method="POST" action="{{ route('assessments.narrative', $assessment) }}" class="no-print flex-shrink-0">
                                    @csrf
                                    <input type="hidden" name="product" value="{{ $key }}">
                                    <button type="submit" class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:text-vytte-900 dark:hover:text-vytte-200">
                                        {{ $existing ? 'Regenerate' : 'Generate' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                        @if ($existing)
                            <div class="mt-2 pt-2 border-t border-slate-100 dark:border-slate-700 text-sm text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-line">{{ $existing->body }}</div>
                            <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500">Written by {{ $existing->model }}.</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <x-methodology-note />

    {{-- Score history (only when ≥ 2 assessments for same module on this project) --}}
    @if ($history->count() >= 2)
        <div class="mt-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden print-break-avoid no-print">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Score History</h2>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">All runs of this module on {{ $assessment->project?->name }}</p>
                </div>
                <a href="{{ route('projects.progress', $assessment->project_id) }}"
                   class="inline-flex items-center gap-1 text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:text-vytte-900 dark:hover:text-vytte-200 transition-colors flex-shrink-0">
                    Full progress
                    <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd"/>
                    </svg>
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-700">
                            <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">#</th>
                            <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Date</th>
                            <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Maturity Level</th>
                            <th class="px-5 py-2.5 text-right text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Score</th>
                            <th class="px-5 py-2.5 text-right text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Band</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach ($history as $i => $h)
                            @php
                                $hs = $h->score?->overall_score !== null ? (float) $h->score->overall_score : null;
                                $hMaturity = $h->score?->maturityLevel;
                            @endphp
                            <tr class="{{ $h->assessment_id === $assessment->assessment_id ? 'bg-vytte-50 dark:bg-vytte-900/20' : '' }}">
                                <td class="px-5 py-3 text-xs text-slate-400 dark:text-slate-500 tabular-nums">{{ $i + 1 }}</td>
                                <td class="px-5 py-3 text-slate-700 dark:text-slate-200">
                                    {{ $h->completed_at?->format('d M Y') ?? '—' }}
                                    @if ($h->assessment_id === $assessment->assessment_id)
                                        <span class="ml-1.5 text-[10px] font-bold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide">Current</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($hMaturity)
                                        <span class="inline-flex items-center gap-1 text-xs text-slate-700 dark:text-slate-300">
                                            <span class="font-bold text-vytte-700 dark:text-vytte-400">L{{ $hMaturity->level_number }}</span>
                                            {{ $hMaturity->level_name }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400 dark:text-slate-500">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right font-bold tabular-nums text-slate-900 dark:text-white">
                                    {{ $hs !== null ? number_format($hs, 1) : '—' }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <x-score-pill :score="$hs" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</x-app-layout>
