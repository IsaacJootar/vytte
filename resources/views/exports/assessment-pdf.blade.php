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
        /* DomPDF does not support flexbox, so the number is centred with line-height (vertical)
           and text-align (horizontal) instead of align/justify, which left it off-centre. */
        .score-circle { width: 70px; height: 70px; border-radius: 50%; text-align: center; line-height: 70px; font-size: 22px; font-weight: 900; }
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
                {{ number_format($overall, 1) }}
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
                <div class="score-maturity">Performance stage: {{ $score['maturity_level']['name'] }}</div>
            @endif
            @if ($calibStatus === 'NOT_CALIBRATED')
                <div class="score-calibration">Not enough responses to calculate a reliable score</div>
            @elseif ($calibStatus === 'PARTIAL')
                <div class="score-calibration">Some sub-indices are uncalibrated — score is partial</div>
            @endif
        </div>
    </div>

    {{-- Visual summary — the same intelligence, at a glance. HTML/CSS charts so they render. --}}
    <div class="section-title">At a Glance</div>
    <div style="margin-bottom: 12px;">
        <p style="font-size: 9px; font-weight: 700; color: #475569; margin-bottom: 4px;">Performance stage</p>
        @include('exports.charts.maturity-ladder', ['level' => $maturityLevel])
    </div>

    @if ($domainScores->where('score', '!=', null)->count() > 0)
        <div style="margin-top: 14px;">
            <p style="font-size: 9px; font-weight: 700; color: #475569; margin-bottom: 4px;">Scores by area</p>
            @include('exports.charts.domain-bars', ['domains' => $domainScores])
        </div>
    @endif

    @php $riskTotal = collect($riskCounts ?? [])->sum(); @endphp
    @if ($riskTotal > 0)
        <div style="margin-top: 14px;">
            <p style="font-size: 9px; font-weight: 700; color: #475569; margin-bottom: 4px;">Risks by level</p>
            @include('exports.charts.risk-strip', ['riskCounts' => $riskCounts])
        </div>
    @endif

    @if ($subIndexScores->where('score', '!=', null)->count() >= 3)
        <div style="margin-top: 14px;">
            <p style="font-size: 9px; font-weight: 700; color: #475569; margin-bottom: 4px;">Balance across sub-indices</p>
            @include('exports.charts.subindex-radar', ['subIndices' => $subIndexScores])
        </div>
    @endif

    @if (count($trendPoints ?? []) >= 2)
        <div style="margin-top: 14px;">
            <p style="font-size: 9px; font-weight: 700; color: #475569; margin-bottom: 4px;">Score over time</p>
            @include('exports.charts.trend-line', ['points' => $trendPoints])
        </div>
    @endif

    @php
        $measurementViews = $report['measurement_views'] ?? [];
    @endphp
    @if (! empty($measurementViews))
        <div class="section-title">Coverage and Interpretation</div>
        <p style="font-size:9px;color:#475569;line-height:1.5;">
            Primary construct: {{ $measurementViews['primary']['construct'] ?? 'Readiness' }}.
            Source items: {{ $measurementViews['primary']['source_items'] ?? '—' }}.
            {{ $measurementViews['primary']['comparison_eligibility']['reason'] ?? '' }}
        </p>
        @foreach ($measurementViews['limitations'] ?? [] as $limitation)
            <p style="font-size:9px;color:#92400e;margin-top:4px;">• {{ $limitation }}</p>
        @endforeach
    @endif

    @if (! empty($measurementViews['issue_register']))
        <div class="section-title">Exact Issues To Track</div>
        <table>
            <thead><tr><th>Issue</th><th>Recorded answer</th><th>Item score</th></tr></thead>
            <tbody>
                @foreach ($measurementViews['issue_register'] as $issue)
                    <tr>
                        <td>{{ $issue['question_text'] }}</td>
                        <td>{{ $issue['recorded_answer'] ?: '—' }}</td>
                        <td>{{ number_format((float) $issue['item_score'], 0) }}/100</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

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

    {{-- What we found + what to do — from the frozen deterministic engine. --}}
    @php
        $intel = $report['intelligence'] ?? null;
        $lead = collect($intel['findings'] ?? [])
            ->whereIn('category', ['CRITICAL_FINDING', 'WEAKNESS', 'STRENGTH']);
        $recs = collect($intel['recommendations'] ?? []);

        // One canonical grouping (InsightService::groupedForDocument) — insights() returns many
        // overlapping views onto the same items, so grouping from anything but 'items' renders
        // the same insight once per bucket it happens to belong to.
        $insightGroups = collect(app(\App\Services\Reporting\InsightService::class)->groupedForDocument($intel['insights'] ?? []));
        $rootCauses = collect($intel['root_causes'] ?? []);
        $risks = collect($intel['risks'] ?? []);
    @endphp

    @if ($lead->isNotEmpty())
        <div class="section-title">What We Found</div>
        <table>
            <tbody>
                @foreach ($lead as $finding)
                    @php
                        $fBand = match ($finding['category']) {
                            'CRITICAL_FINDING' => 'weak',
                            'WEAKNESS' => ($finding['severity'] ?? null) === 'HIGH' ? 'weak' : 'moderate',
                            'STRENGTH' => 'strong',
                            default => 'uncal',
                        };
                    @endphp
                    <tr>
                        <td style="width: 70px;">
                            <span class="pill pill-{{ $fBand }}">
                                {{ $finding['category'] === 'STRENGTH' ? 'Strength' : ($finding['category'] === 'CRITICAL_FINDING' ? 'Critical' : 'Weak') }}
                            </span>
                        </td>
                        <td>
                            <div style="font-weight: 700;">{{ $finding['statement'] }}</div>
                            @if (!empty($finding['why']))
                                <div style="color: #64748b; margin-top: 2px;">Why it matters: {{ $finding['why'] }}</div>
                            @endif
                            @if (!empty($finding['consequence']))
                                <div style="color: #64748b; margin-top: 1px;">If left unaddressed: {{ $finding['consequence'] }}</div>
                            @endif
                            @if (!empty($finding['expected_impact']))
                                <div style="color: #64748b; margin-top: 1px;">Potential to improve: {{ ucfirst(strtolower($finding['expected_impact'])) }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Insights, grouped by governed category. --}}
    @if ($insightGroups->isNotEmpty())
        <div class="section-title">Insights</div>
        @foreach ($insightGroups as $group)
            <div style="font-size: 9px; font-weight: 700; color: #0f172a; margin-top: 8px; margin-bottom: 3px;">{{ $group['name'] }}</div>
            <table>
                <tbody>
                    @foreach ($group['rows'] as $row)
                        <tr>
                            <td style="width: 70px;">
                                <span class="pill pill-{{ ($row['polarity'] ?? '') === 'POSITIVE' ? 'strong' : (($row['polarity'] ?? '') === 'NEGATIVE' ? 'weak' : 'uncal') }}">
                                    {{ ucfirst(strtolower($row['polarity'] ?? 'Note')) }}
                                </span>
                            </td>
                            <td>{{ $row['statement'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endif

    {{-- Likely root causes — the systemic patterns behind the findings. --}}
    @if ($rootCauses->isNotEmpty())
        <div class="section-title">Likely Root Causes</div>
        <table>
            <tbody>
                @foreach ($rootCauses as $cause)
                    <tr>
                        <td>
                            <div style="font-weight: 700;">{{ $cause['statement'] ?? '' }}</div>
                            @php $items = collect($cause['contributing_indicators'] ?? [])->pluck('question_text')->filter(); @endphp
                            @if ($items->isNotEmpty())
                                <div style="color: #64748b; margin-top: 2px;">Contributing items: {{ $items->implode('; ') }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Risks if nothing changes. --}}
    @if ($risks->isNotEmpty())
        <div class="section-title">Risks If Nothing Changes</div>
        <table>
            <tbody>
                @foreach ($risks as $risk)
                    @php
                        $rLevel = strtoupper($risk['level'] ?? '');
                        $rBand = match ($rLevel) { 'HIGH' => 'weak', 'MEDIUM' => 'moderate', 'LOW' => 'strong', default => 'uncal' };
                    @endphp
                    <tr>
                        <td style="width: 70px;">
                            <span class="pill pill-{{ $rBand }}">{{ ucfirst(strtolower($rLevel ?: 'Risk')) }}</span>
                        </td>
                        <td>
                            <div style="font-weight: 700;">{{ $risk['statement'] ?? '' }}</div>
                            @if (!empty($risk['consequence']))
                                <div style="color: #64748b; margin-top: 2px;">{{ $risk['consequence'] }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($recs->isNotEmpty())
        <div class="section-title">What To Do Next</div>
        <table>
            <tbody>
                @foreach ($recs as $rec)
                    <tr>
                        <td style="width: 70px;">
                            <span class="pill pill-{{ $rec['horizon'] === 'IMMEDIATE' ? 'weak' : 'moderate' }}">
                                {{ $rec['horizon'] === 'IMMEDIATE' ? 'Do now' : 'Plan for' }}
                            </span>
                        </td>
                        <td>{{ $rec['statement'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Local questions — workspace-authored context, never part of the published score. --}}
    @if (($customSection ?? null) && ! empty($customScored['questions']) && $customScored['overall'] !== null)
        @php $cs = (float) $customScored['overall']; $csColor = $cs >= 70 ? '#15803D' : ($cs >= 45 ? '#B45309' : '#B91C1C'); @endphp
        <div class="section-title">{{ $customSection->section_title ?: 'Local context' }} — {{ number_format($cs, 1) }} / 100</div>
        <p style="font-size: 8px; color: #64748b; margin-bottom: 6px;">Optional local score from questions the workspace chose to score — not part of the published assessment score.</p>
        <table>
            <tbody>
                @foreach ($customScored['questions'] as $q)
                    <tr>
                        <td>{{ $q['text'] }}</td>
                        <td style="width: 90px; text-align: right; font-weight: bold;">{{ $q['answer'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- WHO-aware methodology label. --}}
    <div style="margin-top: 20px; border: 1px solid #e2e8f0; background: #f8fafc; border-radius: 6px; padding: 10px 12px; font-size: 8px; color: #64748b; line-height: 1.6;">
        <strong style="color: #475569;">About this assessment.</strong>
        Its questions draw on WHO and other public health frameworks. It is not a World Health
        Organization product, and its results are a management guide, not a clinical diagnosis
        or an official accreditation.
    </div>

    {{-- Footer --}}
    <div class="footer">
        <span>Generated by Vytte</span>
        <span>Assessment ID: {{ substr($report['assessment_id'], 0, 8) }}...</span>
    </div>
</div>
</body>
</html>
