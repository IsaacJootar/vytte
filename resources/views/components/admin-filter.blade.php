@props(['label', 'name'])

{{-- A labelled filter. Every control says what it does before it is touched. --}}
<label class="block">
    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ $label }}</span>
    <select name="{{ $name }}"
            {{ $attributes->merge(['class' => 'w-full rounded-xl border-slate-300 py-2.5 text-sm focus:border-vytte-500 focus:ring-vytte-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white']) }}>
        {{ $slot }}
    </select>
</label>
