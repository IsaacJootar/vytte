@php
    $bandColor = ['strong' => '#16a34a', 'fair' => '#d97706', 'weak' => '#dc2626', 'none' => '#94a3b8'];
    // Plain names for the four groups. These describe a place; they are not buttons or instructions.
    $groups = [
        'HIGH_NEED_READY' => ['High need, ready', "Girls' needs are not well met today, and the place has everything needed to set up a Dignity Bank now."],
        'HIGH_NEED_NOT_READY' => ['High need, not ready yet', "Girls' needs are not well met today, but at least one must-have for a Dignity Bank is missing."],
        'LOWER_NEED_READY' => ['Lower need, ready', "Girls' needs are better met today. The place is ready if a Dignity Bank is wanted later."],
        'LOWER_NEED_NOT_READY' => ['Lower need, not ready yet', "Girls' needs are better met today, and the place is not yet ready."],
    ];
    $rows = collect($portfolio['rows']);
    $counts = $portfolio['counts'];
    $survey = $portfolio['survey'];
    $minimum = 10;
    $placeCount = $portfolio['assessed'];
    $siteType = $portfolio['site_type'] ?? null;
    $plural = $siteType === 'school' ? 'schools' : ($siteType === 'PHC' ? 'PHCs' : 'places');
    $singular = $siteType === 'school' ? 'school' : ($siteType === 'PHC' ? 'PHC' : 'place');
    $titleSuffix = $siteType === 'school' ? 'Schools' : ($siteType === 'PHC' ? 'PHCs' : 'All places');
    $byType = collect($portfolio['by_site_type']);
    $areas = collect($portfolio['by_area_council'])->pluck('name')->reject(fn ($n) => $n === 'Area council not set')->values();
    $thin = $rows->filter(fn ($r) => $r['respondents'] < $minimum)->values();
    $inGroup = fn (string $key) => $rows->filter(fn ($r) => ($r['classification']['key'] ?? null) === $key)->values();
    $highNeedReady = $inGroup('HIGH_NEED_READY');
    $highNeedNotReady = $inGroup('HIGH_NEED_NOT_READY');
    $lowerNeed = $rows->filter(fn ($r) => in_array($r['classification']['key'] ?? null, ['LOWER_NEED_READY', 'LOWER_NEED_NOT_READY'], true))->values();
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
    .box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 11px; margin-bottom: 7px; }
    .guide { background: #f1f5f9; border-radius: 6px; padding: 9px 12px; margin-top: 14px; }
    .footer { position: fixed; bottom: -34px; left: 0; right: 0; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; }
    .pagenum:before { content: counter(page); }
</style>
</head>
<body>

<div class="footer">
    <table><tr>
        <td>{{ $portfolio['programme'] }} · Portfolio report · {{ $titleSuffix }}</td>
        <td style="text-align: right;">Page <span class="pagenum"></span></td>
    </tr></table>
</div>

{{-- Cover --}}
<div class="cover">
    <div class="eyebrow" style="color: #93c5fd;">{{ $portfolio['programme'] }}</div>
    <div style="font-size: 12px; margin-top: 6px; color: #cbd5e1;">Portfolio report</div>
    <div style="font-size: 26px; font-weight: bold; line-height: 1.2; margin-top: 18px;">All {{ $plural }}, read together</div>
    <div style="margin-top: 16px; font-size: 11px; color: #e2e8f0;">
        {{ $placeCount }} {{ $placeCount === 1 ? $singular : $plural }} assessed
        @if ($areas->isNotEmpty()) across {{ $areas->count() }} area {{ $areas->count() === 1 ? 'council' : 'councils' }}@endif.
        @if ($survey) {{ $survey['respondents'] }} {{ $survey['respondents'] === 1 ? 'person' : 'people' }} answered.@endif
    </div>
    <div style="margin-top: 6px; font-size: 9px; color: #93c5fd;">Prepared {{ $date }}</div>
</div>

{{-- How to read this report --}}
<div class="guide">
    <strong>How to read this report.</strong>
    Every {{ $singular }} was given two scores out of 100.
    <strong>Needs met</strong> shows how well girls' needs for menstrual products, information and a safe toilet are met today: the higher, the better.
    <strong>Ready</strong> shows whether the {{ $singular }} could host a Dignity Bank (leaders' support, a person to run it, a secure place for stock).
    A {{ $singular }} has a <strong>high need</strong> when its needs-met score is below 60, and is <strong>ready</strong> when its ready score is 60 or more and no must-have is missing.
    That puts each {{ $singular }} into one of four groups, shown below. The groups only describe where a {{ $singular }} stands; there is nothing to click.
</div>

<h2 style="margin-top: 20px;">In short</h2>
@php
    $lines = [];
    $lines[] = $placeCount.' '.($placeCount === 1 ? $singular.' was' : $plural.' were').' assessed. '.($counts['HIGH_NEED_READY'] + $counts['HIGH_NEED_NOT_READY']).' of them have a high need among girls and women, and '.$counts['HIGH_NEED_READY'].' of those '.($counts['HIGH_NEED_READY'] === 1 ? 'is' : 'are').' ready to host a Dignity Bank now.';
    if ($counts['HIGH_NEED_NOT_READY'] > 0) { $lines[] = $counts['HIGH_NEED_NOT_READY'].' '.($counts['HIGH_NEED_NOT_READY'] === 1 ? $singular.' has' : $plural.' have').' a high need but '.($counts['HIGH_NEED_NOT_READY'] === 1 ? 'is' : 'are').' not ready yet: at least one must-have is missing.'; }
    if ($portfolio['common_gap_actions']) { $top = $portfolio['common_gap_actions'][0]; $lines[] = 'The most common gap, found at '.$top['sites'].' of '.$placeCount.' '.($placeCount === 1 ? $singular : $plural).': '.lcfirst(rtrim($top['finding'], '.')).'.'; }
    foreach ($worst as $row) { $lines[] = ($row['direction'] === 'DOWN' ? '' : 'Only ').round($row['value']).'% of the girls and women '.lcfirst($row['label']).'.'; }
    if ($thin->isNotEmpty()) { $lines[] = $thin->count().' of the '.$placeCount.' '.($placeCount === 1 ? $singular : $plural).' had fewer than '.$minimum.' people answering, so '.($thin->count() === 1 ? 'its' : 'their').' own results are a rough guide.'; }
    if ($placeCount < 5) { $lines[] = 'Only '.$placeCount.' '.($placeCount === 1 ? $singular : $plural).' took part, so treat this report as a small sample, not a general picture.'; }
@endphp
@foreach ($lines as $line)
    <div style="padding: 3px 0 3px 12px;"><span style="color: #1d6fa5; font-weight: bold;">•</span> {{ $line }}</div>
@endforeach

{{-- How the places group --}}
<h2 style="margin-top: 20px;">How the {{ $plural }} group</h2>
<table>
    <tr><th style="width: 22%;">Group</th><th style="width: 8%;">Number</th><th style="width: 40%;">What it means</th><th>Which</th></tr>
    @foreach ($groups as $key => [$name, $meaning])
        @php $members = $inGroup($key); @endphp
        <tr>
            <td class="c"><strong>{{ $name }}</strong></td>
            <td class="c"><strong>{{ $counts[$key] }}</strong></td>
            <td class="c muted">{{ $meaning }}</td>
            <td class="c">{{ $members->isEmpty() ? '—' : $members->pluck('name')->implode(', ') }}</td>
        </tr>
    @endforeach
</table>

{{-- Place by place --}}
<div class="break"></div>
<h2>{{ ucfirst($singular) }} by {{ $singular }}</h2>
<div class="lead small">Ordered with the highest need and the most ready first. "Needs met" and "Ready" are scores out of 100.</div>
<table>
    <tr>
        <th style="width: 25%;">{{ ucfirst($singular) }}</th><th style="width: 15%;">Area council</th>
        <th style="width: 9%;">Needs met</th><th style="width: 8%;">Ready</th><th style="width: 22%;">Group</th><th style="width: 12%;">Must-haves missing</th><th style="width: 9%;">People</th>
    </tr>
    @foreach ($rows as $row)
        <tr>
            <td class="c"><strong>{{ $row['name'] }}</strong></td>
            <td class="c">{{ $row['area_council'] === 'Area council not set' ? '—' : $row['area_council'] }}</td>
            <td class="c" style="font-weight: bold; color: {{ $bandColor[$row['provision_band']] }};">{{ $row['provision'] !== null ? round($row['provision']) : '—' }}</td>
            <td class="c" style="font-weight: bold; color: {{ $bandColor[$row['readiness_band']] }};">{{ $row['readiness'] !== null ? round($row['readiness']) : '—' }}</td>
            <td class="c">{{ $groups[$row['classification']['key'] ?? ''][0] ?? '—' }}</td>
            <td class="c">{{ $row['flag_count'] ? $row['flag_count'] : 'None' }}</td>
            <td class="c" style="{{ $row['respondents'] < $minimum ? 'color: #b45309; font-weight: bold;' : '' }}">{{ $row['respondents'] }}</td>
        </tr>
    @endforeach
</table>
<div class="muted small" style="margin-top: 5px;">A number in amber under "People" means fewer than {{ $minimum }} people answered there.</div>

{{-- By area council --}}
<h2 style="margin-top: 18px;">By area council</h2>
<table style="width: 70%;">
    <tr><th>Area council</th><th>{{ ucfirst($plural) }}</th><th>High need, ready</th><th>Average needs met</th></tr>
    @foreach ($portfolio['by_area_council'] as $a)
        <tr><td class="c">{{ $a['name'] === 'Area council not set' ? 'Not recorded' : $a['name'] }}</td><td class="c">{{ $a['sites'] }}</td><td class="c">{{ $a['start_here'] }}</td><td class="c">{{ $a['provision'] ?? '—' }}</td></tr>
    @endforeach
</table>
@if ($byType->count() > 1)
    <h2 style="margin-top: 18px;">PHCs and schools</h2>
    <table style="width: 70%;">
        <tr><th>Type</th><th>Places</th><th>High need, ready</th><th>Average needs met</th><th>Average ready</th></tr>
        @foreach ($portfolio['by_site_type'] as $t)
            <tr><td class="c">{{ ucfirst($t['name']) }}</td><td class="c">{{ $t['sites'] }}</td><td class="c">{{ $t['start_here'] }}</td><td class="c">{{ $t['provision'] ?? '—' }}</td><td class="c">{{ $t['readiness'] ?? '—' }}</td></tr>
        @endforeach
    </table>
@endif

{{-- Common gaps and what to do --}}
@if ($portfolio['common_gap_actions'])
    <div class="break"></div>
    <h2>The gaps found most often, and what answers them</h2>
    <div class="lead small">Each of these is a gap found at several {{ $plural }}, so it is worth solving once for the whole programme rather than one {{ $singular }} at a time.</div>
    <table>
        <tr><th style="width: 40%;">What was found</th><th style="width: 12%;">{{ ucfirst($plural) }}</th><th>What to do</th></tr>
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
    <div class="lead small">{{ $survey['respondents'] }} {{ $survey['respondents'] === 1 ? 'answer' : 'answers' }} from {{ $survey['schools'] }} {{ $survey['schools'] === 1 ? $singular : $plural }}, added together. Each {{ $singular }} counts once. Answers are anonymous.</div>
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
    @if ($highNeedReady->isNotEmpty())
        <strong>1. Set up Dignity Banks first at the {{ $highNeedReady->count() }} high-need {{ $highNeedReady->count() === 1 ? $singular : $plural }} that {{ $highNeedReady->count() === 1 ? 'is' : 'are' }} ready.</strong>
        <div class="muted" style="margin-top: 3px;">{{ $highNeedReady->pluck('name')->implode(', ') }}.</div>
        <div style="margin-top: 3px;">Girls' needs are least met {{ $highNeedReady->count() === 1 ? 'here' : 'at these places' }}, and every must-have is in place. Confirm the person who will run the Dignity Bank and the secure place for stock, then begin.</div>
    @else
        <strong>1. No {{ $singular }} is both high need and ready yet.</strong>
        <div style="margin-top: 3px;">Begin with the {{ $plural }} that are closest to ready, as listed in step 2.</div>
    @endif
</div>
<div class="box">
    <strong>2. Fix what is missing at the {{ $highNeedNotReady->count() }} high-need {{ $highNeedNotReady->count() === 1 ? $singular : $plural }} that {{ $highNeedNotReady->count() === 1 ? 'is' : 'are' }} not ready, then set up.</strong>
    @forelse ($highNeedNotReady as $row)
        <div style="margin-top: 3px;"><strong>{{ $row['name'] }}:</strong> {{ implode(' ', $row['flags']) }}</div>
    @empty
        <div class="muted" style="margin-top: 3px;">None: every high-need {{ $singular }} is ready.</div>
    @endforelse
</div>
<div class="box">
    <strong>3. Solve the common gaps together.</strong>
    @foreach (array_slice($portfolio['common_gap_actions'], 0, 5) as $gap)
        <div style="margin-top: 3px;">– {{ $gap['action'] }} <span class="muted">({{ $gap['sites'] }} of {{ $placeCount }})</span></div>
    @endforeach
</div>
<div class="box">
    <strong>4. The {{ $lowerNeed->count() }} lower-need {{ $lowerNeed->count() === 1 ? $singular : $plural }} can wait.</strong>
    <div style="margin-top: 3px;">Girls' needs are better met {{ $lowerNeed->count() === 1 ? 'here' : 'at these places' }}. Share what is already working with the others, and come back once the higher-need places have started.</div>
</div>
<div class="box">
    <strong>5. Strengthen the evidence.</strong>
    @if ($thin->isNotEmpty())
        <div style="margin-top: 3px;">{{ $thin->count() }} {{ $thin->count() === 1 ? $singular : $plural }} had fewer than {{ $minimum }} people answering: {{ $thin->pluck('name')->implode(', ') }}. Collect more answers there before relying on their girls' survey figures.</div>
    @endif
    @if ($portfolio['in_progress'] > 0)
        <div style="margin-top: 3px;">{{ $portfolio['in_progress'] }} more {{ $portfolio['in_progress'] === 1 ? 'place was' : 'places were' }} set up but {{ $portfolio['in_progress'] === 1 ? 'has' : 'have' }} no completed report yet, so {{ $portfolio['in_progress'] === 1 ? 'it is' : 'they are' }} not part of this picture.</div>
    @endif
    <div style="margin-top: 3px;">Repeat the assessment after 12 months to measure what has changed.</div>
</div>

{{-- How the portfolio was put together --}}
<h2 style="margin-top: 22px;">How this portfolio was put together</h2>
<div class="muted small">
    This report adds together the final reports already produced for each {{ $singular }}; no number here is calculated differently from the {{ $singular }}'s own report.
    A {{ $singular }} appears once. Scores are out of 100.
</div>
@if (! empty($portfolio['merged']))
    <div style="margin-top: 8px;"><strong>Counted once.</strong>
        @foreach ($portfolio['merged'] as $m)
            <div class="small">{{ $m['name'] }} was assessed more than once. The assessment answered by the most people ({{ $m['kept_respondents'] }}) is used; the other ({{ implode(', ', $m['other_respondents']) }} {{ count($m['other_respondents']) === 1 ? 'person' : 'people' }}) is left out so it is not counted twice.</div>
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
