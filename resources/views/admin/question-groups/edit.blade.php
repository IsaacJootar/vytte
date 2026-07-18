<x-admin-layout title="Edit Question Group">
    <div class="mb-5">
        <a href="{{ route('admin.question-groups.show', $group) }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-300">← Back</a>
        <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">Edit Question Group</h1>
    </div>
    <form method="POST" action="{{ route('admin.question-groups.update', $group) }}" class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
        @csrf
        @method('PUT')
        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Department / scope</label>
        <input value="{{ $group->module?->module_name }}" disabled class="mt-1 w-full rounded-lg border-slate-300 bg-slate-100 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900">
        <input type="hidden" name="module_id" value="{{ $group->module_id }}">
        <label class="mt-4 block text-sm font-semibold text-slate-700 dark:text-slate-200">Display number</label>
        <input name="group_number" type="number" min="1" value="{{ old('group_number', $group->group_number) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
        <label class="mt-4 block text-sm font-semibold text-slate-700 dark:text-slate-200">Group label</label>
        <input name="group_label" value="{{ old('group_label', $group->group_label) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
        <label class="mt-4 block text-sm font-semibold text-slate-700 dark:text-slate-200">Status</label>
        <select name="status" class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="ACTIVE" @selected($group->status === 'ACTIVE')>Active</option>
            <option value="ARCHIVED" @selected($group->status === 'ARCHIVED')>Archived</option>
        </select>
        <div class="mt-5 flex gap-3">
            <button class="rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">Save group</button>
            <a href="{{ route('admin.question-groups.show', $group) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</a>
        </div>
    </form>
</x-admin-layout>
