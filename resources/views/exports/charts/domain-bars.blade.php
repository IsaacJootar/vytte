{{-- Horizontal bar chart of domain scores. Pure HTML/CSS (nested tables) so DomPDF renders it.
     Props: $domains (iterable of objects with ->domain_name and ->score). --}}
@php
    $rows = collect($domains)->filter(fn ($d) => ($d->score ?? null) !== null)->values();
@endphp
@if ($rows->isNotEmpty())
<table style="width:100%;border-collapse:collapse;">
    @foreach ($rows as $d)
        @php
            $s = (float) $d->score;
            $pct = round(max(0, min(100, $s)));
            $c = $s >= 70 ? '#15803D' : ($s >= 45 ? '#B45309' : '#B91C1C');
        @endphp
        <tr>
            <td style="width:38%;font-size:10px;color:#334155;padding:3px 8px 3px 0;vertical-align:middle;">{{ \Illuminate\Support\Str::limit($d->domain_name ?? 'Area', 34) }}</td>
            <td style="vertical-align:middle;padding:3px 0;">
                <table style="width:100%;border-collapse:collapse;background:#EEF2F6;"><tr>
                    <td style="width:{{ $pct }}%;background:{{ $c }};height:11px;font-size:1px;line-height:11px;">&nbsp;</td>
                    <td style="font-size:1px;line-height:11px;">&nbsp;</td>
                </tr></table>
            </td>
            <td style="width:34px;text-align:right;font-size:10px;font-weight:bold;color:{{ $c }};padding-left:8px;vertical-align:middle;">{{ round($s) }}</td>
        </tr>
    @endforeach
</table>
@endif
