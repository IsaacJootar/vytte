<x-admin-layout title="Report Shares">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Report Share-Link Control</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Platform-wide oversight and emergency revocation for shared immutable reports.</p>
    </div>
    <form method="GET" class="mb-4 flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
        <select name="status" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All links</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="revoked" @selected(request('status') === 'revoked')>Revoked</option>
            <option value="expired" @selected(request('status') === 'expired')>Expired</option>
        </select>
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Filter</button>
    </form>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
            <tr><th class="px-4 py-3">Report</th><th class="px-4 py-3">Workspace</th><th class="px-4 py-3">Usage</th><th class="px-4 py-3">Expires</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse ($shareLinks as $shareLink)
                <tr>
                    <td class="px-4 py-3"><p class="font-semibold text-slate-900 dark:text-white">{{ $shareLink->assessment?->target?->name ?? 'Report' }}</p><p class="text-xs font-mono text-slate-500">{{ $shareLink->link_id }}</p></td>
                    <td class="px-4 py-3 text-slate-500">{{ $shareLink->assessment?->project?->workspace?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-xs text-slate-500">{{ $shareLink->use_count }} views · last {{ $shareLink->last_used_at?->diffForHumans() ?? 'never' }}</td>
                    <td class="px-4 py-3 text-xs text-slate-500">{{ $shareLink->expires_at?->format('Y-m-d') ?? 'No expiry' }}</td>
                    <td class="px-4 py-3 text-xs font-bold text-slate-500">{{ $shareLink->is_active ? ($shareLink->expires_at?->isPast() ? 'EXPIRED' : 'ACTIVE') : 'REVOKED' }}</td>
                    <td class="px-4 py-3 text-right">
                        @if ($shareLink->is_active)
                            <form method="POST" action="{{ route('admin.report-shares.revoke', $shareLink) }}">
                                @csrf
                                @method('PATCH')
                                <button class="text-xs font-bold text-red-600 dark:text-red-400">Revoke</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No share links found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $shareLinks->links() }}</div>
</x-admin-layout>
