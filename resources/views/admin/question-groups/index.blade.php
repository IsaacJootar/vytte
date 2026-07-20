<x-admin-layout title="Question Groups">
    <div class="mb-5 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Question Groups</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Official groupings used inside Vytte departments and focused scopes.</p>
        </div>
        <a href="{{ route('admin.question-groups.create') }}" class="rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">New group</a>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-3 section-card p-4 dark:border-slate-700 dark:bg-slate-800">
        <select name="module_id" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All departments</option>
            @foreach ($modules as $module)
                <option value="{{ $module->module_id }}" @selected(request('module_id') == $module->module_id)>{{ $module->module_name }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All status</option>
            <option value="ACTIVE" @selected(request('status') === 'ACTIVE')>Active</option>
            <option value="ARCHIVED" @selected(request('status') === 'ARCHIVED')>Archived</option>
        </select>
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Filter</button>
    </form>

    <div class="section-card">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
            <tr>
                <th class="px-4 py-3">Group</th>
                <th class="px-4 py-3">Department / Scope</th>
                <th class="px-4 py-3">Questions</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse ($groups as $group)
                <tr>
                    <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $group->group_number }}. {{ $group->group_label }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $group->module?->module_name }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $group->questions_count }}</td>
                    <td class="px-4 py-3 text-xs font-bold text-slate-500">{{ $group->status }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.question-groups.show', $group) }}" class="text-sm font-semibold text-vytte-700 dark:text-vytte-300">Open</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No question groups found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $groups->links() }}</div>
</x-admin-layout>
