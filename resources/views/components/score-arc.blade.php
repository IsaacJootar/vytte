{{--
    Score Arc Meter
    Props:
        score (int|null) — 0-100 or null for uncalibrated
        size  (int)       — SVG dimension in px (default 90)
        stroke (int)      — stroke width in px (default 7)
--}}
@props(['score' => null, 'size' => 90, 'stroke' => 7])

@php
    $cx   = $size / 2;
    $r    = $cx - $stroke;
    $circ = round(2 * M_PI * $r, 1);

    $calibrated = $score !== null;
    $filled     = $calibrated ? round($circ * ($score / 100), 1) : 0;
    $gap        = round($circ - $filled, 1);

    $arcColor = match (true) {
        ! $calibrated => null,
        $score >= 70  => '#15803D',
        $score >= 45  => '#B45309',
        default       => '#B91C1C',
    };
@endphp

<div class="relative flex-shrink-0" style="width:{{ $size }}px;height:{{ $size }}px">
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}" aria-hidden="true">
        {{-- Track --}}
        <circle
            cx="{{ $cx }}" cy="{{ $cx }}" r="{{ $r }}"
            fill="none"
            class="stroke-slate-200"
            stroke-width="{{ $stroke }}"
            @if(! $calibrated)
                stroke-dasharray="5 8"
                stroke-linecap="round"
            @endif
        />
        {{-- Score fill (calibrated only) --}}
        @if($calibrated)
        <circle
            cx="{{ $cx }}" cy="{{ $cx }}" r="{{ $r }}"
            fill="none"
            stroke="{{ $arcColor }}"
            stroke-width="{{ $stroke }}"
            stroke-dasharray="{{ $filled }} {{ $gap }}"
            stroke-linecap="round"
            transform="rotate(-90 {{ $cx }} {{ $cx }})"
        />
        @endif
    </svg>

    {{-- Center content --}}
    <div class="absolute inset-0 flex flex-col items-center justify-center">
        {{ $slot }}
    </div>
</div>
