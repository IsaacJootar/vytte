@props([
    'title' => null,
    'description' => null,
    'searchPlaceholder' => 'Search',
    'searchName' => 'search',
    'filters' => null,
    'action' => null,
    'headings' => [],
    'paginator' => null,
    'empty' => 'Nothing here yet',
    'emptyHint' => null,
])

{{--
    The one table pattern for Platform Admin.

    Search is a primary action, so it is a full-width field rather than a small box in a
    toolbar. Filters sit beside it and submit with the same form. Every table gets a sticky
    header, consistent row actions, a designed empty state and pagination, so screens do not
    each re-invent them.
--}}
<div class="space-y-4">
    @if ($title || $action)
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                @if ($title)
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
                @endif
            </div>
            @if ($action)
                <div class="flex-shrink-0">{{ $action }}</div>
            @endif
        </div>
    @endif

    <form method="GET" class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <label class="relative flex-1">
                <span class="sr-only">{{ $searchPlaceholder }}</span>
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                </svg>
                <input name="{{ $searchName }}" value="{{ request($searchName) }}" placeholder="{{ $searchPlaceholder }}"
                       class="w-full rounded-xl border-slate-300 py-2.5 pl-9 text-sm placeholder:text-slate-400 focus:border-vytte-500 focus:ring-vytte-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            </label>

            @if ($filters)
                <div class="flex flex-wrap items-center gap-2">{{ $filters }}</div>
            @endif

            <div class="flex items-center gap-2">
                <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-500 dark:bg-slate-700 dark:hover:bg-slate-600">
                    Search
                </button>
                @if (collect(request()->query())->filter()->isNotEmpty())
                    <a href="{{ url()->current() }}" class="text-sm font-semibold text-slate-500 hover:underline dark:text-slate-400">Clear</a>
                @endif
            </div>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                <tr>
                    @foreach ($headings as $heading)
                        <th scope="col" class="px-4 py-3 font-semibold">{{ $heading }}</th>
                    @endforeach
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                {{ $slot }}
                </tbody>
            </table>
        </div>

        @if ($paginator && $paginator->isEmpty())
            <div class="px-4 py-12 text-center">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $empty }}</p>
                @if ($emptyHint)
                    <p class="mx-auto mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">{{ $emptyHint }}</p>
                @endif
                @isset($emptyAction)
                    <div class="mt-4">{{ $emptyAction }}</div>
                @endisset
            </div>
        @endif
    </div>

    @if ($paginator && $paginator->hasPages())
        <div>{{ $paginator->links() }}</div>
    @endif
</div>
