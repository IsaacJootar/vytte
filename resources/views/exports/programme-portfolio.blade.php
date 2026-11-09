@php
    $bandColor = ['strong' => '#16a34a', 'fair' => '#d97706', 'weak' => '#dc2626', 'none' => '#94a3b8'];
    $labelColor = [
        'HIGH_NEED_READY' => ['#065f46', '#d1fae5'],
        'HIGH_NEED_NOT_READY' => ['#78350f', '#fef3c7'],
        'LOWER_NEED_READY' => ['#0c4a6e', '#e0f2fe'],
        'LOWER_NEED_NOT_READY' => ['#1e293b', '#e2e8f0'],
    ];
    $band = fn ($score) => $score === null ? 'none' : ($score >= 70 ? 'strong' : ($score >= 45 ? 'fair' : 'weak'));
    $rows = collect($portfolio['rows']);
    $counts = $portfolio['counts'];
    $survey = $portfolio['survey'];
    $minimum = 10;
    $placeCount = $portfolio['assessed'];
    $typeLabel = fn ($type) => $type === 'school' ? 'school' : $type;
    $byType = collect($portfolio['by_site_type']);
    $typeText = $byType->map(fn ($t) => $t['sites'].' '.($t['name'] === 'school' ? ($t['sites'] === 1 ? 'school' : 'schools') : ($t['sites'] === 1 ? $t['name'] : $t['name'].'s')))->implode(' and ');
    $areas = collect($portfolio['by_area_council'])->pluck('name')->reject(fn ($n) => $n === 'Area council not set')->values();
    $thin = $rows->filter(fn ($r) => $r['respondents'] < $minimum)->values();
    $startHere = $rows->filter(fn ($r) => ($r['classification']['key'] ?? null) === 'HIGH_NEED_READY')->values();
    $prepare = $rows->filter(fn ($r) => ($r['classification']['key'] ?? null) === 'HIGH_NEED_NOT_READY')->values();
    $later = $rows->filter(fn ($r) => in_array($r['classification']['key'] ?? null, ['LOWER_NEED_READY', 'LOWER_NEED_NOT_READY'], true))->values();
    $worst = collect($survey['indicators'] ?? [])->filter(fn ($i) => $i['value'] !== null && ($i['kind'] ?? '') !== 'KNOWLEDGE')
        ->map(fn ($i) => $i + ['concern' => $i['direction'] === 'DOWN' ? $i['value'] : 100 - $i['value']])
        ->sortByDesc('concern')->take(3)->values();
    $date = \Illuminate\Support\Carbon::now()->format('d F Y');
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
    td, th { vertical-align: top; }
    th { background: #0f2c4a; color: #fff; font-size: 8.5px; text-align: left; padding: 5px 6px; }
    td.c { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; }
    .cover { background: #0f2c4a; color: #ffffff; border-radius: 10px; padding: 34px 32px 30px 32px; }
    .tile { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; text-align: center; }
    .big { font-size: 30px; font-weight: bold; line-height: 1; }
    .pill { padding: 2px 7px; border-radius: 8px; font-size: 8px; font-weight: bold; }
    .item { padding: 5px 0 5px 12px; border-top: 1px solid #eef2f7; }
    .box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 11px; margin-bottom: 7px; }
    .footer { position: fixed; bottom: -34px; left: 0; right: 0; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; }
    .pagenum:before { content: counter(page); }
</style>
</head>
<body>

<div class="footer">
    <table><tr>
        <td>{{ $portfolio['programme'] }} · Portfolio report</td>
        <td style="text-align: right;">Page <span class="pagenum"></span></td>
    </tr></table>
</div>

{{-- Cover --}}
<div class="cover">
    <div class="eyebrow" style="color: #93c5fd;">{{ $portfolio['programme'] }}</div>
    <div style="font-size: 12px; margin-top: 6px; color: #cbd5e1;">Portfolio report</div>
    <div style="font-size: 26px; font-weight: bold; line-height: 1.2; margin-top: 18px;">All places, read together</div>
    <div style="margin-top: 16px; font-size: 11px; color: #e2e8f0;">
        {{ $placeCount }} {{ $placeCount === 1 ? 'place' : 'places' }} assessed ({{ $typeText }})
        @if ($areas->isNotEmpty()) across {{ $areas->count() }} area {{ $areas->count() === 1 ? 'council' : 'councils' }}@endif.
        @if ($survey) {{ $survey['respondents'] }} {{ $survey['respondents'] === 1 ? 'person' : 'people' }} answered.@endif
    </div>
    <div style="margin-top: 6px; font-size: 9px; color: #93c5fd;">Prepared {{ $date }}</div>
</div>

{{-- The result in numbers --}}
<table style="margin-top: 14px; border-collapse: separate; border-spacing: 8px 0; margin-left: -8px; width: 103%;"><tr>
    @foreach ([['HIGH_NEED_READY', $portfolio['labels']['HIGH_NEED_READY'], 'High need, ready now'], ['HIGH_NEED_NOT_READY', $portfolio['labels']['HIGH_NEED_NOT_READY'], 'High need, must-haves missing'], ['LATER', 'Later', 'Lower need']] as [$key, $label, $note])
        @php
            $n = $key === 'LATER' ? $counts['LOWER_NEED_READY'] + $counts['LOWER_NEED_NOT_READY'] : $counts[$key];
            $tone = $labelColor[$key === 'LATER' ? 'LOWER_NEED_READY' : $key];
        @endphp
        <td style="width: 33%;">
            <div class="tile" style="background: {{ $tone[1] }}; border-color: {{ $tone[1] }};">
                <div class="big" style="color: {{ $tone[0] }};">{{ $n }}</div>
                <div style="font-weight: bold; margin-top: 4px; color: {{ $tone[0] }}; font-size: 11px;">{{ $label }}</div>
                <div class="small" style="color: {{ $tone[0] }};">{{ $note }}</div>
            </div>
        </td>
    @endforeach
</tr></table>

<h2 style="margin-top: 20px;">In short</h2>
@php
    $lines = [];
    $lines[] = $placeCount.' '.($placeCount === 1 ? 'place was' : 'places were').' assessed. '.($counts['HIGH_NEED_READY'] + $counts['HIGH_NEED_NOT_READY']).' of them have a high need among girls and women, and '.$counts['HIGH_NEED_READY'].' of those are ready to host a Dignity Bank now.';
    if ($counts['HIGH_NEED_NOT_READY'] > 0) { $lines[] = $counts['HIGH_NEED_NOT_READY'].' '.($counts['HIGH_NEED_NOT_READY'] === 1 ? 'place has' : 'places have').' a high need but '.($counts['HIGH_NEED_NOT_READY'] === 1 ? 'is' : 'are').' not ready yet: at least one must-have is missing.'; }
    if ($portfolio['common_gap_actions']) { $top = $portfolio['common_gap_actions'][0]; $lines[] = 'The most common gap, found at '.$top['sites'].' of '.$placeCount.' places: '.lcfirst(rtrim($top['finding'], '.')).'.'; }
    foreach ($worst as $row) { $lines[] = ($row['direction'] === 'DOWN' ? '' : 'Only ').round($row['value']).'% of the girls and women '.lcfirst($row['label']).'.'; }
    if ($thin->isNotEmpty()) { $lines[] = $thin->count().' of the '.$placeCount.' places had fewer than '.$minimum.' people answering, so their own results are a rough guide.'; }
@endphp
@foreach ($lines as $line)
    <div style="padding: 3px 0 3px 12px;"><span style="color: #1d6fa5; font-weight: bold;">•</span> {{ $line }}</div>
@endforeach

{{-- Place by place --}}
<div class="break"></div>
<h2>Place by place</h2>
<div class="lead small">Listed with the places to start at first. "Needs met" is how well girls' needs are met today (higher is better). "Ready" is readiness to host a Dignity Bank.</div>
<table>
    <tr>
        <th style="width: 24%;">Place</th><th style="width: 8%;">Type</th><th style="width: 14%;">Area council</th>
        <th style="width: 8%;">Needs met</th><th style="width: 7%;">Ready</th><th style="width: 15%;">Result</th><th style="width: 16%;">Must-haves missing</th><th style="width: 8%;">People</th>
    </tr>
    @foreach ($rows as $row)
        @php $tone = $labelColor[$row['classification']['key'] ?? 'LOWER_NEED_NOT_READY'] ?? $labelColor['LOWER_NEED_NOT_READY']; @endphp
        <tr>
            <td class="c"><strong>{{ $row['name'] }}</strong></td>
            <td class="c">{{ ucfirst($row['type']) }}</td>
            <td class="c">{{ $row['area_council'] === 'Area council not set' ? '—' : $row['area_council'] }}</td>
            <td class="c" style="font-weight: bold; color: {{ $bandColor[$row['provision_band']] }};">{{ $row['provision'] !== null ? round($row['provision']) : '—' }}</td>
            <td class="c" style="font-weight: bold; color: {{ $bandColor[$row['readiness_band']] }};">{{ $row['readiness'] !== null ? round($row['readiness']) : '—' }}</td>
            <td class="c"><span class="pill" style="background: {{ $tone[1] }}; color: {{ $tone[0] }};">{{ $row['classification']['label'] ?? '—' }}</span></td>
            <td class="c">{{ $row['flag_count'] ? $row['flag_count'] : 'None' }}</td>
            <td class="c" style="{{ $row['respondents'] < $minimum ? 'color: #b45309; font-weight: bold;' : '' }}">{{ $row['respondents'] }}</td>
        </tr>
    @endforeach
</table>
<div class="muted small" style="margin-top: 5px;">A number in amber under "People" means fewer than {{ $minimum }} people answered at that place.</div>

{{-- Groups --}}
<table style="margin-top: 18px; border-collapse: separate; border-spacing: 14px 0; margin-left: -14px; width: 106%;"><tr>
    <td style="width: 50%;">
        <h2>By area council</h2>
        <table>
            <tr><th>Area council</th><th>Places</th><th>Start here</th><th>Needs met</th></tr>
            @foreach ($portfolio['by_area_council'] as $a)
                <tr><td class="c">{{ $a['name'] === 'Area council not set' ? 'Not recorded' : $a['name'] }}</td><td class="c">{{ $a['sites'] }}</td><td class="c">{{ $a['start_here'] }}</td><td class="c">{{ $a['provision'] ?? '—' }}</td></tr>
            @endforeach
        </table>
    </td>
    <td style="width: 50%;">
        <h2>PHCs and schools</h2>
        <table>
            <tr><th>Type</th><th>Places</th><th>Start here</th><th>Needs met</th><th>Ready</th></tr>
            @foreach ($portfolio['by_site_type'] as $t)
                <tr><td class="c">{{ ucfirst($t['name']) }}</td><td class="c">{{ $t['sites'] }}</td><td class="c">{{ $t['start_here'] }}</td><td class="c">{{ $t['provision'] ?? '—' }}</td><td class="c">{{ $t['readiness'] ?? '—' }}</td></tr>
            @endforeach
        </table>
        @php $schools = $byType->firstWhere('name', 'school'); @endphp
        @if (! $schools || $schools['sites'] < 5)
            <div class="muted small" style="margin-top: 5px;">Only {{ $schools['sites'] ?? 0 }} {{ ($schools['sites'] ?? 0) === 1 ? 'school' : 'schools' }} took part, so what this says about schools is thin. It is strong for PHCs.</div>
        @endif
    </td>
</tr></table>

{{-- Common gaps and what to do --}}
@if ($portfolio['common_gap_actions'])
    <div class="break"></div>
    <h2>The gaps found most often, and what answers them</h2>
    <div class="lead small">Each of these is a gap found at several places, so it is worth solving once for the whole programme rather than place by place.</div>
    <table>
        <tr><th style="width: 40%;">What was found</th><th style="width: 12%;">Places</th><th>What to do</th></tr>
        @foreach ($portfolio['common_gap_actions'] as $gap)
            <tr>
                <td class="c">{{ $gap['finding'] }}</td>
                <td class="c"><strong>{{ $gap['sites'] }}</strong> of {{ $placeCount }}</td>
                <td class="c">{{ $gap['action'] ?? '—' }}</td>
            </tr>
        @endforeach
    </table>
@endif

{{-- What girls and women told us --}}
@if ($survey)
    <h2 style="margin-top: 22px;">What girls and women told us</h2>
    <div class="lead small">{{ $survey['respondents'] }} {{ $survey['respondents'] === 1 ? 'answer' : 'answers' }} from {{ $survey['schools'] }} {{ $survey['schools'] === 1 ? 'place' : 'places' }}, added together. Each place counts once. Answers are anonymous.</div>
    @foreach (collect($survey['indicators'])->groupBy('outcome') as $outcome => $indicators)
        <div style="margin: 10px 0 3px 0; font-weight: bold; color: #0f2c4a; font-size: 10.5px;">{{ $outcome }}</div>
        <table>
            @foreach ($indicators as $i)
                <tr>
                    <td class="c" style="width: 66%;">{{ $i['label'] }}<span class="muted small"> {{ ($i['kind'] ?? '') === 'KNOWLEDGE' ? '(average score)' : ($i['direction'] === 'DOWN' ? '(lower is better)' : '(higher is better)') }}</span></td>
                    <td class="c" style="width: 14%; font-weight: bold; font-size: 11px;">{{ $i['value'] !== null ? round($i['value']).($i['kind'] === 'KNOWLEDGE' ? '' : '%') : '—' }}</td>
                    <td class="c muted" style="width: 20%;">{{ $i['n'] }} answers</td>
                </tr>
            @endforeach
        </table>
    @endforeach
@endif

{{-- What to do next, for the whole programme --}}
<div class="break"></div>
<h2>What the programme should do next</h2>
<div class="box">
    @if ($startHere->isNotEmpty())
        <strong>1. Start Dignity Banks at the {{ $startHere->count() }} "Start here" {{ $startHere->count() === 1 ? 'place' : 'places' }}.</strong>
        <div class="muted" style="margin-top: 3px;">{{ $startHere->pluck('name')->implode(', ') }}.</div>
        <div style="margin-top: 3px;">{{ $startHere->count() === 1 ? 'This has' : 'These have' }} a high need and every must-have in place. Confirm the person who will run it and the secure place for stock at each, then begin.</div>
    @else
        <strong>1. No place is both high need and ready yet.</strong>
        <div style="margin-top: 3px;">Begin with the places that are closest to ready, as listed in step 2.</div>
    @endif
</div>
<div class="box">
    <strong>2. Prepare the {{ $prepare->count() }} "Prepare first" {{ $prepare->count() === 1 ? 'place' : 'places' }} before starting.</strong>
    @forelse ($prepare as $row)
        <div style="margin-top: 3px;"><strong>{{ $row['name'] }}:</strong> {{ implode(' ', $row['flags']) }}</div>
    @empty
        <div class="muted" style="margin-top: 3px;">None: every place with a high need is ready.</div>
    @endforelse
</div>
<div class="box">
    <strong>3. Solve the common gaps together.</strong>
    @foreach (array_slice($portfolio['common_gap_actions'], 0, 5) as $gap)
        <div style="margin-top: 3px;">– {{ $gap['action'] }} <span class="muted">({{ $gap['sites'] }} of {{ $placeCount }} places)</span></div>
    @endforeach
</div>
<div class="box">
    <strong>4. Leave the {{ $later->count() }} lower-need {{ $later->count() === 1 ? 'place' : 'places' }} for later.</strong>
    <div style="margin-top: 3px;">Needs are lower {{ $later->count() === 1 ? 'here' : 'at these places' }}. Share what is already working with the others, and return once the higher-need places have started.</div>
</div>
<div class="box">
    <strong>5. Fill the gaps in the evidence.</strong>
    @if ($thin->isNotEmpty())
        <div style="margin-top: 3px;">{{ $thin->count() }} {{ $thin->count() === 1 ? 'place' : 'places' }} had fewer than {{ $minimum }} people answering: {{ $thin->pluck('name')->implode(', ') }}. Collect more answers there before relying on their girls' survey figures.</div>
    @endif
    @if ($portfolio['in_progress'] > 0)
        <div style="margin-top: 3px;">{{ $portfolio['in_progress'] }} more {{ $portfolio['in_progress'] === 1 ? 'place was' : 'places were' }} set up but {{ $portfolio['in_progress'] === 1 ? 'has' : 'have' }} no completed report yet, so {{ $portfolio['in_progress'] === 1 ? 'it is' : 'they are' }} not part of this picture.</div>
    @endif
    <div style="margin-top: 3px;">Repeat the assessment after 12 months to measure what has changed.</div>
</div>

{{-- How the portfolio was put together --}}
<h2 style="margin-top: 22px;">How this portfolio was put together</h2>
<div class="muted small">
    This report adds together the final reports already produced for each place; no number here is calculated differently from the place's own report.
    A place appears once. Scores are out of 100. High need means the needs-met score is below 60. A place is ready only when its readiness score is 60 or more and no must-have is missing.
</div>
@if (! empty($portfolio['merged']))
    <div style="margin-top: 8px;"><strong>Counted once.</strong>
        @foreach ($portfolio['merged'] as $m)
            <div class="small">{{ $m['name'] }} was assessed more than once. The assessment answered by the most people ({{ $m['kept_respondents'] }}) is used; the other ({{ implode(', ', $m['other_respondents']) }} {{ count($m['other_respondents']) === 1 ? 'person' : 'people' }}) is left out so the place is not counted twice.</div>
        @endforeach
    </div>
@endif
@if (! empty($portfolio['left_out']))
    <div style="margin-top: 8px;"><strong>Not ranked.</strong>
        @foreach ($portfolio['left_out'] as $l)
            <div class="small">{{ $l['name'] }} ({{ $l['respondents'] }} {{ $l['respondents'] === 1 ? 'person' : 'people' }} answered): {{ $l['reason'] }}</div>
        @endforeach
    </div>
@endif

</body>
</html>
