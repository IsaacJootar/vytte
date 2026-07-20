@props([
    'title' => null,
    'description' => null,
    'searchLabel' => 'Search',
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

    Search and filters are labelled fields laid out on a grid, each with the same visual
    weight, rather than unlabelled boxes in a toolbar: an administrator can see what every
    control does before touching it. The table sits in a section-card so it reads as a
    surface rather than a floating white rectangle, and the footer states the range being
    shown alongside the pager.
--}}
<div class="space-y-4">
    @if ($title || $action)
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                @if ($title)
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ $title }}</h2>
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

    <form method="GET" class="section-card p-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block lg:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ $searchLabel }}</span>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                    </svg>
                    <input name="{{ $searchName }}" value="{{ request($searchName) }}" placeholder="{{ $searchPlaceholder }}"
                           class="w-full rounded-xl border-slate-300 py-2.5 pl-9 text-sm placeholder:text-slate-400 focus:border-vytte-500 focus:ring-vytte-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                </div>
            </label>

            {{ $filters }}
        </div>

        <div class="mt-4 flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-700">
            <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-500 dark:bg-slate-700 dark:hover:bg-slate-600">
                Apply
            </button>
            @if (collect(request()->query())->filter()->isNotEmpty())
                <a href="{{ url()->current() }}" class="text-sm font-semibold text-slate-500 hover:underline dark:text-slate-400">Clear filters</a>
            @endif
        </div>
    </form>

    <div class="section-card">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-[0.12em] text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                <tr>
                    @foreach ($headings as $heading)
                        <th scope="col" class="px-4 py-3 font-semibold">{{ $heading }}</th>
                    @endforeach
                    <th scope="col" class="px-4 py-3 text-right font-semibold">Action</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @if ($paginator && $paginator->isEmpty())
                    <tr>
                        <td colspan="{{ count($headings) + 1 }}" class="px-4 py-14 text-center">
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $empty }}</p>
                            @if ($emptyHint)
                                <p class="mx-auto mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">{{ $emptyHint }}</p>
                            @endif
                            @isset($emptyAction)
                                <div class="mt-4">{{ $emptyAction }}</div>
                            @endisset
                        </td>
                    </tr>
                @else
                    {{ $slot }}
                @endif
                </tbody>
            </table>
        </div>

        @if ($paginator && $paginator->isNotEmpty())
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 dark:border-slate-700">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
                </p>
                @if ($paginator->hasPages())
                    <div>{{ $paginator->links() }}</div>
                @endif
            </div>
        @endif
    </div>
</div>
