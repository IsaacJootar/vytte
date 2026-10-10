@php
    $bandColor = ['strong' => '#16a34a', 'fair' => '#d97706', 'weak' => '#dc2626', 'none' => '#94a3b8'];
    $labelColor = [
        'HIGH_NEED_READY' => ['#065f46', '#d1fae5'],
        'HIGH_NEED_NOT_READY' => ['#92400e', '#fef3c7'],
        'LOWER_NEED_READY' => ['#075985', '#e0f2fe'],
        'LOWER_NEED_NOT_READY' => ['#334155', '#f1f5f9'],
    ];
    $site = $programme['site'];
    $classification = $programme['classification'];
    $tone = $classification ? ($labelColor[$classification['key']] ?? $labelColor['LOWER_NEED_NOT_READY']) : ['#334155', '#f1f5f9'];
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 34px 38px 40px 38px; }
    body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 10px; }
    h1 { font-size: 20px; margin: 2px 0 2px 0; color: #0f2c4a; }
    h2 { font-size: 11.5px; margin: 14px 0 6px 0; color: #0f2c4a; border-bottom: 1.5px solid #0f2c4a; padding-bottom: 3px; }
    .eyebrow { font-size: 8px; letter-spacing: 1px; text-transform: uppercase; color: #94a3b8; font-weight: bold; }
    .meta { color: #64748b; font-size: 10px; }
    .box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; }
    .tile { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; }
    .big { font-size: 30px; font-weight: bold; }
    .muted { color: #64748b; }
    .bar { height: 6px; background: #e2e8f0; border-radius: 3px; }
    .fill { height: 6px; border-radius: 3px; }
    table { width: 100%; border-collapse: collapse; }
    td { vertical-align: top; }
    .item { padding: 5px 0; border-top: 1px solid #f1f5f9; }
    .item:first-child { border-top: 0; }
    .small { font-size: 9px; }
</style>
</head>
<body>

<div class="eyebrow">{{ $programme['programme'] }}</div>
<h1>{{ $site['name'] }}</h1>
<div class="meta">
    {{ collect([$site['area_council'] ? $site['area_council'].' area council' : null, $site['state'], $site['type'], $site['assessed_on'] ? 'Assessed '.\Illuminate\Support\Carbon::parse($site['assessed_on'])->format('d M Y') : null])->filter()->implode('  ·  ') }}
</div>

@if ($classification)
    <div style="margin-top: 12px; padding: 10px 12px; border-radius: 6px; background: {{ $tone[1] }}; color: {{ $tone[0] }};">
        <span style="font-size: 13px; font-weight: bold;">{{ $classification['label'] }}</span>
        <span style="margin-left: 8px;">{{ $classification['detail'] }}</span>
    </div>
@endif

<table style="margin-top: 12px;">
    <tr>
        @foreach (['provision', 'readiness'] as $i => $role)
            @php $block = $programme[$role]; @endphp
            <td style="width: 50%; {{ $i === 0 ? 'padding-right: 6px;' : 'padding-left: 6px;' }}">
                <div class="tile">
                    <div class="small muted">{{ $block['label'] }}</div>
                    <div class="big" style="color: {{ $bandColor[$block['band']] }};">{{ $block['score'] !== null ? round($block['score']) : '—' }}<span class="muted" style="font-size: 12px; font-weight: normal;"> / 100</span></div>
                    <div class="bar"><div class="fill" style="width: {{ $block['score'] !== null ? round($block['score']) : 0 }}%; background: {{ $bandColor[$block['band']] }};"></div></div>
                </div>
            </td>
        @endforeach
    </tr>
</table>

<h2>Must-have conditions</h2>
@if ($programme['flags'])
    <div class="box" style="background: #fef2f2; border-color: #fecaca; color: #991b1b;">
        <strong>Must fix before a Dignity Bank can start</strong>
        @foreach ($programme['flags'] as $flag)
            <div style="margin-top: 3px;">•  {{ $flag }}</div>
        @endforeach
    </div>
@else
    <div class="box" style="background: #f0fdf4; border-color: #bbf7d0; color: #166534;">No must-have problems found. Every condition needed to host a Dignity Bank is in place or could be put in place.</div>
@endif

<h2>Section scores</h2>
<table>
    @foreach ($programme['sections'] as $section)
        <tr>
            <td style="padding: 4px 0; width: 56%;">{{ $section['name'] }} <span class="muted small">({{ $section['role'] === 'READINESS' ? 'readiness' : 'provision' }})</span></td>
            <td style="padding: 4px 8px; width: 34%;"><div class="bar"><div class="fill" style="width: {{ $section['score'] !== null ? round($section['score']) : 0 }}%; background: {{ $bandColor[$section['band']] }};"></div></div></td>
            <td style="padding: 4px 0; width: 10%; text-align: right; font-weight: bold; color: {{ $bandColor[$section['band']] }};">{{ $section['score'] !== null ? round($section['score']) : '—' }}</td>
        </tr>
    @endforeach
</table>

<h2>Biggest gaps found</h2>
@forelse ($programme['gaps'] as $gap)
    <div class="item">{{ $gap['gap'] }}</div>
@empty
    <div class="muted">No major gaps were found.</div>
@endforelse

<h2>To get ready to host a Dignity Bank</h2>
@forelse ($programme['readiness_gaps'] as $gap)
    <div class="item">{{ $gap['gap'] }}</div>
@empty
    <div class="muted">Nothing further is needed to get ready.</div>
@endforelse

@if ($programme['strengths'])
    <h2>Working well</h2>
    @foreach ($programme['strengths'] as $strength)
        <div class="item"><span style="color: #16a34a;">✓</span> {{ $strength['good'] }}</div>
    @endforeach
@endif

@if ($programme['profile'])
    <h2>About this {{ $programme['noun'] }}</h2>
    @foreach ($programme['profile'] as $line)
        <div class="item">{{ $line }}</div>
    @endforeach
@endif

<p class="muted small" style="margin-top: 16px;">
    Each section is scored from 0 to 100 and the two headline scores are the plain average of their sections. A {{ $programme['noun'] }}
    is labelled ready only when its readiness score is {{ round($programme['thresholds']['readiness_ready_at_or_above']) }} or more and no must-have problem is found.
    Girls' needs count as high when the provision score is below {{ round($programme['thresholds']['provision_good_at_or_above']) }}.
</p>

</body>
</html>
