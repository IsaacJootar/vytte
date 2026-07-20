<x-admin-layout title="People">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">People</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Everyone with a Vytte account, across every workspace.
        </p>
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card tone="blue" label="People" :value="$counts['total']" sub="Accounts in total" />
        <x-stat-card tone="slate" label="Platform admins" :value="$counts['admins']" sub="Can govern Vytte itself" />
        <x-stat-card :tone="$counts['suspended'] > 0 ? 'moderate' : 'strong'" label="Suspended" :value="$counts['suspended']" sub="Blocked from signing in" />
        <x-stat-card tone="blue" label="Invites waiting" :value="$counts['pending']" sub="Sent but not accepted" />
    </div>

    <x-admin-table
        search-label="Search people"
        search-placeholder="Search by name or email address"
        :headings="['Person', 'Workspace', 'Member of', 'Access', 'Status']"
        :paginator="$users"
        empty="No people match your search"
        empty-hint="Try a different search, or clear the filters.">

        <x-slot:filters>
            <x-admin-filter label="Access" name="platform_role">
                <option value="">Everyone</option>
                <option value="PLATFORM_ADMIN" @selected(request('platform_role') === 'PLATFORM_ADMIN')>Platform admins only</option>
            </x-admin-filter>
            <x-admin-filter label="Status" name="status">
                <option value="">Any status</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
            </x-admin-filter>
        </x-slot:filters>

        @foreach ($users as $person)
            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.platform-users.show', $person) }}" class="font-semibold text-slate-900 hover:text-vytte-700 hover:underline dark:text-white dark:hover:text-vytte-300">
                        {{ $person->name }}
                    </a>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $person->email }}</p>
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $person->activeWorkspace?->name ?? '—' }}</td>
                <td class="px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">
                    {{ $person->workspace_memberships_count }} {{ Str::plural('workspace', $person->workspace_memberships_count) }}
                </td>
                <td class="px-4 py-3">
                    @if ($person->isPlatformAdmin())
                        <span class="inline-flex rounded-full bg-vytte-100 px-2.5 py-1 text-xs font-semibold text-vytte-800 dark:bg-vytte-900/40 dark:text-vytte-200">Platform admin</span>
                    @else
                        <span class="text-xs text-slate-500 dark:text-slate-400">Customer</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if ($person->isSuspended())
                        <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Suspended</span>
                    @else
                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">Active</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.platform-users.show', $person) }}" class="link-nav inline-flex items-center gap-1 text-sm">
                        Open <span aria-hidden="true">&rarr;</span>
                    </a>
                </td>
            </tr>
        @endforeach
    </x-admin-table>
</x-admin-layout>
