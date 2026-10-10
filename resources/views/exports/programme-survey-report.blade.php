@php
    $site = $survey['site'];
    $color = fn ($row) => match (true) {
        ($row['improved'] ?? null) === true => '#16a34a',
        ($row['improved'] ?? null) === false => '#dc2626',
        default => '#64748b',
    };
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
    .muted { color: #64748b; }
    .bar { height: 6px; background: #e2e8f0; border-radius: 3px; }
    .fill { height: 6px; border-radius: 3px; background: #1d6fa5; }
    .fill-down { background: #f59e0b; }
    table { width: 100%; border-collapse: collapse; }
    td { vertical-align: top; }
    .item td { padding: 6px 0 2px 0; border-top: 1px solid #f1f5f9; }
    .value { font-size: 18px; font-weight: bold; text-align: right; }
    .small { font-size: 8.5px; }
</style>
</head>
<body>

<div class="eyebrow">{{ $survey['programme'] }} · Girls and women survey</div>
<h1>{{ $site['name'] }}</h1>
<div class="meta">
    {{ collect([$site['area_council'] ? $site['area_council'].' area council' : null, $site['state'], $site['surveyed_on'] ? 'Surveyed '.\Illuminate\Support\Carbon::parse($site['surveyed_on'])->format('d M Y') : null])->filter()->implode(' · ') }}
</div>

<div class="box" style="margin-top: 12px;">
    <b style="font-size: 16px;">{{ $survey['respondents'] }}</b>
    {{ $survey['respondents'] === 1 ? 'girl or woman' : 'girls and women' }} answered.
    @if ($survey['previous'])
        Compared with the earlier survey of {{ $survey['previous']['respondents'] }}
        ({{ \Illuminate\Support\Carbon::parse($survey['previous']['completed_at'])->format('d M Y') }}).
    @else
        This is the first survey here, so it is the starting point.
    @endif
    @unless ($survey['enough'])
        <div class="small" style="color: #b45309; margin-top: 4px;">Fewer than {{ $survey['minimum'] }} answered. Treat these figures as a rough guide only.</div>
    @endunless
</div>

@foreach ($survey['outcomes'] as $outcome)
    <h2>{{ $outcome['name'] }}</h2>
    <table>
        @foreach ($outcome['indicators'] as $row)
            <tr class="item">
                <td style="width: 72%;">
                    {{ $row['label'] }}
                    <div class="muted small">
                        {{ $row['direction'] === 'DOWN' ? 'Lower is better' : 'Higher is better' }}
                        @if ($row['kind'] === 'KNOWLEDGE') · average marks @endif
                        · {{ $row['n'] }} {{ $row['n'] === 1 ? 'answer' : 'answers' }}
                    </div>
                </td>
                <td style="width: 28%;">
                    <div class="value">{{ $row['value'] !== null ? round($row['value']).'%' : '—' }}</div>
                    @if (($row['change'] ?? null) !== null)
                        <div class="small" style="text-align: right; color: {{ $color($row) }};">
                            {{ $row['change'] > 0 ? '▲' : ($row['change'] < 0 ? '▼' : '■') }}
                            {{ abs($row['change']) }} points from {{ round($row['before']) }}%
                        </div>
                    @endif
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding-bottom: 4px;">
                    <div class="bar"><div class="fill {{ $row['direction'] === 'DOWN' ? 'fill-down' : '' }}" style="width: {{ $row['value'] !== null ? round($row['value']) : 0 }}%"></div></div>
                </td>
            </tr>
        @endforeach
    </table>
@endforeach

<p class="muted small" style="margin-top: 14px;">
    Each figure is the share of girls and women who gave that answer. Anyone who chose not to answer a question is left out of that figure. Answers are anonymous.
</p>

</body>
</html>
