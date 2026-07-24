@props([
    'series' => [],   // [['label' => 'GOV', 'value' => 42.0], ...]
    'size' => 260,
    'compare' => null, // optional second series for A-vs-B overlay
])

@php
    $axes = collect($series)->values();
    $n = max($axes->count(), 1);
    $cx = $size / 2;
    $cy = $size / 2;
    $r = $size / 2 - 34;

    // A point on the given axis at a 0-100 value.
    $point = function (int $i, float $value) use ($n, $cx, $cy, $r) {
        $angle = deg2rad(-90 + ($i * 360 / $n));
        $dist = $r * max(0, min(100, $value)) / 100;
        return [round($cx + $dist * cos($angle), 1), round($cy + $dist * sin($angle), 1)];
    };
    $polygon = fn ($data) => collect($data)->values()
        ->map(fn ($row, $i) => implode(',', $point($i, (float) ($row['value'] ?? 0))))->join(' ');
@endphp

@if ($axes->isNotEmpty())
    <svg viewBox="0 0 {{ $size }} {{ $size }}" class="w-full h-auto" role="img" aria-label="Domain profile radar chart" style="max-width: {{ $size }}px">
        {{-- concentric rings --}}
        @foreach ([25, 50, 75, 100] as $ring)
            <polygon points="{{ collect(range(0, $n - 1))->map(fn ($i) => implode(',', $point($i, $ring)))->join(' ') }}"
                     fill="none" stroke="currentColor" class="text-slate-200 dark:text-slate-700" stroke-width="1" />
        @endforeach
        {{-- axes + labels --}}
        @foreach ($axes as $i => $row)
            @php [$ex, $ey] = $point($i, 100); [$lx, $ly] = $point($i, 118); @endphp
            <line x1="{{ $cx }}" y1="{{ $cy }}" x2="{{ $ex }}" y2="{{ $ey }}" stroke="currentColor" class="text-slate-200 dark:text-slate-700" stroke-width="1" />
            <text x="{{ $lx }}" y="{{ $ly }}" text-anchor="middle" dominant-baseline="middle"
                  class="fill-slate-500 dark:fill-slate-400" style="font-size: 9px; font-weight: 600;">{{ $row['label'] }}</text>
        @endforeach
        {{-- optional comparison series (drawn first, muted) --}}
        @if ($compare)
            <polygon points="{{ $polygon($compare) }}" fill="rgba(148,163,184,0.15)" stroke="#94A3B8" stroke-width="1.5" stroke-dasharray="3 3" />
        @endif
        {{-- the value polygon --}}
        <polygon points="{{ $polygon($axes) }}" fill="rgba(13,148,136,0.18)" stroke="#0D9488" stroke-width="2" />
        @foreach ($axes as $i => $row)
            @php [$px, $py] = $point($i, (float) ($row['value'] ?? 0)); @endphp
            <circle cx="{{ $px }}" cy="{{ $py }}" r="2.5" fill="#0D9488" />
        @endforeach
    </svg>
@endif
