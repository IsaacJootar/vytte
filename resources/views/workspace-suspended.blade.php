<x-guest-layout>
    <div class="mx-auto max-w-lg text-center">
        <div class="section-card p-8">
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-xl text-amber-700 dark:bg-amber-900/40 dark:text-amber-200" aria-hidden="true">!</span>

            <h1 class="mt-4 text-lg font-bold text-slate-900 dark:text-white">
                {{ $workspace->status === 'ARCHIVED' ? 'This workspace has been closed' : 'This workspace is on hold' }}
            </h1>

            <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                @if ($workspace->status === 'ARCHIVED')
                    <strong>{{ $workspace->name }}</strong> has been closed by Vytte. Its assessments and
                    reports are kept safe and nothing has been deleted, but the workspace can no longer be used.
                @else
                    <strong>{{ $workspace->name }}</strong> has been placed on hold by Vytte, so it cannot be
                    used right now. Nothing has been deleted — your projects, assessments and reports are all
                    still here and will be exactly as you left them once the hold is lifted.
                @endif
            </p>

            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
                Contact Vytte support to resolve this.
            </p>

            <form method="POST" action="{{ route('logout') }}" class="mt-6">
                @csrf
                <button class="btn-secondary w-full">Sign out</button>
            </form>
        </div>
    </div>
</x-guest-layout>
