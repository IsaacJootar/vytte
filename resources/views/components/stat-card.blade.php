@props([
    'label',
    'value',
    'sub' => null,
    'tone' => 'blue',
    'href' => null,
])

{{--
    The coloured number card used at the top of a page.

    Same treatment as the workspace dashboard, so a stat reads the same wherever the
    user is in the product. Tone carries meaning: blue and slate are neutral counts,
    strong/moderate/weak are judgements, none means "nothing to report yet".
--}}
@php
    $toneClass = 'metric-'.$tone;
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'metric-card '.$toneClass.($href ? ' block transition-transform hover:-translate-y-0.5' : '')]) }}>
    <div class="mb-3 flex items-start justify-between gap-2">
        <p class="metric-card-label">{{ $label }}</p>
        @isset($icon)
            <div class="metric-icon-badge">{{ $icon }}</div>
        @endisset
    </div>
    <p class="metric-card-value">{{ $value }}</p>
    @if ($sub)
        <p class="metric-card-sub">{{ $sub }}</p>
    @endif
</{{ $tag }}>
