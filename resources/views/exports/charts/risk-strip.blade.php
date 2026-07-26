{{-- Risk counts by level as colored blocks. Pure SVG for DomPDF + browser.
     Props: $riskCounts (collection/array keyed HIGH/MEDIUM/LOW => count). --}}
@php
    $counts = collect($riskCounts ?? []);
    $levels = [
        ['HIGH', 'High', '#B91C1C', '#FEE2E2'],
        ['MEDIUM', 'Medium', '#B45309', '#FEF3C7'],
        ['LOW', 'Low', '#15803D', '#DCFCE7'],
    ];
    $width = 520;
    $blockW = 168;
    $gap = 8;
    $height = 66;
@endphp
<svg width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}" style="max-width:100%;height:auto" xmlns="http://www.w3.org/2000/svg" font-family="DejaVu Sans, sans-serif">
    @foreach ($levels as $i => [$key, $label, $fg, $bg])
        @php $x = $i * ($blockW + $gap); $count = (int) ($counts[$key] ?? 0); @endphp
        <rect x="{{ $x }}" y="0" width="{{ $blockW }}" height="{{ $height }}" rx="8" fill="{{ $bg }}"/>
        <text x="{{ $x + 14 }}" y="30" font-size="24" font-weight="bold" fill="{{ $fg }}">{{ $count }}</text>
        <text x="{{ $x + 14 }}" y="50" font-size="11" fill="{{ $fg }}">{{ $label }} risk{{ $count === 1 ? '' : 's' }}</text>
    @endforeach
</svg>
