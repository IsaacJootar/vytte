<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ auth()->user()?->theme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' · Vytte' : 'Vytte' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-[#F8FAFC] dark:bg-slate-900 font-sans antialiased">

<div class="min-h-screen lg:flex">

    {{-- ===== DESKTOP SIDEBAR ===== --}}
    <aside class="hidden lg:flex lg:flex-col fixed top-0 left-0 bottom-0 w-52 bg-navy z-20">

        {{-- Logo --}}
        <div class="flex items-center gap-2.5 px-3.5 py-[18px] border-b border-white/[0.08]">
            <div class="w-8 h-8 rounded-lg bg-vytte-700 flex items-center justify-center flex-shrink-0">
                <x-vytte-mark class="w-4 h-4" />
            </div>
            <div class="min-w-0">
                <div class="text-[13px] font-bold text-white leading-tight">Vytte</div>
                <div class="text-[10px] text-white/[0.35] leading-none mt-0.5 truncate">
                    {{ auth()->user()->activeWorkspace?->name ?? 'Workspace' }}
                </div>
                @php $sidebarPlan = auth()->user()->activeWorkspace?->plan ?? 'FREE'; @endphp
                <span class="mt-1 inline-block text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded
                    {{ $sidebarPlan === 'AGENCY' ? 'bg-violet-500/25 text-violet-200' : ($sidebarPlan === 'PRO' ? 'bg-vytte-500/25 text-vytte-200' : 'bg-white/10 text-white/40') }}">
                    {{ $sidebarPlan }}
                </span>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 px-2 py-2 flex flex-col gap-px overflow-y-auto">
            <x-sidebar-nav-item
                href="{{ route('dashboard') }}"
                icon="home"
                :active="request()->routeIs('dashboard')"
            >Dashboard</x-sidebar-nav-item>

            <x-sidebar-nav-item
                href="{{ route('projects.index') }}"
                icon="folder"
                :active="request()->routeIs('projects.*')"
            >Projects</x-sidebar-nav-item>

            <x-sidebar-nav-item
                href="{{ route('assessments.index') }}"
                icon="clipboard-document-list"
                :active="request()->routeIs('assessments.*')"
            >Assessments</x-sidebar-nav-item>

            <x-sidebar-nav-item
                href="#"
                icon="chart-bar"
                :active="request()->routeIs('reports.*')"
            >Reports</x-sidebar-nav-item>

            <div class="my-2 mx-1 border-t border-white/[0.08]"></div>

            <x-sidebar-nav-item
                href="{{ route('modules.index') }}"
                icon="squares-2x2"
                :active="request()->routeIs('modules.*')"
            >Modules</x-sidebar-nav-item>

            <x-sidebar-nav-item
                href="{{ route('team.index') }}"
                icon="users"
                :active="request()->routeIs('team.*')"
            >Team</x-sidebar-nav-item>

            {{-- Notifications bell --}}
            @php $unreadCount = auth()->user()->unreadNotifications()->count(); @endphp
            <a href="{{ route('notifications.index') }}"
               class="group flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg text-[13px] font-medium transition-colors duration-100 {{ request()->routeIs('notifications.*') ? 'bg-white/[0.12] text-white' : 'text-white/60 hover:bg-white/[0.07] hover:text-white' }}">
                <div class="relative flex-shrink-0">
                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zm0 16a2 2 0 002-2H8a2 2 0 002 2z"/>
                    </svg>
                    @if ($unreadCount > 0)
                        <span class="absolute -top-1 -right-1 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-red-500 text-[8px] font-bold text-white leading-none">
                            {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                        </span>
                    @endif
                </div>
                <span>Notifications</span>
                @if ($unreadCount > 0)
                    <span class="ml-auto text-[10px] font-bold text-red-400">{{ $unreadCount }}</span>
                @endif
            </a>

            <x-sidebar-nav-item
                href="{{ route('profile.edit') }}"
                icon="cog-6-tooth"
                :active="request()->routeIs('profile.*') || request()->routeIs('settings.*')"
            >Settings</x-sidebar-nav-item>
        </nav>

        {{-- User footer --}}
        <div class="flex items-center gap-2.5 px-3.5 py-3 border-t border-white/[0.08]">
            <div class="w-7 h-7 rounded-full bg-vytte-700 flex items-center justify-center text-[11px] font-bold text-white flex-shrink-0 uppercase">
                {{ substr(auth()->user()->name, 0, 1) }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-[11px] font-semibold text-white/85 truncate">{{ auth()->user()->name }}</div>
                <div class="text-[10px] text-white/[0.30] truncate">{{ auth()->user()->email }}</div>
            </div>

            {{-- Theme toggle --}}
            <form method="POST" action="{{ route('preferences.theme') }}">
                @csrf
                <input type="hidden" name="theme" value="{{ auth()->user()->theme === 'dark' ? 'light' : 'dark' }}">
                <button type="submit"
                        class="text-white/[0.30] hover:text-white/70 transition-colors duration-100 p-0.5"
                        title="{{ auth()->user()->theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode' }}">
                    @if(auth()->user()->theme === 'dark')
                        {{-- Sun icon --}}
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
                        </svg>
                    @else
                        {{-- Moon icon --}}
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                        </svg>
                    @endif
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-white/[0.30] hover:text-white/70 transition-colors duration-100" title="Log out">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </button>
            </form>
        </div>
    </aside>

    {{-- ===== MAIN CONTENT COLUMN ===== --}}
    <div class="flex-1 flex flex-col min-h-screen lg:ml-52">

        {{-- Mobile top bar --}}
        <header class="lg:hidden sticky top-0 z-30 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between px-4 py-3">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-vytte-700 flex items-center justify-center flex-shrink-0">
                    <x-vytte-mark class="w-3.5 h-3.5" />
                </div>
                <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $title ?: 'Vytte' }}</span>
            </div>
            <div class="flex items-center gap-2">
                {{-- Mobile theme toggle --}}
                <form method="POST" action="{{ route('preferences.theme') }}">
                    @csrf
                    <input type="hidden" name="theme" value="{{ auth()->user()->theme === 'dark' ? 'light' : 'dark' }}">
                    <button type="submit"
                            class="p-1.5 text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
                            title="{{ auth()->user()->theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode' }}">
                        @if(auth()->user()->theme === 'dark')
                            <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
                            </svg>
                        @else
                            <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                            </svg>
                        @endif
                    </button>
                </form>
                <div class="w-7 h-7 rounded-full bg-vytte-700 flex items-center justify-center text-[11px] font-bold text-white uppercase flex-shrink-0">
                    {{ substr(auth()->user()->name, 0, 1) }}
                </div>
            </div>
        </header>

        {{-- Page content --}}
        <main class="flex-1 px-4 py-5 sm:px-6 lg:px-8 pb-28 lg:pb-8">
            {{ $slot }}
        </main>

        {{-- Mobile bottom tab bar --}}
        <nav class="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 flex pb-safe">
            <x-mobile-nav-item
                href="{{ route('dashboard') }}"
                icon="home"
                label="Home"
                :active="request()->routeIs('dashboard')"
            />
            <x-mobile-nav-item
                href="{{ route('projects.index') }}"
                icon="folder"
                label="Projects"
                :active="request()->routeIs('projects.*')"
            />
            <x-mobile-nav-item
                href="{{ route('assessments.index') }}"
                icon="clipboard-document-list"
                label="Assess"
                :active="request()->routeIs('assessments.*')"
            />
            <x-mobile-nav-item
                href="{{ route('profile.edit') }}"
                icon="user"
                label="More"
                :active="request()->routeIs('profile.*') || request()->routeIs('settings.*')"
            />
        </nav>
    </div>

</div>

@livewireScripts
</body>
</html>
