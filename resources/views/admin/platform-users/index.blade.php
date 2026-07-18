<x-admin-layout title="Platform Users">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Platform Users and Roles</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Assign Vytte Platform Admin authority separately from workspace roles.</p>
    </div>
    <form method="GET" class="mb-4 flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
        <input name="search" value="{{ request('search') }}" placeholder="Search users" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
        <select name="platform_role" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All users</option>
            <option value="PLATFORM_ADMIN" @selected(request('platform_role') === 'PLATFORM_ADMIN')>Vytte Platform Admin</option>
        </select>
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Filter</button>
    </form>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
            <tr><th class="px-4 py-3">User</th><th class="px-4 py-3">Workspace</th><th class="px-4 py-3">Memberships</th><th class="px-4 py-3">Platform role</th><th class="px-4 py-3">Control</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @foreach ($users as $user)
                <tr>
                    <td class="px-4 py-3"><p class="font-semibold text-slate-900 dark:text-white">{{ $user->name }}</p><p class="text-xs text-slate-500">{{ $user->email }}</p></td>
                    <td class="px-4 py-3 text-slate-500">{{ $user->activeWorkspace?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $user->workspace_memberships_count }}</td>
                    <td class="px-4 py-3 text-xs font-bold text-slate-500">{{ $user->platform_role === 'PLATFORM_ADMIN' ? 'Vytte Platform Admin' : 'None' }}</td>
                    <td class="px-4 py-3">
                        <form method="POST" action="{{ route('admin.platform-users.role', $user) }}" class="flex gap-2">
                            @csrf
                            @method('PATCH')
                            <select name="platform_role" class="rounded-lg border-slate-300 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                <option value="" @selected($user->platform_role === null)>No platform role</option>
                                <option value="PLATFORM_ADMIN" @selected($user->platform_role === 'PLATFORM_ADMIN')>Vytte Platform Admin</option>
                            </select>
                            <button class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Save</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</x-admin-layout>
