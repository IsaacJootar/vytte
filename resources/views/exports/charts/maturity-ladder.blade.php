{{-- Five performance stages derived from the same overall score. This is not a separate score
     or a claim about institutional maturity. Props: $level (int 1-5 or null). --}}
@php
    $current = $level ? (int) $level : null;
    $stages = [
        1 => ['name' => 'Urgent Action', 'range' => '0–<20', 'meaning' => 'Stabilize severe gaps now.'],
        2 => ['name' => 'Foundational', 'range' => '20–<40', 'meaning' => 'Complete essential foundations.'],
        3 => ['name' => 'Developing', 'range' => '40–<60', 'meaning' => 'Make core capabilities consistent.'],
        4 => ['name' => 'Established', 'range' => '60–<80', 'meaning' => 'Target remaining weak areas.'],
        5 => ['name' => 'Leading', 'range' => '80–100', 'meaning' => 'Sustain, learn, and share what works.'],
    ];
@endphp
<table style="width:100%;border-collapse:separate;border-spacing:0 4px;">
    @foreach (array_reverse($stages, true) as $num => $stage)
        @php $isCurrent = $current === $num; @endphp
        <tr style="background:{{ $isCurrent ? '#0369A1' : '#F1F5F9' }};">
            <td style="width:28px;text-align:center;padding:7px 4px;font-size:11px;font-weight:bold;color:{{ $isCurrent ? '#FFFFFF' : '#64748B' }};">{{ $num }}</td>
            <td style="padding:7px 6px;font-size:10px;color:{{ $isCurrent ? '#FFFFFF' : '#334155' }};">
                <strong>{{ $stage['name'] }}</strong> <span style="opacity:.75">({{ $stage['range'] }})</span><br>
                <span style="font-size:9px;opacity:.85">{{ $stage['meaning'] }}</span>
            </td>
            <td style="width:72px;padding:7px 6px;text-align:right;font-size:9px;font-weight:bold;color:{{ $isCurrent ? '#FFFFFF' : '#94A3B8' }};">{{ $isCurrent ? 'CURRENT STAGE' : '' }}</td>
        </tr>
    @endforeach
</table>
