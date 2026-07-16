{{-- Score status pill. Pass score (int|null). --}}
@props(['score' => null])

@php
    [$label, $cls] = match (true) {
        $score === null => ['Uncalibrated', 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400'],
        $score >= 70    => ['Strong',       'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400'],
        $score >= 45    => ['Moderate',     'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400'],
        default         => ['Weak',         'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'],
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold $cls"]) }}>
    {{ $label }}
</span>
