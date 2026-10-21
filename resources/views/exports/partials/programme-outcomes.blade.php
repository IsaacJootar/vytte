@php
    $color = fn ($row) => match (true) {
        ($row['improved'] ?? null) === true => '#16a34a',
        ($row['improved'] ?? null) === false => '#dc2626',
        default => '#64748b',
    };
    $all = collect($outcomes['outcomes']);
    $isSecondary = fn ($outcome) => str_contains($outcome['name'], 'secondary');
    $cards = $all->reject($isSecondary)->map(fn ($outcome) => ['kind' => 'outcome', 'outcome' => $outcome])->values();
    if ($outcomes['endline_indicators']) {
        $cards->push(['kind' => 'endline']);
    }
    $cards = $cards->concat($all->filter($isSecondary)->map(fn ($outcome) => ['kind' => 'outcome', 'outcome' => $outcome]))->values();
@endphp
<h2>What girls and women told us</h2>
<div class="lead">
    <b style="font-size: 15px; color: #0f2c4a;">{{ $outcomes['respondents'] }}</b> {{ $outcomes['respondents'] === 1 ? 'person' : 'people' }} answered.
    @if ($outcomes['previous'])
        Compared with the earlier assessment of {{ $outcomes['previous']['respondents'] }}
        ({{ \Illuminate\Support\Carbon::parse($outcomes['previous']['completed_at'])->format('d M Y') }}).
    @else
        This is the first assessment here, so it is the starting point. The next one will show what changed.
    @endif
    @unless ($outcomes['enough'])
        <span style="color: #b45309; font-weight: bold;">Fewer than {{ $outcomes['minimum'] }} answered: a rough guide only.</span>
    @endunless
</div>

@foreach ($cards as $position => $item)
    <div class="outcome">
        @if ($item['kind'] === 'endline')
            <table class="ohead"><tr>
                <td style="width: 28px;"><span class="num" style="background: #94a3b8;">{{ $position + 1 }}</span></td>
                <td><div class="otitle">Dignity Banks that keep working</div>
                    <div class="muted small">Does the support carry on without the programme running everything? Measured at the follow-up, about 12 months after a Dignity Bank opens.</div></td>
            </tr></table>
            <table>
                @foreach ($outcomes['endline_indicators'] as $line)
                    <tr><td class="item" style="width: 70%;">{{ $line }}</td><td class="item" style="text-align: right;"><span class="pill">Measured at follow-up</span></td></tr>
                @endforeach
            </table>
        @else
            @php $outcome = $item['outcome']; @endphp
            <table class="ohead"><tr>
                <td style="width: 28px;"><span class="num">{{ $position + 1 }}</span></td>
                <td><div class="otitle">{{ $outcome['name'] }}</div>
                    @if ($outcome['note'])<div class="muted small">{{ $outcome['note'] }}</div>@endif</td>
            </tr></table>
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
        @endif
    </div>
@endforeach
