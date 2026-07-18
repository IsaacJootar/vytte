<x-admin-layout title="Assessment Oversight">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Assessment Oversight</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">System-level metadata view; response content remains inside normal governed access paths.</p>
    </div>
    <form method="GET" class="mb-4 flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
        <select name="status" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All status</option>
            <option value="IN_PROGRESS" @selected(request('status') === 'IN_PROGRESS')>In progress</option>
            <option value="COMPLETE" @selected(request('status') === 'COMPLETE')>Complete</option>
        </select>
        <select name="creation_path" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All paths</option>
            <option value="COMPREHENSIVE" @selected(request('creation_path') === 'COMPREHENSIVE')>Comprehensive</option>
            <option value="FOCUSED" @selected(request('creation_path') === 'FOCUSED')>Focused</option>
        </select>
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Filter</button>
    </form>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
            <tr><th class="px-4 py-3">Assessment</th><th class="px-4 py-3">Workspace</th><th class="px-4 py-3">Path</th><th class="px-4 py-3">Lifecycle</th><th class="px-4 py-3">Immutable artifacts</th><th class="px-4 py-3">Score</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse ($assessments as $assessment)
                <tr>
                    <td class="px-4 py-3"><p class="font-semibold text-slate-900 dark:text-white">{{ $assessment->target?->name ?? 'Untitled target' }}</p><p class="text-xs font-mono text-slate-500">{{ $assessment->assessment_id }}</p></td>
                    <td class="px-4 py-3 text-slate-500">{{ $assessment->project?->workspace?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-xs font-bold text-slate-500">{{ $assessment->creation_path ?? 'LEGACY' }}</td>
                    <td class="px-4 py-3 text-xs text-slate-500">{{ $assessment->status }} · {{ $assessment->publish_status }}</td>
                    <td class="px-4 py-3 text-xs text-slate-500">Snapshot: {{ $assessment->snapshot ? 'Yes' : 'No' }} · Report: {{ $assessment->reportSnapshot ? 'Yes' : 'No' }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $assessment->score?->overall_score ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No assessments found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $assessments->links() }}</div>
</x-admin-layout>
