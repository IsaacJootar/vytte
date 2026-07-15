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
<body class="font-sans antialiased bg-slate-100 min-h-screen">

<div class="min-h-screen flex flex-col justify-center items-center px-4 py-12 sm:px-6">

    {{-- Logo --}}
    <a href="/" class="flex items-center gap-2.5 mb-8">
        <div class="w-9 h-9 rounded-xl bg-vytte-700 flex items-center justify-center">
            <x-vytte-mark class="w-5 h-5" />
        </div>
        <span class="text-xl font-bold text-slate-900 tracking-tight">Vytte</span>
    </a>

    {{-- Auth card --}}
    <div class="w-full max-w-sm bg-white rounded-2xl border border-slate-200 shadow-sm px-6 py-8">
        {{ $slot }}
    </div>

</div>

@livewireScripts
</body>
</html>
