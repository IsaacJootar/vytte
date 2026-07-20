@props(['status'])

{{--
    Workspace status in the customer's language, not the database's.
    ACTIVE/SUSPENDED/ARCHIVED are storage values; nobody outside the code should read them.
--}}
@php
    [$label, $classes] = match ($status) {
        'ACTIVE' => ['Active', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'],
        'SUSPENDED' => ['On hold', 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'],
        'ARCHIVED' => ['Closed', 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200'],
        default => [ucfirst(strtolower((string) $status)), 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold '.$classes]) }}>
    {{ $label }}
</span>
