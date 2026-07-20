<x-admin-layout title="Workspaces">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Workspaces</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Every customer account on Vytte, and how each one is doing.
        </p>
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card tone="blue" label="Workspaces" :value="$counts['total']" sub="Customer accounts in total" />
        <x-stat-card tone="strong" label="Active" :value="$counts['active']" sub="Able to be used today" />
        <x-stat-card tone="moderate" label="On hold" :value="$counts['suspended']" sub="Suspended by Vytte" />
        <x-stat-card tone="slate" label="Closed" :value="$counts['archived']" sub="Archived, data kept" />
    </div>

    <x-admin-table
        search-label="Search workspaces"
        search-placeholder="Search by workspace name"
        :headings="['Workspace', 'Owner', 'Plan', 'People', 'Projects', 'Status']"
        :paginator="$workspaces"
        empty="No workspaces match your search"
        empty-hint="Try a different search, or clear the filters.">

        <x-slot:filters>
            <x-admin-filter label="Plan" name="plan">
                <option value="">All plans</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan }}" @selected(request('plan') === $plan)>{{ ucfirst(strtolower($plan)) }}</option>
                @endforeach
            </x-admin-filter>
            <x-admin-filter label="Status" name="status">
                <option value="">Any status</option>
                <option value="ACTIVE" @selected(request('status') === 'ACTIVE')>Active</option>
                <option value="SUSPENDED" @selected(request('status') === 'SUSPENDED')>On hold</option>
                <option value="ARCHIVED" @selected(request('status') === 'ARCHIVED')>Closed</option>
            </x-admin-filter>
        </x-slot:filters>

        @foreach ($workspaces as $workspace)
            @php $owner = $workspace->ownerMember->first()?->user; @endphp
            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.workspaces.show', $workspace) }}" class="font-semibold text-slate-900 hover:text-vytte-700 hover:underline dark:text-white dark:hover:text-vytte-300">
                        {{ $workspace->name }}
                    </a>
                    <p class="mt-0.5 text-xs text-slate-400">Joined {{ $workspace->created_at?->format('j M Y') }}</p>
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ $owner?->name ?? 'No owner' }}
                    @if ($owner)
                        <p class="text-xs text-slate-400">{{ $owner->email }}</p>
                    @endif
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ ucfirst(strtolower($workspace->plan ?? 'free')) }}</td>
                <td class="px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">{{ $workspace->members_count }}</td>
                <td class="px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">{{ $workspace->projects_count }}</td>
                <td class="px-4 py-3"><x-workspace-status-badge :status="$workspace->status" /></td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.workspaces.show', $workspace) }}" class="link-nav inline-flex items-center gap-1 text-sm">
                        Open <span aria-hidden="true">&rarr;</span>
                    </a>
                </td>
            </tr>
        @endforeach
    </x-admin-table>
</x-admin-layout>
