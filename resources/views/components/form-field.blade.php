@props(['label', 'name' => null, 'for' => null, 'hint' => null, 'optional' => false, 'error' => null])

{{--
    One field: label, the reason it is being asked, the control, then any error.

    Optional fields say so. Required ones are not marked, because marking the majority
    tells the reader nothing.

    Pass `name` and the label binds to a control with that id, and the field's own
    validation error is found automatically — so a field can never silently drop the
    message explaining why it was rejected.
--}}
@php
    $inputId = $for ?? $name;
    $errorBag = $errors ?? null;
    $fieldError = $error ?? ($name && $errorBag ? $errorBag->first($name) : null);
@endphp
<div>
    <label @if ($inputId) for="{{ $inputId }}" @endif class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
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

    @if ($fieldError)
        <p class="mt-1.5 text-xs font-medium text-danger-600 dark:text-danger-500">{{ $fieldError }}</p>
    @endif
</div>
