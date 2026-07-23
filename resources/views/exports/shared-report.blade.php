<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report['title'] }} · Vytte</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body class="bg-slate-100 font-sans antialiased min-h-screen">

    {{-- Read-only banner --}}
    <div class="no-print bg-vytte-700 text-white px-4 py-2.5 text-center text-xs font-semibold">
        This is a read-only shared report. Link expires in 30 days.
        <button onclick="window.print()" class="ml-4 underline underline-offset-2 hover:no-underline">Print</button>
    </div>

    <div class="max-w-3xl mx-auto px-4 py-8">

        {{-- Branding --}}
        <div class="flex items-center gap-2 mb-6 no-print">
            <div class="w-7 h-7 rounded-lg bg-vytte-700 flex items-center justify-center flex-shrink-0">
                <x-vytte-mark class="w-3.5 h-3.5" />
            </div>
            <span class="text-sm font-bold text-slate-900">Vytte</span>
        </div>

        {{-- Header --}}
        <div class="mb-5">
            <p class="text-xs font-semibold text-vytte-700 uppercase tracking-widest mb-0.5">Assessment Report</p>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">
                {{ $report['title'] }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ $report['target']['name'] }}
                @if ($report['project']['name'])
                    · {{ $report['project']['name'] }}
                @endif
                @if ($report['completed_at'])
                    · Completed {{ \Illuminate\Support\Carbon::parse($report['completed_at'])->format('d M Y') }}
                @endif
            </p>
            @if (count($report['modules']) > 1)
                <p class="mt-2 text-xs text-slate-500">Areas: {{ collect($report['modules'])->pluck('module_name')->join(', ') }}</p>
            @endif
        </div>

        {{-- Score hero --}}
        @php
            $score = $report['score'];
            $overall = $score['overall_score'] !== null ? (float) $score['overall_score'] : null;
            $calibStatus = $score['calibration_status'];
            $band = match(true) {
                $overall === null => 'uncal',
                $overall >= 70 => 'strong',
                $overall >= 45 => 'moderate',
                default => 'weak',
            };
            $bandColor = match($band) { 'strong' => '#15803D', 'moderate' => '#B45309', 'weak' => '#B91C1C', default => '#94A3B8' };
            $bandLabel = match($band) { 'strong' => 'Strong', 'moderate' => 'Moderate', 'weak' => 'Weak', default => 'Uncalibrated' };
        @endphp

        <div class="bg-white rounded-2xl border border-slate-200 p-6 mb-5 flex items-center gap-6">
            <div class="w-20 h-20 rounded-full flex items-center justify-center text-2xl font-black flex-shrink-0"
                 style="background: {{ $band === 'strong' ? '#F0FDF4' : ($band === 'moderate' ? '#FFFBEB' : ($band === 'weak' ? '#FEF2F2' : '#F8FAFC')) }}; color: {{ $bandColor }}">
                @if ($overall !== null){{ round($overall) }}@else —@endif
            </div>
            <div>
                <div class="text-lg font-black" style="color: {{ $bandColor }}">{{ $bandLabel }}</div>
                @if ($score['maturity_level'])
                    <p class="text-sm text-slate-500 mt-0.5">Maturity: {{ $score['maturity_level']['name'] }}</p>
                @endif
                @if ($calibStatus === 'NOT_CALIBRATED')
                    <p class="mt-2 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-1.5">
                        Not enough responses to calculate a reliable score.
                    </p>
                @elseif ($calibStatus === 'PARTIAL')
                    <p class="mt-2 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-1.5">
                        Some sub-indices are uncalibrated — this score is partial.
                    </p>
                @endif
            </div>
        </div>

        @if (($report['respondent_collection']['is_multi_respondent'] ?? false) === true)
            <div class="bg-white rounded-2xl border border-slate-200 px-5 py-4 mb-5">
                <p class="text-xs font-semibold text-vytte-700 uppercase tracking-wider">Respondent aggregate</p>
                <p class="mt-1 text-sm text-slate-600">
                    Based on {{ $report['respondent_collection']['eligible_completed_respondents'] }} eligible completed respondents
                    using the arithmetic mean. Individual identities and responses are not included in this shared report.
                </p>
            </div>
        @endif

        {{-- Domain scores --}}
        @if ($domainScores->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden mb-5">
                <div class="px-5 py-3.5 border-b border-slate-100">
                    <h2 class="text-sm font-bold text-slate-900">Domain Scores</h2>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50">
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-slate-500">Domain</th>
                            <th class="text-right px-5 py-2.5 text-xs font-semibold text-slate-500">Score</th>
                            <th class="text-right px-5 py-2.5 text-xs font-semibold text-slate-500">Band</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($domainScores as $ds)
                            @php
                                $dsScore = $ds->score !== null ? (float) $ds->score : null;
                                $dsBand = match(true) { $dsScore === null => 'uncal', $dsScore >= 70 => 'strong', $dsScore >= 45 => 'moderate', default => 'weak' };
                                $dsBandColor = match($dsBand) { 'strong' => '#15803D', 'moderate' => '#B45309', 'weak' => '#B91C1C', default => '#94A3B8' };
                            @endphp
                            <tr>
                                <td class="px-5 py-3 font-medium text-slate-800">{{ $ds->domain_name }}</td>
                                <td class="px-5 py-3 text-right font-bold tabular-nums" style="color: {{ $dsBandColor }}">
                                    {{ $dsScore !== null ? number_format($dsScore, 1) : '—' }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <x-score-pill :score="$dsScore" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Sub-index scores --}}
        @if ($subIndexScores->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden mb-5">
                <div class="px-5 py-3.5 border-b border-slate-100">
                    <h2 class="text-sm font-bold text-slate-900">Sub-Index Scores</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach ($subIndexScores as $sis)
                        @php
                            $sisScore = $sis->score !== null ? (float) $sis->score : null;
                            $sisBand = match(true) { $sisScore === null => 'uncal', $sisScore >= 70 => 'strong', $sisScore >= 45 => 'moderate', default => 'weak' };
                            $sisColor = match($sisBand) { 'strong' => '#15803D', 'moderate' => '#B45309', 'weak' => '#B91C1C', default => '#94A3B8' };
                        @endphp
                        <div class="flex items-center gap-3 px-5 py-3">
                            <div class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                                <span class="text-[10px] font-black text-slate-600">{{ $sis->acronym }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-800 truncate">{{ $sis->full_name }}</p>
                                <p class="text-xs text-slate-400">{{ $sis->domain_name }}</p>
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <p class="text-sm font-bold tabular-nums" style="color: {{ $sisColor }}">
                                    {{ $sisScore !== null ? number_format($sisScore, 1) : '—' }}
                                </p>
                                @if ($sis->calibration_status === 'NOT_CALIBRATED')
                                    <p class="text-[10px] text-slate-400">Uncalibrated</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- What we found + what to do — from the frozen deterministic engine. --}}
        @php
            $intel = $report['intelligence'] ?? null;
            $lead = collect($intel['findings'] ?? [])
                ->whereIn('category', ['CRITICAL_FINDING', 'WEAKNESS', 'STRENGTH'])
                ->take(6);
            $recs = collect($intel['recommendations'] ?? []);
        @endphp

        @if ($lead->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden mb-5">
                <div class="px-5 py-3.5 border-b border-slate-100">
                    <h2 class="text-sm font-bold text-slate-900">What we found</h2>
                </div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($lead as $finding)
                        @php
                            $dot = match ($finding['category']) {
                                'CRITICAL_FINDING' => '#B91C1C',
                                'WEAKNESS' => ($finding['severity'] ?? null) === 'HIGH' ? '#B91C1C' : '#B45309',
                                'STRENGTH' => '#15803D',
                                default => '#94A3B8',
                            };
                        @endphp
                        <li class="flex items-start gap-3 px-5 py-3">
                            <span class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full" style="background: {{ $dot }}"></span>
                            <p class="text-sm text-slate-700">{{ $finding['statement'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($recs->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden mb-5">
                <div class="px-5 py-3.5 border-b border-slate-100">
                    <h2 class="text-sm font-bold text-slate-900">What to do next</h2>
                </div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($recs as $rec)
                        <li class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-bold uppercase tracking-wide" style="color: {{ $rec['horizon'] === 'IMMEDIATE' ? '#B91C1C' : '#B45309' }}">
                                    {{ $rec['horizon'] === 'IMMEDIATE' ? 'Do now' : 'Plan for' }}
                                </span>
                                <span class="text-xs font-semibold text-slate-500">{{ $rec['type'] }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-700">{{ $rec['statement'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- WHO-aware methodology label. --}}
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-xs leading-relaxed text-slate-500 mb-5">
            <span class="font-semibold text-slate-600">About this assessment.</span>
            Its questions draw on WHO and other public health frameworks. It is not a World
            Health Organization product, and its results are a management guide, not a
            clinical diagnosis or an official accreditation.
        </div>

        {{-- Footer --}}
        <div class="text-center text-xs text-slate-400 mt-8 no-print">
            <p>Shared via <strong class="text-slate-600">Vytte</strong> · Read-only · This link expires in 30 days</p>
        </div>

    </div>

</body>
</html>
