<x-admin-layout title="Activity">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Activity</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Everything that has happened on Vytte — who did it, when, and where. This record can never be edited or deleted.
        </p>
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <x-stat-card tone="blue" label="Today" :value="$counts['today']" sub="Events recorded since midnight" />
        <x-stat-card tone="slate" label="This week" :value="$counts['week']" sub="Events in the last 7 days" />
        <x-stat-card tone="strong" label="All time" :value="$counts['total']" sub="Permanent record" />
    </div>

    <x-admin-table
        search-label="Search activity"
        search-placeholder="Search by what happened, or who did it"
        :headings="['What happened', 'Who', 'Where', 'When']"
        :paginator="$logs"
        empty="No activity matches your search"
        empty-hint="Try a different search, a wider date range, or clear the filters.">

        <x-slot:filters>
            <x-admin-filter label="Kind" name="category">
                <option value="">Everything</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                @endforeach
            </x-admin-filter>
            <x-admin-filter label="Workspace" name="workspace_id">
                <option value="">All workspaces</option>
                @foreach ($workspaces as $workspace)
                    <option value="{{ $workspace->workspace_id }}" @selected(request('workspace_id') === $workspace->workspace_id)>{{ $workspace->name }}</option>
                @endforeach
            </x-admin-filter>
            <x-admin-filter label="When" name="since">
                <option value="">Any time</option>
                <option value="today" @selected(request('since') === 'today')>Today</option>
                <option value="week" @selected(request('since') === 'week')>Last 7 days</option>
                <option value="month" @selected(request('since') === 'month')>Last 30 days</option>
            </x-admin-filter>
        </x-slot:filters>

        @foreach ($logs as $log)
            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                <td class="px-4 py-3">
                    <p class="font-medium text-slate-900 dark:text-white">{{ \App\Support\AuditEventLabel::for($log->event) }}</p>
                    <p class="mt-0.5 text-xs text-slate-400">{{ \App\Support\AuditEventLabel::categoryFor($log->event) }}</p>
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ $log->user?->name ?? 'Vytte itself' }}
                    @if ($log->user)
                        <p class="text-xs text-slate-400">{{ $log->user->email }}</p>
                    @endif
                </td>
                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                    {{ $log->ip_address ?? '—' }}
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                    {{ $log->created_at?->format('j M Y, H:i') }}
                    <p class="text-slate-400">{{ $log->created_at?->diffForHumans() }}</p>
                </td>
            </tr>
        @endforeach
    </x-admin-table>
</x-admin-layout>
