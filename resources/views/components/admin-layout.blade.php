@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      class="{{ auth()->user()?->theme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' · Vytte Platform Admin' : 'Vytte Platform Admin' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#F8FAFC] dark:bg-slate-900 font-sans antialiased">

@php
    $adminNav = [
        ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'active' => 'admin.dashboard'],
        ['route' => 'admin.official-content.index', 'label' => 'Official Content', 'active' => 'admin.official-content.*'],
        ['route' => 'admin.question-groups.index', 'label' => 'Question Groups', 'active' => 'admin.question-groups.*'],
        ['route' => 'admin.question-identities.index', 'label' => 'Questions', 'active' => 'admin.question-identities.*'],
        ['route' => 'admin.question-versions.index', 'label' => 'Question Versions', 'active' => 'admin.question-versions.*'],
        ['route' => 'admin.framework-versions.index', 'label' => 'Frameworks', 'active' => 'admin.framework-versions.*'],
        ['route' => 'admin.catalogue-releases.index', 'label' => 'Catalogue Releases', 'active' => 'admin.catalogue-releases.*'],
        ['route' => 'admin.facility-profiles.index', 'label' => 'Facility Profiles', 'active' => 'admin.facility-profiles.*'],
        ['route' => 'admin.scoring-policies.index', 'label' => 'Scoring Policies', 'active' => 'admin.scoring-policies.*'],
        ['route' => 'admin.workspaces.index', 'label' => 'Workspaces', 'active' => 'admin.workspaces.*'],
        ['route' => 'admin.platform-users.index', 'label' => 'Platform Users', 'active' => 'admin.platform-users.*'],
        ['route' => 'admin.assessment-oversight.index', 'label' => 'Assessment Oversight', 'active' => 'admin.assessment-oversight.*'],
        ['route' => 'admin.report-shares.index', 'label' => 'Report Shares', 'active' => 'admin.report-shares.*'],
        ['route' => 'admin.audit-logs.index', 'label' => 'Audit Logs', 'active' => 'admin.audit-logs.*'],
        ['route' => 'admin.modules.index', 'label' => 'Modules', 'active' => 'admin.modules.*'],
        ['route' => 'admin.domain-taxonomies.index', 'label' => 'Domain Taxonomies', 'active' => 'admin.domain-taxonomies.*'],
        ['route' => 'admin.geographic-usage.index', 'label' => 'Geographic Usage', 'active' => 'admin.geographic-usage.*'],
        ['route' => 'admin.plan-features.index', 'label' => 'Plan Features', 'active' => 'admin.plan-features.*'],
        ['route' => 'admin.settings.index', 'label' => 'Settings', 'active' => 'admin.settings.*'],
    ];
@endphp

<div class="min-h-screen lg:flex">

    <aside class="hidden lg:flex lg:flex-col fixed top-0 left-0 bottom-0 w-52 bg-[#10203A] z-20">
        <a href="{{ route('admin.dashboard') }}"
           class="flex items-center gap-2.5 px-3.5 py-[18px] border-b border-white/[0.08] hover:bg-white/[0.04] focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-vytte-500"
           aria-label="Go to platform admin dashboard">
            <div class="w-8 h-8 rounded-lg bg-vytte-700 flex items-center justify-center flex-shrink-0">
                <x-vytte-mark class="w-4 h-4" />
            </div>
            <div class="min-w-0">
                <div class="text-[13px] font-bold text-white leading-tight">Vytte</div>
                <div class="text-[10px] text-white/[0.42] leading-none mt-0.5 truncate">Platform Admin</div>
            </div>
        </a>

        <nav class="flex-1 px-2 py-2 flex flex-col gap-px overflow-y-auto">
            @foreach ($adminNav as $item)
                <a href="{{ route($item['route']) }}"
                   class="group flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg text-[13px] font-medium transition-colors duration-100
                   {{ request()->routeIs($item['active'])
                       ? 'bg-white/[0.12] text-white'
                       : 'text-white/60 hover:bg-white/[0.07] hover:text-white' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ request()->routeIs($item['active']) ? 'bg-vytte-400' : 'bg-white/20 group-hover:bg-white/45' }}"></span>
                    <span class="truncate">{{ $item['label'] }}</span>
                </a>
            @endforeach

            <div class="mx-1 mt-3 rounded-lg border border-white/[0.08] bg-white/[0.04] px-3 py-2">
                <p class="text-[9px] font-semibold uppercase tracking-wider text-white/35">Access level</p>
                <p class="mt-0.5 text-[11px] font-bold text-white/75">Vytte Platform Admin</p>
            </div>
        </nav>

        <div class="flex items-center gap-2.5 px-3.5 py-3 border-t border-white/[0.08]">
            <div class="w-7 h-7 rounded-full bg-vytte-700 flex items-center justify-center text-[11px] font-bold text-white flex-shrink-0 uppercase">
                {{ substr(auth()->user()->name, 0, 1) }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-[11px] font-semibold text-white/85 truncate">{{ auth()->user()->name }}</div>
                <div class="text-[10px] text-white/[0.30] truncate">{{ auth()->user()->email }}</div>
            </div>

            <a href="{{ route('dashboard') }}"
               class="text-white/[0.30] hover:text-white/70 transition-colors duration-100"
               title="Back to app">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 12h18"/>
                    <path d="M9 18l-6-6 6-6"/>
                </svg>
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-white/[0.30] hover:text-white/70 transition-colors duration-100" title="Log out">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </button>
            </form>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-h-screen lg:ml-52">
        <header class="lg:hidden sticky top-0 z-30 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between px-4 py-3">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-vytte-700 flex items-center justify-center flex-shrink-0">
                    <x-vytte-mark class="w-3.5 h-3.5" />
                </div>
                <div>
                    <span class="block text-sm font-bold text-slate-900 dark:text-white">Vytte</span>
                    <span class="block text-[10px] text-slate-500 dark:text-slate-400 leading-none">Platform Admin</span>
                </div>
            </div>
            <a href="{{ route('dashboard') }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-400">Back to app</a>
        </header>

        <main class="flex-1 px-4 py-5 sm:px-6 lg:px-8 pb-8">
            @if (session('success'))
                <div class="mb-5 flex items-center gap-3 px-4 py-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-sm text-green-800 dark:text-green-300 font-medium">
                    <svg class="w-4 h-4 text-green-600 dark:text-green-400 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                    </svg>
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 px-4 py-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-800 dark:text-red-300">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>

</div>

</body>
</html>
