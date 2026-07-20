@props(['label', 'for' => null, 'hint' => null, 'optional' => false, 'error' => null])

{{--
    One field: label, the reason it is being asked, the control, then any error.

    Optional fields say so. Required ones are not marked, because marking the majority
    tells the reader nothing.
--}}
<div>
    <label @if ($for) for="{{ $for }}" @endif class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
        {{ $label }}
        @if ($optional)
            <span class="font-normal text-slate-400">(optional)</span>
        @endif
    </label>

    @if ($hint)
        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
    @endif

    <div class="mt-1.5">
        {{ $slot }}
    </div>

    @if ($error)
        <p class="mt-1.5 text-xs font-medium text-danger-600 dark:text-danger-500">{{ $error }}</p>
    @endif
</div>
