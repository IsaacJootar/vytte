<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'Vytte') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased bg-slate-50 dark:bg-slate-900 dark:text-slate-100">
        <div class="min-h-screen flex flex-col justify-center items-center px-4 py-12 sm:px-6 lg:px-8">
            <div class="mb-8">
                <a href="/" class="flex items-center gap-2">
                    <span class="text-2xl font-bold text-vytte-600 dark:text-vytte-400">Vytte</span>
                </a>
            </div>

            <div class="w-full max-w-sm bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 px-6 py-8">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
