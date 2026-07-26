{{-- Score over time as one bar per run. Pure HTML/CSS for DomPDF + browser.
     Props: $points (array of ['label' => string, 'value' => float]). --}}
@php
    $pts = collect($points ?? [])->values();
@endphp
@if ($pts->count() >= 2)
<table style="width:100%;border-collapse:collapse;">
    @foreach ($pts as $p)
        @php
            $v = (float) $p['value'];
            $pct = round(max(0, min(100, $v)));
            $c = $v >= 70 ? '#15803D' : ($v >= 45 ? '#B45309' : '#B91C1C');
        @endphp
        <tr>
            <td style="width:70px;font-size:10px;color:#64748B;padding:3px 8px 3px 0;vertical-align:middle;">{{ $p['label'] }}</td>
            <td style="vertical-align:middle;padding:3px 0;">
                <table style="width:100%;border-collapse:collapse;background:#EEF2F6;"><tr>
                    <td style="width:{{ $pct }}%;background:{{ $c }};height:11px;font-size:1px;line-height:11px;">&nbsp;</td>
                    <td style="font-size:1px;line-height:11px;">&nbsp;</td>
                </tr></table>
            </td>
            <td style="width:34px;text-align:right;font-size:10px;font-weight:bold;color:{{ $c }};padding-left:8px;vertical-align:middle;">{{ round($v) }}</td>
        </tr>
    @endforeach
</table>
@endif
