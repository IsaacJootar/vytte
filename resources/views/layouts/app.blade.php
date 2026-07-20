@php
    // One shell for every role. Only $nav differs, so spacing, mobile behaviour, theme
    // handling and focus states are defined once and cannot drift between roles.
    $nav ??= \App\Support\RoleNavigation::workspace();
    $title = $title ?? null;
    $user = auth()->user();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $user?->theme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · Vytte' : 'Vytte' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-[#F8FAFC] dark:bg-slate-900 font-sans antialiased">

<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded-lg focus:bg-vytte-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
    Skip to content
</a>

<div class="min-h-screen lg:flex">

    {{-- ===== DESKTOP SIDEBAR ===== --}}
    <aside class="hidden lg:flex lg:flex-col fixed top-0 left-0 bottom-0 w-52 bg-navy z-20">
        <a href="{{ route($nav['home']['route']) }}"
           class="flex items-center gap-2.5 px-3.5 py-[18px] border-b border-white/[0.08] hover:bg-white/[0.04] focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-vytte-500"
           aria-label="Go to {{ $nav['home']['subtitle'] }} home">
            <div class="w-8 h-8 rounded-lg bg-vytte-700 flex items-center justify-center flex-shrink-0">
                <x-vytte-mark class="w-4 h-4" />
            </div>
            <div class="min-w-0">
                <div class="text-[13px] font-bold text-white leading-tight">{{ $nav['home']['title'] }}</div>
                <div class="text-[10px] text-white/[0.35] leading-none mt-0.5 truncate">{{ $nav['home']['subtitle'] }}</div>
            </div>
        </a>

        <nav class="flex-1 px-2 py-2 flex flex-col gap-px overflow-y-auto" aria-label="Main navigation">
            @foreach ($nav['groups'] as $groupIndex => $group)
                @php
                    $groupIsActive = collect($group['items'])->contains(fn ($item) => request()->routeIs($item['active']));
                @endphp

                @if ($group['collapsible'])
                    {{-- Core Alpine only: the group holding the current page starts open. --}}
                    <div x-data="{ open: {{ $groupIsActive ? 'true' : 'false' }} }" class="mt-2">
                        <button type="button" x-on:click="open = !open" :aria-expanded="open.toString()"
                                class="flex w-full items-center gap-1.5 px-2.5 py-1 rounded text-[9px] font-semibold uppercase tracking-wider text-white/35 hover:text-white/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-700">
                            <svg class="w-3 h-3 transition-transform duration-150" :class="open && 'rotate-90'" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M7.5 5l5 5-5 5"/>
                            </svg>
                            <span>{{ $group['label'] }}</span>
                        </button>
                        <div x-show="open" x-transition class="flex flex-col gap-px pt-0.5">
                            @foreach ($group['items'] as $item)
                                <x-sidebar-nav-item :href="route($item['route'])" :icon="$item['icon']"
                                                    :active="request()->routeIs($item['active'])">
                                    {{ $item['label'] }}
                                </x-sidebar-nav-item>
                            @endforeach
                        </div>
                    </div>
                @else
                    @if ($group['label'])
                        <p class="mt-3 px-2.5 pb-1 text-[9px] font-semibold uppercase tracking-wider text-white/35">{{ $group['label'] }}</p>
                    @endif
                    @foreach ($group['items'] as $item)
                        <x-sidebar-nav-item :href="route($item['route'])" :icon="$item['icon']"
                                            :active="request()->routeIs($item['active'])">
                            {{ $item['label'] }}
                            @if (! empty($item['badge']))
                                <span class="ml-auto rounded-full bg-danger-500 px-1.5 text-[9px] font-bold leading-4 text-white">{{ $item['badge'] > 9 ? '9+' : $item['badge'] }}</span>
                            @endif
                        </x-sidebar-nav-item>
                    @endforeach
                @endif
            @endforeach

            @if ($nav['footer'])
                <div class="mx-1 mt-3 rounded-lg border border-white/[0.08] bg-white/[0.04] px-3 py-2">
                    <p class="text-[9px] font-semibold uppercase tracking-wider text-white/35">{{ $nav['footer']['label'] }}</p>
                    <p class="mt-0.5 text-[11px] font-bold text-white/75">{{ $nav['footer']['value'] }}</p>
                    @if ($nav['footer']['link'])
                        <a href="{{ route($nav['footer']['link']['route']) }}" class="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold text-vytte-300 hover:text-white">
                            {{ $nav['footer']['link']['label'] }} <span aria-hidden="true">→</span>
                        </a>
                    @endif
                </div>
            @endif
        </nav>

        <div class="flex items-center gap-2.5 px-3.5 py-3 border-t border-white/[0.08]">
            <div class="w-7 h-7 rounded-full bg-vytte-700 flex items-center justify-center text-[11px] font-bold text-white flex-shrink-0 uppercase">
                {{ substr($user?->name ?? '?', 0, 1) }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-[11px] font-semibold text-white/85 truncate">{{ $user?->name }}</div>
                <div class="text-[10px] text-white/[0.30] truncate">{{ $user?->email }}</div>
            </div>
            <x-theme-toggle class="text-white/[0.30] hover:text-white/70" />
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-white/[0.30] hover:text-white/70 transition-colors duration-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-500 rounded" title="Log out">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    <span class="sr-only">Log out</span>
                </button>
            </form>
        </div>
    </aside>

    {{-- ===== MAIN COLUMN ===== --}}
    <div class="flex-1 flex flex-col min-h-screen lg:ml-52">

        {{-- Mobile top bar --}}
        <header class="lg:hidden sticky top-0 z-30 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between px-4 py-3">
            <div class="flex min-w-0 items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-vytte-700 flex items-center justify-center flex-shrink-0">
                    <x-vytte-mark class="w-3.5 h-3.5" />
                </div>
                <div class="min-w-0">
                    <span class="block truncate text-sm font-bold text-slate-900 dark:text-white">{{ $title ?: $nav['home']['title'] }}</span>
                    <span class="block truncate text-[10px] leading-none text-slate-400">{{ $nav['home']['subtitle'] }}</span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <x-theme-toggle class="text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200" />
                <div class="w-7 h-7 rounded-full bg-vytte-700 flex items-center justify-center text-[11px] font-bold text-white uppercase flex-shrink-0">
                    {{ substr($user?->name ?? '?', 0, 1) }}
                </div>
            </div>
        </header>

        <main id="main-content" class="flex-1 px-4 py-5 sm:px-6 lg:px-8 pb-28 lg:pb-8">
            <div class="mx-auto w-full max-w-[1280px]">
                {{ $slot }}
            </div>
        </main>

        {{-- Mobile bottom navigation. Every role has one. --}}
        <nav class="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 flex pb-safe" aria-label="Main navigation">
            @foreach ($nav['mobile'] as $item)
                <x-mobile-nav-item :href="route($item['route'])" :icon="$item['icon']" :label="$item['label']"
                                   :active="request()->routeIs($item['active'])" />
            @endforeach
        </nav>
    </div>

</div>

@livewireScripts
</body>
</html>
