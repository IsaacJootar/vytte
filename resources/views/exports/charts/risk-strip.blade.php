{{-- Risk counts by level as three colored blocks. Pure HTML/CSS for DomPDF + browser.
     Props: $riskCounts (collection/array keyed HIGH/MEDIUM/LOW => count). --}}
@php
    $counts = collect($riskCounts ?? []);
    $levels = [
        ['HIGH', 'High', '#B91C1C', '#FEE2E2'],
        ['MEDIUM', 'Medium', '#B45309', '#FEF3C7'],
        ['LOW', 'Low', '#15803D', '#DCFCE7'],
    ];
@endphp
<table style="width:100%;border-collapse:separate;border-spacing:6px 0;">
    <tr>
        @foreach ($levels as [$key, $label, $fg, $bg])
            @php $count = (int) ($counts[$key] ?? 0); @endphp
            <td style="width:33%;background:{{ $bg }};padding:8px 12px;vertical-align:middle;">
                <div style="font-size:22px;font-weight:bold;color:{{ $fg }};">{{ $count }}</div>
                <div style="font-size:10px;color:{{ $fg }};">{{ $label }} risk{{ $count === 1 ? '' : 's' }}</div>
            </td>
        @endforeach
    </tr>
</table>
