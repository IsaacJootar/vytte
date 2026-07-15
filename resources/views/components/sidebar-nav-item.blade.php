@props(['href' => '#', 'icon' => 'home', 'active' => false])

@php
    $baseClass  = 'relative flex items-center gap-2.5 px-3 py-2 rounded text-xs font-medium transition-colors duration-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-700';
    $activeClass = 'sb-active-bar text-white bg-vytte-700/[0.15]';
    $inactiveClass = 'text-white/[0.52] hover:bg-white/[0.06] hover:text-white/80';
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => $baseClass . ' ' . ($active ? $activeClass : $inactiveClass)]) }}>
    <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-4 h-4 flex-shrink-0" />
    <span>{{ $slot }}</span>
</a>
