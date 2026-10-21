@php
    $bandColor = ['strong' => '#16a34a', 'fair' => '#d97706', 'weak' => '#dc2626', 'none' => '#94a3b8'];
    $labelColor = [
        'HIGH_NEED_READY' => ['#065f46', '#6ee7b7'],
        'HIGH_NEED_NOT_READY' => ['#78350f', '#fcd34d'],
        'LOWER_NEED_READY' => ['#0c4a6e', '#7dd3fc'],
        'LOWER_NEED_NOT_READY' => ['#1e293b', '#cbd5e1'],
    ];
    $site = $programme['site'];
    $classification = $programme['classification'];
    $outcomes = $programme['outcomes'];
    $tone = $classification ? ($labelColor[$classification['key']] ?? $labelColor['LOWER_NEED_NOT_READY']) : ['#1e293b', '#cbd5e1'];
    $details = array_filter([
        'Area council' => $site['area_council'],
        'State' => $site['state'],
        'Type' => $site['type'],
        'Assessed on' => $site['assessed_on'] ? \Illuminate\Support\Carbon::parse($site['assessed_on'])->format('d M Y') : null,
        'People who answered' => $outcomes ? $outcomes['respondents'] : null,
    ], fn ($value) => $value !== null && $value !== '');
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 40px 42px 54px 42px; }
    body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 10px; line-height: 1.45; }
    h2 { font-size: 13px; margin: 0 0 8px 0; color: #0f2c4a; border-bottom: 2px solid #0f2c4a; padding-bottom: 4px; }
    .break { page-break-before: always; }
    .eyebrow { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; font-weight: bold; }
    .muted { color: #64748b; }
    .small { font-size: 8.5px; }
    .lead { margin-bottom: 8px; color: #475569; }
    table { width: 100%; border-collapse: collapse; }
    td { vertical-align: top; }
    .item { padding: 6px 0; border-top: 1px solid #eef2f7; }
    .bar { height: 7px; background: #e2e8f0; border-radius: 4px; }
    .fill { height: 7px; border-radius: 4px; }
    .panel { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; }
    .cover { background: #0f2c4a; color: #ffffff; border-radius: 10px; padding: 34px 32px 30px 32px; }
    .tile { border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; }
    .big { font-size: 38px; font-weight: bold; line-height: 1; }
    .num { display: inline-block; width: 20px; height: 20px; line-height: 20px; text-align: center; border-radius: 5px; background: #0f2c4a; color: #fff; font-weight: bold; font-size: 10px; }
    .otitle { font-size: 11px; font-weight: bold; color: #0f2c4a; }
    .ohead { margin: 12px 0 2px 0; }
    .outcome { page-break-inside: avoid; margin-bottom: 6px; }
    .pill { background: #f1f5f9; color: #64748b; padding: 2px 7px; border-radius: 8px; font-size: 8px; font-weight: bold; }
    .check { display: inline-block; width: 15px; height: 15px; line-height: 15px; text-align: center; border-radius: 8px; color: #fff; font-size: 9px; font-weight: bold; }
    .footer { position: fixed; bottom: -34px; left: 0; right: 0; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; }
    .pagenum:before { content: counter(page); }
</style>
</head>
<body>

<div class="footer">
    <table><tr>
        <td>{{ $programme['programme'] }} · {{ $site['name'] }}</td>
        <td style="text-align: right;">Page <span class="pagenum"></span></td>
    </tr></table>
</div>

{{-- Cover --}}
<div class="cover">
    <div class="eyebrow" style="color: #93c5fd;">{{ $programme['programme'] }}</div>
    <div style="font-size: 12px; margin-top: 6px; color: #cbd5e1;">Assessment report</div>
    <div style="font-size: 26px; font-weight: bold; line-height: 1.2; margin-top: 18px;">{{ $site['name'] }}</div>
    <table style="margin-top: 22px;">
        @foreach (array_chunk($details, 3, true) as $row)
            <tr>
                @foreach ($row as $label => $value)
                    <td style="padding: 6px 12px 6px 0; width: 33%;">
                        <div class="eyebrow" style="color: #93c5fd; font-size: 7px;">{{ $label }}</div>
                        <div style="font-size: 11px; margin-top: 2px;">{{ $value }}</div>
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>
</div>

@if ($classification)
    <table style="margin-top: 16px;"><tr>
        <td style="background: {{ $tone[1] }}; color: {{ $tone[0] }}; border-radius: 8px; padding: 14px 18px;">
            <div class="eyebrow">Result</div>
            <div style="font-size: 20px; font-weight: bold; margin-top: 2px;">{{ $classification['label'] }}</div>
            <div style="margin-top: 4px; font-size: 10.5px;">{{ $classification['detail'] }}</div>
        </td>
    </tr></table>
@endif

<table style="margin-top: 14px; border-spacing: 12px 0; border-collapse: separate; margin-left: -12px; width: 104%;"><tr>
    @foreach (['provision', 'readiness'] as $role)
        @php $block = $programme[$role]; $pct = $block['score'] !== null ? round($block['score']) : 0; @endphp
        <td style="width: 50%;">
            <div class="tile">
                <div class="muted small" style="font-weight: bold;">{{ $block['label'] }}</div>
                <div class="big" style="margin-top: 6px; color: {{ $bandColor[$block['band']] }};">{{ $block['score'] !== null ? $pct : '—' }}<span style="font-size: 12px; color: #94a3b8; font-weight: normal;"> / 100</span></div>
                <div class="bar" style="margin-top: 10px;"><div class="fill" style="width: {{ $pct }}%; background: {{ $bandColor[$block['band']] }};"></div></div>
                <div class="muted small" style="margin-top: 6px;">
                    @if ($role === 'provision')
                        Higher means girls' needs are better met today.
                    @else
                        Needs {{ round($programme['thresholds']['readiness_ready_at_or_above']) }} or more and every must-have in place.
                    @endif
                </div>
            </div>
        </td>
    @endforeach
</tr></table>

{{-- In short, must-haves, next steps --}}
<div class="break"></div>
<h2>In short</h2>
@foreach ($programme['summary'] as $line)
    <div style="padding: 3px 0 3px 12px; position: relative;"><span style="color: #1d6fa5; font-weight: bold;">•</span> {{ $line }}</div>
@endforeach

<h2 style="margin-top: 20px;">Must-haves for a Dignity Bank</h2>
<div class="lead small">A Dignity Bank cannot start until each of these is in place.</div>
<table>
    @foreach ($programme['must_haves'] as $item)
        <tr>
            <td class="item" style="width: 24px;">
                <span class="check" style="background: {{ $item['missing'] ? '#dc2626' : '#16a34a' }};">{{ $item['missing'] ? 'x' : '✓' }}</span>
            </td>
            <td class="item">
                @if ($item['missing'])
                    <strong style="color: #b91c1c;">Missing.</strong> {{ $item['label'] }}
                @else
                    <strong style="color: #15803d;">In place.</strong> <span class="muted">{{ $item['ok'] }}</span>
                @endif
            </td>
        </tr>
    @endforeach
</table>

<h2 style="margin-top: 20px;">What to do next</h2>
<table>
    @foreach ($programme['next_steps'] as $i => $step)
        <tr>
            <td class="item" style="width: 24px;"><span class="num">{{ $i + 1 }}</span></td>
            <td class="item">{{ $step }}</td>
        </tr>
    @endforeach
</table>

{{-- Findings --}}
<div class="break"></div>
<h2>How each area scores</h2>
<table>
    @foreach ($programme['sections'] as $section)
        <tr>
            <td class="item" style="width: 46%;">{{ $section['name'] }}<div class="muted small">{{ $section['role'] === 'READINESS' ? 'Readiness' : 'Needs met' }}</div></td>
            <td class="item" style="width: 44%;"><div class="bar" style="margin-top: 6px;"><div class="fill" style="width: {{ $section['score'] !== null ? round($section['score']) : 0 }}%; background: {{ $bandColor[$section['band']] }};"></div></div></td>
            <td class="item" style="width: 10%; text-align: right; font-weight: bold; font-size: 13px; color: {{ $bandColor[$section['band']] }};">{{ $section['score'] !== null ? round($section['score']) : '—' }}</td>
        </tr>
    @endforeach
</table>

<table style="margin-top: 18px; border-collapse: separate; border-spacing: 14px 0; margin-left: -14px; width: 106%;"><tr>
    <td style="width: 50%;">
        <h2>Biggest gaps</h2>
        @forelse ($programme['gaps'] as $gap)
            <div class="item">{{ $gap['gap'] }}</div>
        @empty
            <div class="muted">No major gaps were found.</div>
        @endforelse
    </td>
    <td style="width: 50%;">
        <h2>To get ready to host a Dignity Bank</h2>
        @forelse ($programme['readiness_gaps'] as $gap)
            <div class="item">{{ $gap['gap'] }}</div>
        @empty
            <div class="muted">Nothing further is needed to get ready.</div>
        @endforelse
    </td>
</tr></table>

@if ($programme['strengths'])
    <h2 style="margin-top: 18px;">Working well</h2>
    @foreach ($programme['strengths'] as $strength)
        <div class="item"><span style="color: #16a34a;">✓</span> {{ $strength['good'] }}</div>
    @endforeach
@endif

{{-- What girls and women reported --}}
@if ($outcomes)
    <div class="break"></div>
    @include('exports.partials.programme-outcomes', ['outcomes' => $outcomes])
@endif

{{-- About and how to read --}}
@if ($programme['profile'])
    <h2 style="margin-top: 22px;">About this {{ $programme['noun'] }}</h2>
    @foreach ($programme['profile'] as $line)
        <div class="item">{{ $line }}</div>
    @endforeach
@endif

<h2 style="margin-top: 22px;">How to read this report</h2>
<div class="muted small">
    Each area is scored from 0 to 100, and the two headline scores are the plain average of their areas. A {{ $programme['noun'] }}
    is labelled ready only when its readiness score is {{ round($programme['thresholds']['readiness_ready_at_or_above']) }} or more and no must-have is missing.
    Girls' needs count as high when the needs-met score is below {{ round($programme['thresholds']['provision_good_at_or_above']) }}.
    When several people answered, scores are averaged, each finding reflects the most common answer, and a must-have counts as missing when at least half of them reported it.
    @if ($outcomes)
        The figures about girls and women are the share of people who gave each answer. Anyone who chose not to answer a question is left out of that figure. Answers are anonymous.
    @endif
</div>

</body>
</html>
