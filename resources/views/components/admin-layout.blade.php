@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      class="{{ auth()->user()?->theme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title . ' · Vytte Admin' : 'Vytte Admin' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 dark:bg-slate-900 font-sans antialiased">

<div class="min-h-screen flex flex-col">

    {{-- Top bar --}}
    <header class="bg-slate-900 dark:bg-slate-950 text-white shadow-sm flex-shrink-0 border-b border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-12">
            <div class="flex items-center gap-3">
                <span class="text-xs font-bold tracking-widest uppercase text-slate-400">Vytte</span>
                <span class="text-slate-600">·</span>
                <span class="text-sm font-semibold text-white">Platform Admin</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-xs text-slate-400">{{ auth()->user()->name }}</span>
                <a href="{{ route('dashboard') }}"
                   class="text-xs text-slate-400 hover:text-white transition-colors">
                    ← Back to app
                </a>
            </div>
        </div>
    </header>

    <div class="flex-1 flex max-w-7xl mx-auto w-full px-4 sm:px-6 py-6 gap-6">

        {{-- Sidebar --}}
        <nav class="w-44 flex-shrink-0 hidden sm:block">
            <ul class="space-y-0.5">
                @foreach ([
                    ['route' => 'admin.dashboard', 'label' => 'Dashboard'],
                    ['route' => 'admin.workspaces.index', 'label' => 'Workspaces'],
                    ['route' => 'admin.modules.index', 'label' => 'Modules'],
                    ['route' => 'admin.settings.index', 'label' => 'Settings'],
                ] as $item)
                    <li>
                        <a href="{{ route($item['route']) }}"
                           class="block px-3 py-2 text-sm rounded-lg font-medium transition-colors
                               {{ request()->routeIs($item['route'] . '*')
                                   ? 'bg-vytte-700 text-white'
                                   : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white' }}">
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        {{-- Main content --}}
        <main class="flex-1 min-w-0">

            @if (session('success'))
                <div class="mb-5 flex items-center gap-3 px-4 py-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-sm text-green-800 dark:text-green-300 font-medium">
                    <svg class="w-4 h-4 text-green-600 dark:text-green-400 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
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
