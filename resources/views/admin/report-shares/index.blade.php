<x-admin-layout title="Shared Reports">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Shared Reports</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Report links customers have shared outside Vytte. Anyone holding a live link can read that report without signing in.
        </p>
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card tone="strong" label="Live links" :value="$counts['active']" sub="Readable right now" />
        <x-stat-card tone="moderate" label="Expired" :value="$counts['expired']" sub="Past their expiry date" />
        <x-stat-card tone="slate" label="Revoked" :value="$counts['revoked']" sub="Switched off deliberately" />
        <x-stat-card tone="blue" label="Times opened" :value="$counts['opens']" sub="Across every shared link" />
    </div>

    <x-admin-table
        search-label="Search shared reports"
        search-placeholder="Search by what was assessed"
        :headings="['Report', 'Workspace', 'Opened', 'Expires', 'Status']"
        :paginator="$shareLinks"
        empty="No shared reports match your search"
        empty-hint="Try a different search, or clear the filters.">

        <x-slot:filters>
            <x-admin-filter label="Status" name="status">
                <option value="">Any status</option>
                <option value="active" @selected(request('status') === 'active')>Live</option>
                <option value="expired" @selected(request('status') === 'expired')>Expired</option>
                <option value="revoked" @selected(request('status') === 'revoked')>Revoked</option>
            </x-admin-filter>
        </x-slot:filters>

        @foreach ($shareLinks as $link)
            @php
                $isExpired = $link->expires_at && $link->expires_at->isPast();
                $isLive = $link->is_active && ! $isExpired;
            @endphp
            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                <td class="px-4 py-3">
                    <p class="font-semibold text-slate-900 dark:text-white">{{ $link->assessment?->target?->name ?? 'Unnamed report' }}</p>
                    <p class="mt-0.5 text-xs text-slate-400">Shared {{ $link->created_at?->diffForHumans() }}</p>
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ $link->assessment?->project?->workspace?->name ?? '—' }}
                </td>
                <td class="px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">
                    {{ $link->use_count ?? 0 }} {{ Str::plural('time', $link->use_count ?? 0) }}
                    @if ($link->last_used_at)
                        <p class="text-xs text-slate-400">Last {{ $link->last_used_at->diffForHumans() }}</p>
                    @endif
                </td>
                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                    {{ $link->expires_at?->format('j M Y') ?? 'No expiry date' }}
                </td>
                <td class="px-4 py-3">
                    @if (! $link->is_active)
                        <span class="inline-flex rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200">Revoked</span>
                    @elseif ($isExpired)
                        <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Expired</span>
                    @else
                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">Live</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    @if ($isLive)
                        <form method="POST" action="{{ route('admin.report-shares.revoke', $link) }}"
                              onsubmit="return confirm('Revoke this link? Anyone holding it will immediately lose access to the report.')">
                            @csrf @method('PATCH')
                            <button class="text-sm font-semibold text-slate-500 hover:text-red-600 hover:underline dark:text-slate-400"
                                    data-loading-label="Revoking…">Revoke</button>
                        </form>
                    @else
                        <span class="text-xs text-slate-400">No longer live</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-admin-table>
</x-admin-layout>
