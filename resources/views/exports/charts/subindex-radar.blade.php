{{-- Radar / spider chart of sub-index scores. Pure SVG for DomPDF + browser.
     Needs at least 3 points to form a shape; otherwise nothing is drawn.
     Props: $subIndices (iterable of objects with ->full_name/->acronym and ->score). --}}
@php
    $rows = collect($subIndices)->filter(fn ($s) => ($s->score ?? null) !== null)->values();
    $n = $rows->count();
    $size = 320;
    $cx = $size / 2;
    $cy = $size / 2;
    $rMax = $size * 0.34;
    $rings = [0.25, 0.5, 0.75, 1.0];

    $pointFor = function ($i, $frac) use ($n, $cx, $cy, $rMax) {
        $angle = (-M_PI / 2) + (2 * M_PI * $i / $n);
        return [
            round($cx + $rMax * $frac * cos($angle), 1),
            round($cy + $rMax * $frac * sin($angle), 1),
        ];
    };
@endphp
@if ($n >= 3)
<svg width="100%" viewBox="0 0 {{ $size }} {{ $size }}" xmlns="http://www.w3.org/2000/svg" font-family="DejaVu Sans, sans-serif">
    {{-- grid rings --}}
    @foreach ($rings as $ring)
        @php
            $pts = collect(range(0, $n - 1))->map(function ($i) use ($pointFor, $ring) {
                [$x, $y] = $pointFor($i, $ring);
                return "$x,$y";
            })->implode(' ');
        @endphp
        <polygon points="{{ $pts }}" fill="none" stroke="#E5E7EB" stroke-width="1"/>
    @endforeach
    {{-- spokes --}}
    @foreach ($rows as $i => $s)
        @php [$x, $y] = $pointFor($i, 1.0); @endphp
        <line x1="{{ $cx }}" y1="{{ $cy }}" x2="{{ $x }}" y2="{{ $y }}" stroke="#E5E7EB" stroke-width="1"/>
    @endforeach
    {{-- data polygon --}}
    @php
        $dataPts = $rows->map(function ($s, $i) use ($pointFor) {
            [$x, $y] = $pointFor($i, max(0, min(100, (float) $s->score)) / 100);
            return "$x,$y";
        })->implode(' ');
    @endphp
    <polygon points="{{ $dataPts }}" fill="#0369A1" fill-opacity="0.22" stroke="#0369A1" stroke-width="2"/>
    {{-- labels --}}
    @foreach ($rows as $i => $s)
        @php
            [$lx, $ly] = $pointFor($i, 1.16);
            $anchor = $lx < $cx - 5 ? 'end' : ($lx > $cx + 5 ? 'start' : 'middle');
            $label = $s->acronym ?? \Illuminate\Support\Str::limit($s->full_name ?? '', 14);
        @endphp
        <text x="{{ $lx }}" y="{{ $ly }}" text-anchor="{{ $anchor }}" font-size="10" fill="#475569">{{ $label }}</text>
    @endforeach
</svg>
@endif
