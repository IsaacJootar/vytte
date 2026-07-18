<x-admin-layout title="New Question Group">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Create Question Group</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">A question group belongs to one official department or focused scope.</p>
    </div>
    <form method="POST" action="{{ route('admin.question-groups.store') }}" class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
        @csrf
        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Department / scope</label>
        <select name="module_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
            @foreach ($modules as $module)
                <option value="{{ $module->module_id }}">{{ $module->module_name }}</option>
            @endforeach
        </select>
        <label class="mt-4 block text-sm font-semibold text-slate-700 dark:text-slate-200">Display number</label>
        <input name="group_number" type="number" min="1" value="{{ old('group_number', 1) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
        <label class="mt-4 block text-sm font-semibold text-slate-700 dark:text-slate-200">Group label</label>
        <input name="group_label" value="{{ old('group_label') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
        <div class="mt-5 flex gap-3">
            <button class="rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">Create group</button>
            <a href="{{ route('admin.question-groups.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</a>
        </div>
    </form>
</x-admin-layout>
