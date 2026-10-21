@php
    $color = fn ($row) => match (true) {
        ($row['improved'] ?? null) === true => '#16a34a',
        ($row['improved'] ?? null) === false => '#dc2626',
        default => '#64748b',
    };
@endphp
<h2>What girls and women told us</h2>
<div class="muted">
    <b style="font-size: 14px; color: #1e293b;">{{ $outcomes['respondents'] }}</b> {{ $outcomes['respondents'] === 1 ? 'person' : 'people' }} answered.
    @if ($outcomes['previous'])
        Compared with the earlier assessment of {{ $outcomes['previous']['respondents'] }}
        ({{ \Illuminate\Support\Carbon::parse($outcomes['previous']['completed_at'])->format('d M Y') }}).
    @else
        This is the first assessment here, so it is the starting point.
    @endif
    @unless ($outcomes['enough'])
        <span style="color: #b45309;">Fewer than {{ $outcomes['minimum'] }} answered. Treat these figures as a rough guide only.</span>
    @endunless
</div>
@foreach ($outcomes['outcomes'] as $outcome)
    <div class="eyebrow" style="margin-top: 10px;">{{ $outcome['name'] }}</div>
    <table>
        @foreach ($outcome['indicators'] as $row)
            <tr>
                <td class="item" style="width: 72%;">
                    {{ $row['label'] }}
                    <div class="muted small">
                        {{ $row['direction'] === 'DOWN' ? 'Lower is better' : 'Higher is better' }}
                        @if ($row['kind'] === 'KNOWLEDGE') · average marks @endif
                        · {{ $row['n'] }} {{ $row['n'] === 1 ? 'answer' : 'answers' }}
                    </div>
                    <div class="bar" style="margin-top: 3px;"><div class="fill" style="width: {{ $row['value'] !== null ? round($row['value']) : 0 }}%; background: {{ $row['direction'] === 'DOWN' ? '#f59e0b' : '#1d6fa5' }};"></div></div>
                </td>
                <td class="item" style="width: 28%; text-align: right;">
                    <div style="font-size: 16px; font-weight: bold;">{{ $row['value'] !== null ? round($row['value']).'%' : '—' }}</div>
                    @if (($row['change'] ?? null) !== null)
                        <div class="small" style="color: {{ $color($row) }};">
                            {{ $row['change'] > 0 ? '▲' : ($row['change'] < 0 ? '▼' : '■') }}
                            {{ abs($row['change']) }} points from {{ round($row['before']) }}%
                        </div>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
@endforeach
