<x-admin-layout title="Audit Logs">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Audit Logs</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Immutable platform and workspace activity trail.</p>
    </div>
    <form method="GET" class="mb-4 flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
        <input name="event" value="{{ request('event') }}" placeholder="Filter event" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
        <input name="workspace_id" value="{{ request('workspace_id') }}" placeholder="Workspace ID" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Filter</button>
    </form>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
            <tr><th class="px-4 py-3">Time</th><th class="px-4 py-3">Event</th><th class="px-4 py-3">Actor</th><th class="px-4 py-3">Workspace</th><th class="px-4 py-3">Subject</th><th class="px-4 py-3">Changes</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse ($logs as $log)
                <tr>
                    <td class="px-4 py-3 text-xs text-slate-500">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $log->event }}</td>
                    <td class="px-4 py-3 text-xs font-mono text-slate-500">{{ $log->user_id ?? 'system/public' }}</td>
                    <td class="px-4 py-3 text-xs font-mono text-slate-500">{{ $log->workspace_id ?? '—' }}</td>
                    <td class="px-4 py-3 text-xs text-slate-500">{{ $log->auditable_type ? class_basename($log->auditable_type) : '—' }}<br><span class="font-mono">{{ $log->auditable_id }}</span></td>
                    <td class="px-4 py-3 text-xs text-slate-500">
                        <details>
                            <summary class="cursor-pointer font-semibold">View JSON</summary>
                            <pre class="mt-2 max-w-md overflow-auto rounded-lg bg-slate-950 p-3 text-[11px] text-slate-100">{{ json_encode(['old' => $log->old_values, 'new' => $log->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No audit records found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-admin-layout>
