@props([
    'points' => [],   // [['label' => '01 Jan', 'value' => 42.0], ...] oldest → newest
    'height' => 140,
    'width' => 480,
])

@php
    $data = collect($points)->values()->filter(fn ($p) => ($p['value'] ?? null) !== null)->values();
    $padX = 28; $padY = 16;
    $plotW = $width - $padX * 2;
    $plotH = $height - $padY * 2;
    $count = $data->count();

    // x by index, y by score (0 bottom, 100 top).
    $x = fn ($i) => $count <= 1 ? $padX + $plotW / 2 : round($padX + ($i * $plotW / ($count - 1)), 1);
    $y = fn ($v) => round($padY + $plotH - ($plotH * max(0, min(100, $v)) / 100), 1);

    $line = $data->map(fn ($p, $i) => $x($i).','.$y((float) $p['value']))->join(' ');
@endphp

@if ($data->isNotEmpty())
    <svg viewBox="0 0 {{ $width }} {{ $height }}" class="w-full h-auto" role="img" aria-label="Score over time" style="max-width: {{ $width }}px">
        {{-- gridlines at 45 and 70 (the band thresholds) --}}
        @foreach ([['v' => 70, 'c' => '#15803D'], ['v' => 45, 'c' => '#B45309']] as $g)
            <line x1="{{ $padX }}" y1="{{ $y($g['v']) }}" x2="{{ $width - $padX }}" y2="{{ $y($g['v']) }}"
                  stroke="{{ $g['c'] }}" stroke-width="1" stroke-dasharray="3 3" opacity="0.35" />
        @endforeach
        {{-- the line --}}
        @if ($count > 1)
            <polyline points="{{ $line }}" fill="none" stroke="#0D9488" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
        @endif
        {{-- points + labels --}}
        @foreach ($data as $i => $p)
            @php $vy = $y((float) $p['value']); $vx = $x($i); @endphp
            <circle cx="{{ $vx }}" cy="{{ $vy }}" r="3" fill="#0D9488" />
            <text x="{{ $vx }}" y="{{ $vy - 8 }}" text-anchor="middle" class="fill-slate-600 dark:fill-slate-300" style="font-size: 9px; font-weight: 700;">{{ number_format((float) $p['value'], 0) }}</text>
            <text x="{{ $vx }}" y="{{ $height - 3 }}" text-anchor="middle" class="fill-slate-400 dark:fill-slate-500" style="font-size: 8px;">{{ $p['label'] ?? '' }}</text>
        @endforeach
    </svg>
@endif
