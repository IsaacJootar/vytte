<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $assessment->moduleScope->first()?->module?->module_name ?? 'Assessment Report' }} · Vytte</title>
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
                {{ $assessment->moduleScope->first()?->module?->module_name ?? 'Assessment' }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ $assessment->target?->name }}
                @if ($assessment->project)
                    · {{ $assessment->project->name }}
                @endif
                @if ($assessment->completed_at)
                    · Completed {{ $assessment->completed_at->format('d M Y') }}
                @endif
            </p>
        </div>

        {{-- Score hero --}}
        @php
            $score = $assessment->score;
            $overall = $score ? (float) $score->overall_score : null;
            $calibStatus = $score?->calibration_status ?? 'NOT_CALIBRATED';
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
                @if ($score?->maturityLevel)
                    <p class="text-sm text-slate-500 mt-0.5">Maturity: {{ $score->maturityLevel->level_name }}</p>
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

        {{-- Footer --}}
        <div class="text-center text-xs text-slate-400 mt-8 no-print">
            <p>Shared via <strong class="text-slate-600">Vytte</strong> · Read-only · This link expires in 30 days</p>
        </div>

    </div>

</body>
</html>
