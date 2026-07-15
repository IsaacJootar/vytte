<x-guest-layout title="Sign in">

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <h1 class="text-lg font-bold text-slate-900 mb-1">Sign in to Vytte</h1>
    <p class="text-sm text-slate-500 mb-6">Health systems diagnostic platform</p>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="space-y-4">
            <div>
                <x-input-label for="email" value="Email address" />
                <x-text-input id="email" type="email" name="email"
                    :value="old('email')" required autofocus autocomplete="username"
                    placeholder="you@example.com" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <x-input-label for="password" value="Password" class="mb-0" />
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}"
                           class="text-xs text-vytte-700 hover:text-vytte-800 font-medium">
                            Forgot password?
                        </a>
                    @endif
                </div>
                <x-text-input id="password" type="password" name="password"
                    required autocomplete="current-password" placeholder="••••••••" />
                <x-input-error :messages="$errors->get('password')" />
            </div>

            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="remember"
                       class="w-4 h-4 rounded border-slate-300 text-vytte-700 focus:ring-vytte-700">
                <span class="text-sm text-slate-600">Remember me</span>
            </label>
        </div>

        <x-primary-button class="w-full justify-center mt-6">
            Sign in
        </x-primary-button>

        @if (Route::has('register'))
            <p class="mt-5 text-center text-sm text-slate-500">
                No account?
                <a href="{{ route('register') }}" class="text-vytte-700 hover:text-vytte-800 font-semibold">
                    Create one
                </a>
            </p>
        @endif
    </form>

</x-guest-layout>
