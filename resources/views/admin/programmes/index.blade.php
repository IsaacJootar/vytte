<x-admin-layout title="Programme Reports">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Programme Reports</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            A programme is one set of assessments run at many schools and PHCs. Open one to see every finished report in it, across all workspaces, and to download the portfolio report.
        </p>
    </div>

    @forelse ($programmes as $programme)
        <a href="{{ route('admin.programmes.show', $programme['slug']) }}"
           class="mb-3 flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 transition-colors hover:border-vytte-300 dark:border-slate-700 dark:bg-slate-800">
            <div class="min-w-0">
                <p class="text-base font-bold text-slate-900 dark:text-white">{{ $programme['name'] }}</p>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ $programme['reports'] }} finished {{ $programme['reports'] === 1 ? 'report' : 'reports' }}
                </p>
            </div>
            <span class="flex-shrink-0 text-sm font-semibold text-vytte-700 dark:text-vytte-300">Open →</span>
        </a>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-slate-600 dark:text-slate-400">
            No programme has a finished report yet.
        </div>
    @endforelse
</x-admin-layout>
