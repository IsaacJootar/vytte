<x-app-layout title="Custom Assessments">
    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Custom Assessments</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Workspace-only assessment designs. They do not modify official Vytte methodology or scoring.</p>
        </div>
        <a href="{{ route('custom-assessments.create') }}" class="rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">New custom assessment</a>
    </div>

    <x-plan-gate feature="workspace_custom_assessments">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                    <tr><th class="px-4 py-3">Design</th><th class="px-4 py-3">Scope</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse ($designs as $design)
                        <tr>
                            <td class="px-4 py-3"><p class="font-semibold text-slate-900 dark:text-white">{{ $design->title }}</p><p class="text-xs text-slate-500">{{ $design->purpose }}</p></td>
                            <td class="px-4 py-3 text-slate-500">{{ $design->scope ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs font-bold text-slate-500">{{ $design->status }}</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('custom-assessments.show', $design) }}" class="text-sm font-semibold text-vytte-700 dark:text-vytte-300">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No custom assessment designs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $designs->links() }}</div>
    </x-plan-gate>
</x-app-layout>
