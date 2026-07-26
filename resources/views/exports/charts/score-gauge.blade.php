{{-- Overall score as a labelled horizontal bar. Pure HTML/CSS so it renders identically in
     DomPDF, the browser and print. Props: $score (float|null), $size (unused, kept for the
     call sites). --}}
@php
    $s = $score !== null ? max(0.0, min(100.0, (float) $score)) : null;
    $color = $s === null ? '#94A3B8' : ($s >= 70 ? '#15803D' : ($s >= 45 ? '#B45309' : '#B91C1C'));
    $bandLabel = $s === null ? 'Not calibrated' : ($s >= 70 ? 'Strong' : ($s >= 45 ? 'Moderate' : 'Weak'));
    $pct = $s === null ? 0 : round($s);
@endphp
<table style="width:100%;border-collapse:collapse;">
    <tr>
        <td style="width:64px;vertical-align:middle;padding-right:10px;">
            <span style="font-size:26px;font-weight:bold;color:{{ $color }};">{{ $s !== null ? number_format($s, 1) : '—' }}</span>
        </td>
        <td style="vertical-align:middle;">
            <div style="font-size:11px;font-weight:bold;color:{{ $color }};margin-bottom:3px;">{{ $bandLabel }} · out of 100</div>
            <table style="width:100%;border-collapse:collapse;background:#EEF2F6;"><tr>
                <td style="width:{{ $pct }}%;background:{{ $color }};height:12px;font-size:1px;line-height:12px;">&nbsp;</td>
                <td style="font-size:1px;line-height:12px;">&nbsp;</td>
            </tr></table>
        </td>
    </tr>
</table>
