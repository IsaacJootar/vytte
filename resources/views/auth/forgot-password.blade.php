<x-guest-layout title="Reset password">

    <h1 class="text-lg font-bold text-slate-900 mb-1">Reset your password</h1>
    <p class="text-sm text-slate-500 mb-5">Enter your email and we'll send a reset link.</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <x-input-label for="email" value="Email address" />
            <x-text-input id="email" type="email" name="email"
                :value="old('email')" required autofocus placeholder="you@example.com" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <x-primary-button class="w-full justify-center mt-5">
            Send reset link
        </x-primary-button>

        <p class="mt-5 text-center text-sm text-slate-500">
            <a href="{{ route('login') }}" class="text-vytte-700 hover:text-vytte-800 font-semibold">
                Back to sign in
            </a>
        </p>
    </form>

</x-guest-layout>
