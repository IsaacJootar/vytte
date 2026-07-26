{{-- Overall score as a ring gauge. Pure SVG so it renders in DomPDF and in the browser.
     Props: $score (float|null), $size (int, default 130). --}}
@php
    $size = $size ?? 130;
    $stroke = round($size * 0.11);
    $cx = $size / 2;
    $r = $cx - $stroke;
    $circ = 2 * M_PI * $r;
    $pct = $score !== null ? max(0.0, min(100.0, (float) $score)) : 0;
    $filled = round($circ * ($pct / 100), 2);
    $rest = round($circ - $filled, 2);
    $color = $score === null ? '#94A3B8' : ((float) $score >= 70 ? '#15803D' : ((float) $score >= 45 ? '#B45309' : '#B91C1C'));
@endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}" xmlns="http://www.w3.org/2000/svg">
    <circle cx="{{ $cx }}" cy="{{ $cx }}" r="{{ $r }}" fill="none" stroke="#E5E7EB" stroke-width="{{ $stroke }}"/>
    @if ($score !== null)
        <circle cx="{{ $cx }}" cy="{{ $cx }}" r="{{ $r }}" fill="none" stroke="{{ $color }}" stroke-width="{{ $stroke }}"
                stroke-dasharray="{{ $filled }} {{ $rest }}" stroke-linecap="round"
                transform="rotate(-90 {{ $cx }} {{ $cx }})"/>
    @endif
    <text x="{{ $cx }}" y="{{ $cx + $size * 0.09 }}" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="{{ round($size * 0.24) }}" font-weight="bold" fill="{{ $color }}">{{ $score !== null ? number_format((float) $score, 1) : '—' }}</text>
    <text x="{{ $cx }}" y="{{ $cx + $size * 0.28 }}" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="{{ round($size * 0.09) }}" fill="#94A3B8">out of 100</text>
</svg>
