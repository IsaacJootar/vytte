@props(['status'])

@php
    // Governance statuses shown in ordinary language. The stored value is unchanged.
    $presentation = match ($status) {
        'DRAFT' => ['label' => 'Draft', 'classes' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'],
        'INTERNAL_REVIEW' => ['label' => 'In review', 'classes' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200'],
        'APPROVED' => ['label' => 'Approved, not yet locked', 'classes' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200'],
        'PUBLISHED' => ['label' => 'Published', 'classes' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'],
        'SUPERSEDED' => ['label' => 'Replaced by newer version', 'classes' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200'],
        'ARCHIVED' => ['label' => 'Archived', 'classes' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200'],
        default => ['label' => $status, 'classes' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200'],
    };
@endphp

<span class="inline-block rounded-full px-2.5 py-1 text-xs font-semibold {{ $presentation['classes'] }}">{{ $presentation['label'] }}</span>
