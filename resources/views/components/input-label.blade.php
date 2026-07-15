@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-semibold text-slate-700 mb-1']) }}>
    {{ $value ?? $slot }}
</label>
