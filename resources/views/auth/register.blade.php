<x-guest-layout title="Create account">

    <h1 class="text-lg font-bold text-slate-900 mb-1">Create your account</h1>
    <p class="text-sm text-slate-500 mb-6">Get started with Vytte — free for teams</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="space-y-4">
            <div>
                <x-input-label for="name" value="Full name" />
                <x-text-input id="name" type="text" name="name"
                    :value="old('name')" required autofocus autocomplete="name"
                    placeholder="Isaac Jootar" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="email" value="Email address" />
                <x-text-input id="email" type="email" name="email"
                    :value="old('email')" required autocomplete="username"
                    placeholder="you@example.com" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <div>
                <x-input-label for="password" value="Password" />
                <x-text-input id="password" type="password" name="password"
                    required autocomplete="new-password" placeholder="Min. 8 characters" />
                <x-input-error :messages="$errors->get('password')" />
            </div>

            <div>
                <x-input-label for="password_confirmation" value="Confirm password" />
                <x-text-input id="password_confirmation" type="password" name="password_confirmation"
                    required autocomplete="new-password" placeholder="Repeat password" />
                <x-input-error :messages="$errors->get('password_confirmation')" />
            </div>
        </div>

        <x-primary-button class="w-full justify-center mt-6">
            Create account
        </x-primary-button>

        <p class="mt-5 text-center text-sm text-slate-500">
            Already have an account?
            <a href="{{ route('login') }}" class="text-vytte-700 hover:text-vytte-800 font-semibold">
                Sign in
            </a>
        </p>
    </form>

</x-guest-layout>
