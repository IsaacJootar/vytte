<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invite Expired · Vytte</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans antialiased flex items-center justify-center p-4">

<div class="w-full max-w-md">

    {{-- Logo --}}
    <div class="flex justify-center mb-8">
        <div class="w-12 h-12 rounded-2xl bg-vytte-700 flex items-center justify-center">
            <x-vytte-mark class="w-6 h-6" />
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

        <div class="px-6 py-8 text-center">
            {{-- Icon --}}
            <div class="mx-auto mb-4 w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center">
                <svg class="w-6 h-6 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
            </div>

            <h1 class="text-lg font-bold text-slate-900 mb-1">This invite has expired</h1>
            <p class="text-sm text-slate-500 mb-1">
                The invitation to join <strong class="text-slate-700">{{ $invite->workspace->name }}</strong> is no longer valid.
            </p>
            <p class="text-sm text-slate-400">
                Ask the workspace owner to send you a new invite link.
            </p>
        </div>

        <div class="px-6 pb-6">
            @auth
                <a href="{{ route('dashboard') }}"
                   class="block w-full text-center py-2.5 bg-vytte-700 text-white text-sm font-bold rounded-xl hover:bg-vytte-800 transition-colors duration-150">
                    Go to dashboard
                </a>
            @else
                <a href="{{ route('login') }}"
                   class="block w-full text-center py-2.5 bg-vytte-700 text-white text-sm font-bold rounded-xl hover:bg-vytte-800 transition-colors duration-150">
                    Sign in
                </a>
            @endauth
        </div>

    </div>

    <p class="mt-6 text-center text-xs text-slate-400">Powered by <span class="font-semibold text-slate-500">Vytte</span></p>
</div>

</body>
</html>
