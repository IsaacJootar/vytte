<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Team Invitation · Vytte</title>
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

        {{-- Header --}}
        <div class="px-6 py-5 border-b border-slate-100 text-center">
            <p class="text-xs font-semibold text-vytte-700 uppercase tracking-widest mb-1">You've been invited</p>
            <h1 class="text-xl font-bold text-slate-900">Join {{ $invite->workspace->name }}</h1>
        </div>

        {{-- Details --}}
        <div class="px-6 py-5 space-y-3">
            <div class="flex items-center justify-between text-sm">
                <span class="text-slate-500">Workspace</span>
                <span class="font-semibold text-slate-900">{{ $invite->workspace->name }}</span>
            </div>
            <div class="flex items-center justify-between text-sm">
                <span class="text-slate-500">Invited by</span>
                <span class="font-semibold text-slate-900">{{ $invite->invitedBy?->name ?? 'A workspace member' }}</span>
            </div>
            <div class="flex items-center justify-between text-sm">
                <span class="text-slate-500">Your role</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">
                    {{ ucfirst(strtolower($invite->role)) }}
                </span>
            </div>
            <div class="flex items-center justify-between text-sm">
                <span class="text-slate-500">Expires</span>
                <span class="text-slate-600">{{ $invite->expires_at?->diffForHumans() ?? 'Never' }}</span>
            </div>
        </div>

        {{-- Action --}}
        <div class="px-6 pb-6">
            @if ($user)
                {{-- Logged in: accept immediately --}}
                <form method="GET" action="{{ route('invitations.accept', $invite->token) }}">
                    <button type="submit"
                            class="w-full py-2.5 bg-vytte-700 text-white text-sm font-bold rounded-xl hover:bg-vytte-800 transition-colors duration-150">
                        Accept Invitation
                    </button>
                </form>
                <p class="mt-3 text-center text-xs text-slate-400">
                    Accepting as <strong class="text-slate-600">{{ $user->email }}</strong>.
                    Not you? <a href="{{ route('logout') }}" class="text-vytte-700 underline underline-offset-2"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit()">Sign out</a>
                </p>
                <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
            @else
                {{-- Guest: must log in or register first; intended URL auto-redirects after auth --}}
                <a href="{{ route('login') }}"
                   class="block w-full text-center py-2.5 bg-vytte-700 text-white text-sm font-bold rounded-xl hover:bg-vytte-800 transition-colors duration-150 mb-3">
                    Sign in to accept
                </a>
                <a href="{{ route('register') }}"
                   class="block w-full text-center py-2.5 border border-slate-200 text-sm font-semibold text-slate-700 rounded-xl hover:bg-slate-50 transition-colors duration-150">
                    Create an account
                </a>
                <p class="mt-4 text-center text-xs text-slate-400">
                    After signing in you'll be automatically added to <strong>{{ $invite->workspace->name }}</strong>.
                </p>
            @endif
        </div>

    </div>

    <p class="mt-6 text-center text-xs text-slate-400">Powered by <span class="font-semibold text-slate-500">Vytte</span></p>
</div>

</body>
</html>
