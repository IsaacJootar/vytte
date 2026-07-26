{{-- Score over time as a line chart. Only meaningful with 2+ points. Pure SVG.
     Props: $points (array of ['label' => string, 'value' => float]). --}}
@php
    $pts = collect($points ?? [])->values();
    $width = 520;
    $height = 180;
    $padL = 34;
    $padR = 14;
    $padT = 14;
    $padB = 28;
    $plotW = $width - $padL - $padR;
    $plotH = $height - $padT - $padB;
    $n = $pts->count();
    $xFor = fn ($i) => $n <= 1 ? $padL + $plotW / 2 : round($padL + $plotW * $i / ($n - 1), 1);
    $yFor = fn ($v) => round($padT + $plotH * (1 - max(0, min(100, (float) $v)) / 100), 1);
@endphp
@if ($n >= 2)
<svg width="100%" viewBox="0 0 {{ $width }} {{ $height }}" xmlns="http://www.w3.org/2000/svg" font-family="DejaVu Sans, sans-serif">
    {{-- gridlines at 0/50/100 --}}
    @foreach ([0, 50, 100] as $g)
        @php $gy = $yFor($g); @endphp
        <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $width - $padR }}" y2="{{ $gy }}" stroke="#E5E7EB" stroke-width="1"/>
        <text x="{{ $padL - 6 }}" y="{{ $gy + 3 }}" text-anchor="end" font-size="9" fill="#94A3B8">{{ $g }}</text>
    @endforeach
    {{-- line --}}
    @php
        $poly = $pts->map(fn ($p, $i) => $xFor($i).','.$yFor($p['value']))->implode(' ');
    @endphp
    <polyline points="{{ $poly }}" fill="none" stroke="#0369A1" stroke-width="2.5"/>
    {{-- points + x labels --}}
    @foreach ($pts as $i => $p)
        @php $x = $xFor($i); $y = $yFor($p['value']); @endphp
        <circle cx="{{ $x }}" cy="{{ $y }}" r="3.5" fill="#0369A1"/>
        <text x="{{ $x }}" y="{{ $y - 8 }}" text-anchor="middle" font-size="9" font-weight="bold" fill="#0369A1">{{ number_format((float) $p['value'], 0) }}</text>
        <text x="{{ $x }}" y="{{ $height - 10 }}" text-anchor="middle" font-size="9" fill="#94A3B8">{{ $p['label'] }}</text>
    @endforeach
</svg>
@endif
