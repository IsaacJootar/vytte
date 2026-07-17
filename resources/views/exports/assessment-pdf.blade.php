<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Assessment Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; background: white; }
        .page { padding: 36px 40px; }
        .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-35deg); font-size: 48px; font-weight: 900; color: rgba(148,163,184,0.18); white-space: nowrap; pointer-events: none; z-index: 1000; letter-spacing: 2px; }

        /* Header */
        .header { border-bottom: 2px solid #0369a1; padding-bottom: 14px; margin-bottom: 20px; }
        .header-top { display: flex; justify-content: space-between; align-items: flex-start; }
        .brand { font-size: 16px; font-weight: 800; color: #0369a1; letter-spacing: -0.5px; }
        .report-label { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-top: 2px; }
        .header-meta { text-align: right; font-size: 9px; color: #64748b; line-height: 1.6; }
        .module-title { font-size: 15px; font-weight: 800; color: #0f172a; margin-top: 10px; }
        .target-name { font-size: 10px; color: #64748b; margin-top: 3px; }

        /* Score hero */
        .score-hero { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px 24px; margin-bottom: 20px; display: flex; align-items: center; gap: 24px; }
        .score-circle { width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 900; flex-shrink: 0; }
        .score-meta { flex: 1; }
        .score-band-label { font-size: 11px; font-weight: 700; margin-bottom: 4px; }
        .score-maturity { font-size: 9px; color: #64748b; margin-top: 2px; }
        .score-calibration { font-size: 9px; color: #b45309; background: #fef3c7; border: 1px solid #fde68a; padding: 4px 8px; border-radius: 4px; margin-top: 6px; display: inline-block; }

        /* Section headers */
        .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #475569; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 10px; margin-top: 20px; }

        /* Domain table */
        table { width: 100%; border-collapse: collapse; font-size: 9px; }
        th { background: #f1f5f9; color: #475569; font-weight: 700; text-align: left; padding: 6px 8px; border-bottom: 1px solid #e2e8f0; font-size: 8px; text-transform: uppercase; letter-spacing: 0.4px; }
        td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }

        /* Score pills inline */
        .pill { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: 700; }
        .pill-strong { background: #dcfce7; color: #15803d; }
        .pill-moderate { background: #fef3c7; color: #b45309; }
        .pill-weak { background: #fee2e2; color: #b91c1c; }
        .pill-uncal { background: #f1f5f9; color: #94a3b8; }

        /* Sub-index table */
        .sub-acronym { font-weight: 700; font-size: 8px; }
        .sub-name { color: #64748b; font-size: 8px; }

        /* Footer */
        .footer { margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 10px; display: flex; justify-content: space-between; font-size: 8px; color: #94a3b8; }
    </style>
</head>
<body>
@if (!empty($showWatermark))
<div class="watermark">Vytte Free Plan</div>
@endif
<div class="page">

    {{-- Header --}}
    <div class="header">
        <div class="header-top">
            <div>
                <div class="brand">Vytte</div>
                <div class="report-label">Assessment Report</div>
            </div>
            <div class="header-meta">
                @if ($report['completed_at'])
                    Completed: {{ \Illuminate\Support\Carbon::parse($report['completed_at'])->format('d M Y') }}<br>
                @endif
                @if ($report['assessor_name'])
                    Assessor: {{ $report['assessor_name'] }}<br>
                @endif
                Finalized: {{ \Illuminate\Support\Carbon::parse($report['report_generated_at'])->format('d M Y') }}
            </div>
        </div>
        <div class="module-title">{{ $report['title'] }}</div>
        <div class="target-name">{{ $report['target']['name'] }} · {{ $report['project']['name'] }}</div>
        @if (count($report['modules']) > 1)
            <div class="target-name">Areas: {{ collect($report['modules'])->pluck('module_name')->join(', ') }}</div>
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
        $bandColor = match($band) {
            'strong' => '#15803d',
            'moderate' => '#b45309',
            'weak' => '#b91c1c',
            default => '#94a3b8',
        };
        $bandBg = match($band) {
            'strong' => '#dcfce7',
            'moderate' => '#fef3c7',
            'weak' => '#fee2e2',
            default => '#f1f5f9',
        };
    @endphp
    <div class="score-hero">
        <div class="score-circle" style="background: {{ $bandBg }}; color: {{ $bandColor }};">
            @if ($overall !== null)
                {{ round($overall) }}
            @else
                —
            @endif
        </div>
        <div class="score-meta">
            <div class="score-band-label" style="color: {{ $bandColor }};">
                @if ($band === 'strong') Strong Performance
                @elseif ($band === 'moderate') Moderate Performance
                @elseif ($band === 'weak') Weak — Needs Improvement
                @else Not Yet Calibrated
                @endif
            </div>
            @if ($score['maturity_level'])
                <div class="score-maturity">Maturity Level: {{ $score['maturity_level']['name'] }}</div>
            @endif
            @if ($calibStatus === 'NOT_CALIBRATED')
                <div class="score-calibration">Not enough responses to calculate a reliable score</div>
            @elseif ($calibStatus === 'PARTIAL')
                <div class="score-calibration">Some sub-indices are uncalibrated — score is partial</div>
            @endif
        </div>
    </div>

    {{-- Domain scores --}}
    @if ($domainScores->isNotEmpty())
        <div class="section-title">Domain Scores</div>
        <table>
            <thead>
                <tr>
                    <th>Domain</th>
                    <th>Score</th>
                    <th>Band</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($domainScores as $ds)
                    @php
                        $dsScore = $ds->score !== null ? (float) $ds->score : null;
                        $dsBand = match(true) {
                            $dsScore === null => 'uncal',
                            $dsScore >= 70 => 'strong',
                            $dsScore >= 45 => 'moderate',
                            default => 'weak',
                        };
                    @endphp
                    <tr>
                        <td>{{ $ds->domain_name }}</td>
                        <td>{{ $dsScore !== null ? number_format($dsScore, 1) : '—' }}</td>
                        <td>
                            <span class="pill pill-{{ $dsBand }}">
                                {{ ucfirst($dsBand === 'uncal' ? 'Uncalibrated' : $dsBand) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Sub-index scores --}}
    @if ($subIndexScores->isNotEmpty())
        <div class="section-title">Sub-Index Scores</div>
        <table>
            <thead>
                <tr>
                    <th>Sub-Index</th>
                    <th>Domain</th>
                    <th>Score</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($subIndexScores as $sis)
                    @php
                        $sisScore = $sis->score !== null ? (float) $sis->score : null;
                        $sisBand = match(true) {
                            $sisScore === null => 'uncal',
                            $sisScore >= 70 => 'strong',
                            $sisScore >= 45 => 'moderate',
                            default => 'weak',
                        };
                    @endphp
                    <tr>
                        <td>
                            <span class="sub-acronym">{{ $sis->acronym }}</span>
                            <div class="sub-name">{{ $sis->full_name }}</div>
                        </td>
                        <td>{{ $sis->domain_name }}</td>
                        <td>{{ $sisScore !== null ? number_format($sisScore, 1) : '—' }}</td>
                        <td>
                            <span class="pill pill-{{ $sisBand }}">
                                @if ($sisBand === 'uncal') Uncalibrated
                                @else {{ ucfirst($sisBand) }}
                                @endif
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Footer --}}
    <div class="footer">
        <span>Generated by Vytte · vytte.com</span>
        <span>Assessment ID: {{ substr($report['assessment_id'], 0, 8) }}...</span>
    </div>
</div>
</body>
</html>
