<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) && $title ? $title . ' · Vytte' : 'Vytte' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-100 font-sans antialiased">

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
                href="#"
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
        <header class="lg:hidden sticky top-0 z-30 bg-white border-b border-slate-200 flex items-center justify-between px-4 py-3">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-vytte-700 flex items-center justify-center flex-shrink-0">
                    <x-vytte-mark class="w-3.5 h-3.5" />
                </div>
                <span class="text-sm font-bold text-slate-900">{{ $title ?? 'Vytte' }}</span>
            </div>
            <div class="w-7 h-7 rounded-full bg-vytte-700 flex items-center justify-center text-[11px] font-bold text-white uppercase flex-shrink-0">
                {{ substr(auth()->user()->name, 0, 1) }}
            </div>
        </header>

        {{-- Page content --}}
        <main class="flex-1 px-4 py-5 sm:px-6 lg:px-8 pb-28 lg:pb-8">
            {{ $slot }}
        </main>

        {{-- Mobile bottom tab bar --}}
        <nav class="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-white border-t border-slate-200 flex pb-safe">
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
                href="#"
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
