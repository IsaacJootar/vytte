<x-app-layout :title="'Results · ' . ($assessment->moduleScope->first()?->module?->module_name ?? 'Assessment')">

    @php
        $module      = $assessment->moduleScope->first()?->module;
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
           class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 transition-colors">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 010 1.06L8.06 10l3.72 3.72a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd"/>
            </svg>
            {{ $assessment->project?->name }}
        </a>
        <button onclick="window.print()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v5a2 2 0 002 2h1v1a1 1 0 001 1h8a1 1 0 001-1v-1h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a1 1 0 00-1-1H6a1 1 0 00-1 1zm2 0h6v3H7V4zm-1 9a1 1 0 000 2h8a1 1 0 000-2H6zm0-1V9h8v3H6z" clip-rule="evenodd"/>
            </svg>
            Print
        </button>
    </div>

    {{-- Header --}}
    <div class="mb-1">
        <p class="text-xs font-semibold text-vytte-700 uppercase tracking-wide">Assessment Results</p>
        <h1 class="text-xl font-bold text-slate-900 mt-0.5">{{ $module?->module_name ?? 'Unknown module' }}</h1>
        <p class="text-sm text-slate-500 mt-0.5">
            {{ $target?->name }}
            @if ($assessment->completed_at)
                · Completed {{ $assessment->completed_at->format('d M Y') }}
            @endif
        </p>
    </div>

    {{-- Overall score hero --}}
    <div class="mt-5 bg-white rounded-2xl border border-slate-200 p-6 print-break-avoid">
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
                            <div class="text-[11px] font-semibold text-slate-400 leading-tight text-center px-1">
                                Not yet<br>calibrated
                            </div>
                        @endif
                    </div>
                </x-score-arc>
            </div>

            {{-- Summary --}}
            <div class="flex-1 min-w-0">
                <h2 class="text-base font-bold text-slate-900">Overall Score</h2>

                @if ($calibStatus === 'NOT_CALIBRATED')
                    <div class="mt-2 flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-xl">
                        <svg class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                        </svg>
                        <p class="text-sm text-amber-800">Not enough responses to produce a score. Ensure all required questions are answered and resubmit.</p>
                    </div>
                @elseif ($calibStatus === 'PARTIAL')
                    <div class="mt-2 flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-xl">
                        <svg class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 000-2 1 1 0 000 2z" clip-rule="evenodd"/>
                        </svg>
                        <p class="text-sm text-amber-800">Partial score — some sub-indices have unanswered questions. Score is based on available data only.</p>
                    </div>
                @else
                    <p class="mt-1 text-sm text-slate-500">
                        Based on {{ $subIndexScores->count() }} sub-index{{ $subIndexScores->count() !== 1 ? 'es' : '' }}.
                    </p>
                @endif

                @if ($maturity)
                    <div class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 bg-slate-100 rounded-lg">
                        <span class="text-xs text-slate-500">Maturity level</span>
                        <span class="text-xs font-bold text-slate-900">{{ $maturity->level_number }} — {{ $maturity->level_name }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Sub-index breakdown --}}
    @if ($subIndexScores->isNotEmpty())
        <div class="mt-5 bg-white rounded-2xl border border-slate-200 overflow-hidden print-break-avoid">
            <div class="px-5 py-3.5 border-b border-slate-100">
                <h2 class="text-sm font-bold text-slate-900">Sub-index Breakdown</h2>
            </div>
            <div class="divide-y divide-slate-100">
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
                                <span class="text-xs font-bold text-slate-900 bg-slate-100 px-1.5 py-0.5 rounded">{{ $row->acronym }}</span>
                                <span class="text-sm font-semibold text-slate-800 truncate">{{ $row->full_name }}</span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-400 truncate">{{ $row->domain_name }} ({{ $row->domain_code }})</p>
                            @if ($rowStatus === 'NOT_CALIBRATED')
                                <p class="mt-1 text-xs text-amber-700 font-medium">Not calibrated — no answers recorded for this sub-index.</p>
                            @elseif ($rowStatus === 'PARTIAL')
                                <p class="mt-1 text-xs text-amber-600">Partial — score based on available answers only.</p>
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
    @if ($domainScores->isNotEmpty())
        <div class="mt-5 bg-white rounded-2xl border border-slate-200 overflow-hidden print-break-avoid">
            <div class="px-5 py-3.5 border-b border-slate-100">
                <h2 class="text-sm font-bold text-slate-900">Domain Breakdown</h2>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 uppercase tracking-wide">Domain</th>
                        <th class="px-5 py-2.5 text-right text-[10px] font-bold text-slate-400 uppercase tracking-wide">Score</th>
                        <th class="px-5 py-2.5 text-right text-[10px] font-bold text-slate-400 uppercase tracking-wide">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($domainScores as $row)
                        @php $ds = $row->score !== null ? (float) $row->score : null; @endphp
                        <tr>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">{{ $row->domain_code }}</span>
                                    <span class="font-medium text-slate-800">{{ $row->domain_name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-right font-bold text-slate-900 tabular-nums">
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
    @endif

    {{-- Findings --}}
    @php
        $findings = $subIndexScores->filter(fn ($row) => $row->score === null || (float) $row->score < 70);
    @endphp
    @if ($findings->isNotEmpty())
        <div class="mt-5 bg-white rounded-2xl border border-slate-200 p-5 print-break-avoid">
            <h2 class="text-sm font-bold text-slate-900 mb-3">Findings</h2>
            <ul class="flex flex-col gap-3">
                @foreach ($findings as $row)
                    @php
                        $fs = $row->score !== null ? (float) $row->score : null;
                        if ($fs === null || $row->calibration_status === 'NOT_CALIBRATED') {
                            $icon  = 'text-slate-400';
                            $bg    = 'bg-slate-50 border-slate-200';
                            $label = 'Uncalibrated';
                            $text  = $row->acronym . ' (' . $row->full_name . ') could not be scored — no responses were recorded for this sub-index. Ensure all questions are answered.';
                        } elseif ($fs < 45) {
                            $icon  = 'text-red-500';
                            $bg    = 'bg-red-50 border-red-200';
                            $label = 'Weak';
                            $text  = $row->acronym . ' (' . $row->full_name . ') scored ' . number_format($fs, 1) . ' — this area needs immediate attention. Review responses and consider targeted interventions.';
                        } else {
                            $icon  = 'text-amber-500';
                            $bg    = 'bg-amber-50 border-amber-200';
                            $label = 'Moderate';
                            $text  = $row->acronym . ' (' . $row->full_name . ') scored ' . number_format($fs, 1) . ' — developing, with room for improvement. Monitor and support this area.';
                        }
                    @endphp
                    <li class="flex items-start gap-3 p-3 rounded-xl border {{ $bg }}">
                        <svg class="w-4 h-4 flex-shrink-0 mt-0.5 {{ $icon }}" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ $label }}</span>
                            <p class="text-sm text-slate-700 mt-0.5">{{ $text }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Score history (only when ≥ 2 assessments for same module on this project) --}}
    @if ($history->count() >= 2)
        <div class="mt-5 bg-white rounded-2xl border border-slate-200 overflow-hidden print-break-avoid">
            <div class="px-5 py-3.5 border-b border-slate-100">
                <h2 class="text-sm font-bold text-slate-900">Score History</h2>
                <p class="text-xs text-slate-400 mt-0.5">All runs of this module on {{ $assessment->project?->name }}</p>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 uppercase tracking-wide">#</th>
                        <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 uppercase tracking-wide">Date</th>
                        <th class="px-5 py-2.5 text-right text-[10px] font-bold text-slate-400 uppercase tracking-wide">Score</th>
                        <th class="px-5 py-2.5 text-right text-[10px] font-bold text-slate-400 uppercase tracking-wide">Band</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($history as $i => $h)
                        @php $hs = $h->score?->overall_score !== null ? (float) $h->score->overall_score : null; @endphp
                        <tr class="{{ $h->assessment_id === $assessment->assessment_id ? 'bg-vytte-50' : '' }}">
                            <td class="px-5 py-3 text-xs text-slate-400 tabular-nums">{{ $i + 1 }}</td>
                            <td class="px-5 py-3 text-slate-700">
                                {{ $h->completed_at?->format('d M Y') ?? '—' }}
                                @if ($h->assessment_id === $assessment->assessment_id)
                                    <span class="ml-1.5 text-[10px] font-bold text-vytte-700 uppercase tracking-wide">Current</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right font-bold tabular-nums text-slate-900">
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
    @endif

</x-app-layout>
