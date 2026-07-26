{{-- Sub-index scores as horizontal bars. Pure HTML/CSS so DomPDF renders it (a true radar
     needs SVG polygons DomPDF cannot draw). Props: $subIndices. --}}
@php
    $rows = collect($subIndices)->filter(fn ($s) => ($s->score ?? null) !== null)->values();
@endphp
@if ($rows->isNotEmpty())
<table style="width:100%;border-collapse:collapse;">
    @foreach ($rows as $s)
        @php
            $v = (float) $s->score;
            $pct = round(max(0, min(100, $v)));
            $c = $v >= 70 ? '#15803D' : ($v >= 45 ? '#B45309' : '#B91C1C');
            $name = $s->full_name ?? $s->acronym ?? 'Sub-index';
        @endphp
        <tr>
            <td style="width:40%;font-size:10px;color:#334155;padding:3px 8px 3px 0;vertical-align:middle;">{{ \Illuminate\Support\Str::limit($name, 34) }}</td>
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
