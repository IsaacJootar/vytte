{{-- Five-rung maturity ladder with the current level highlighted. Pure SVG.
     Props: $level (int 1-5 or null). --}}
@php
    $current = $level ? (int) $level : null;
    $rungs = [
        1 => 'Data Collection',
        2 => 'Data Reporting',
        3 => 'Data Analysis',
        4 => 'Data-Driven Management',
        5 => 'Learning Health System',
    ];
    $width = 520;
    $rowH = 30;
    $height = count($rungs) * $rowH + 6;
@endphp
<svg width="100%" viewBox="0 0 {{ $width }} {{ $height }}" xmlns="http://www.w3.org/2000/svg" font-family="DejaVu Sans, sans-serif">
    @foreach (array_reverse($rungs, true) as $num => $name)
        @php
            $i = 5 - $num; // level 5 on top
            $y = $i * $rowH + 3;
            $isCurrent = $current === $num;
            $bg = $isCurrent ? '#0369A1' : '#F1F5F9';
            $fg = $isCurrent ? '#FFFFFF' : '#64748B';
        @endphp
        <rect x="0" y="{{ $y }}" width="{{ $width }}" height="{{ $rowH - 6 }}" rx="6" fill="{{ $bg }}"/>
        <circle cx="18" cy="{{ $y + ($rowH - 6) / 2 }}" r="9" fill="{{ $isCurrent ? '#FFFFFF' : '#E2E8F0' }}"/>
        <text x="18" y="{{ $y + ($rowH - 6) / 2 + 4 }}" text-anchor="middle" font-size="11" font-weight="bold" fill="{{ $isCurrent ? '#0369A1' : '#64748B' }}">{{ $num }}</text>
        <text x="36" y="{{ $y + ($rowH - 6) / 2 + 4 }}" font-size="12" font-weight="{{ $isCurrent ? 'bold' : 'normal' }}" fill="{{ $fg }}">{{ $name }}{{ $isCurrent ? '  — you are here' : '' }}</text>
    @endforeach
</svg>
