{{-- Horizontal bar chart of domain scores, colored by band. Pure SVG for DomPDF + browser.
     Props: $domains (iterable of objects with ->domain_name and ->score). --}}
@php
    $rows = collect($domains)->filter(fn ($d) => ($d->score ?? null) !== null)->values();
    $width = 520;
    $rowH = 30;
    $labelW = 190;
    $barW = $width - $labelW - 44; // leave room for the value at the end
    $height = max(1, $rows->count()) * $rowH + 8;
@endphp
@if ($rows->isNotEmpty())
<svg width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}" style="max-width:100%;height:auto" xmlns="http://www.w3.org/2000/svg" font-family="DejaVu Sans, sans-serif">
    @foreach ($rows as $i => $d)
        @php
            $s = (float) $d->score;
            $y = $i * $rowH + 4;
            $barLen = round($barW * max(0, min(100, $s)) / 100, 1);
            $c = $s >= 70 ? '#15803D' : ($s >= 45 ? '#B45309' : '#B91C1C');
            $name = \Illuminate\Support\Str::limit($d->domain_name ?? 'Area', 30);
        @endphp
        <text x="0" y="{{ $y + $rowH * 0.62 }}" font-size="11" fill="#334155">{{ $name }}</text>
        <rect x="{{ $labelW }}" y="{{ $y + 5 }}" width="{{ $barW }}" height="{{ $rowH - 14 }}" rx="4" fill="#EEF2F6"/>
        <rect x="{{ $labelW }}" y="{{ $y + 5 }}" width="{{ $barLen }}" height="{{ $rowH - 14 }}" rx="4" fill="{{ $c }}"/>
        <text x="{{ $labelW + $barW + 6 }}" y="{{ $y + $rowH * 0.62 }}" font-size="11" font-weight="bold" fill="{{ $c }}">{{ number_format($s, 0) }}</text>
    @endforeach
</svg>
@endif
