<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assessment — Vytte</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-slate-50 text-slate-900">

    <header class="bg-white border-b border-slate-200 px-4 py-3">
        <div class="max-w-lg mx-auto">
            <span class="text-sm font-bold text-vytte-700 tracking-tight">Vytte</span>
        </div>
    </header>

    <main class="max-w-lg mx-auto px-4 py-6">
        @livewire('public-respondent-runner', ['token' => $token])
    </main>

    @livewireScripts
</body>
</html>
