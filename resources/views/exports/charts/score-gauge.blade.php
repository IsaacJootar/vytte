{{-- Overall score as a ring gauge. Pure SVG so it renders in DomPDF and in the browser.
     Uses a path arc rather than stroke-dasharray, which DomPDF renders reliably.
     Props: $score (float|null), $size (int, default 130). --}}
@php
    $size = $size ?? 130;
    $stroke = round($size * 0.11);
    $cx = $size / 2;
    $r = $cx - $stroke;
    $pct = $score !== null ? max(0.0, min(100.0, (float) $score)) : 0;
    $color = $score === null ? '#94A3B8' : ((float) $score >= 70 ? '#15803D' : ((float) $score >= 45 ? '#B45309' : '#B91C1C'));

    // Arc endpoint, sweeping clockwise from 12 o'clock.
    $theta = deg2rad(-90 + ($pct / 100) * 360);
    $endX = round($cx + $r * cos($theta), 2);
    $endY = round($cx + $r * sin($theta), 2);
    $largeArc = $pct > 50 ? 1 : 0;
    $topX = $cx;
    $topY = round($cx - $r, 2);
@endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}" style="max-width:100%;height:auto" xmlns="http://www.w3.org/2000/svg">
    <circle cx="{{ $cx }}" cy="{{ $cx }}" r="{{ $r }}" fill="none" stroke="#E5E7EB" stroke-width="{{ $stroke }}"/>
    @if ($score !== null && $pct > 0)
        @if ($pct >= 99.9)
            <circle cx="{{ $cx }}" cy="{{ $cx }}" r="{{ $r }}" fill="none" stroke="{{ $color }}" stroke-width="{{ $stroke }}"/>
        @else
            <path d="M {{ $topX }} {{ $topY }} A {{ $r }} {{ $r }} 0 {{ $largeArc }} 1 {{ $endX }} {{ $endY }}" fill="none" stroke="{{ $color }}" stroke-width="{{ $stroke }}" stroke-linecap="round"/>
        @endif
    @endif
    <text x="{{ $cx }}" y="{{ $cx + $size * 0.09 }}" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="{{ round($size * 0.24) }}" font-weight="bold" fill="{{ $color }}">{{ $score !== null ? number_format((float) $score, 1) : '—' }}</text>
    <text x="{{ $cx }}" y="{{ $cx + $size * 0.28 }}" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="{{ round($size * 0.09) }}" fill="#94A3B8">out of 100</text>
</svg>
