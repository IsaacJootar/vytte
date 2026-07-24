@props([
    'icon' => 'sparkles',
    'title' => 'Nothing here yet',
    'message' => null,
    'action' => null,        // href for the primary next step
    'actionLabel' => null,   // button text
    'secondary' => null,     // href for a secondary link
    'secondaryLabel' => null,
])

{{--
    One empty state, everywhere. An empty page is a doorway, not a wall: a plain sentence and a
    single button that is the exact next action. The optional slot holds a greyed preview of
    what this page becomes once there is data, so the value is visible before it exists.
--}}
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 px-5 py-12 flex flex-col items-center text-center">
    <div class="w-12 h-12 rounded-xl bg-vytte-50 dark:bg-vytte-900/30 flex items-center justify-center mb-3">
        <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-6 h-6 text-vytte-600 dark:text-vytte-400" />
    </div>

    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</p>

    @if ($message)
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-sm">{{ $message }}</p>
    @endif

    @if ($action)
        <a href="{{ $action }}"
           class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 bg-vytte-700 text-white text-sm font-semibold rounded-lg hover:bg-vytte-800 transition-colors">
            {{ $actionLabel ?? 'Get started' }}
        </a>
    @endif

    @if ($secondary)
        <a href="{{ $secondary }}" class="mt-3 text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:underline">
            {{ $secondaryLabel ?? 'Learn more' }}
        </a>
    @endif

    @if (isset($slot) && trim($slot) !== '')
        <div class="mt-6 w-full opacity-60 pointer-events-none select-none">
            {{ $slot }}
        </div>
    @endif
</div>
