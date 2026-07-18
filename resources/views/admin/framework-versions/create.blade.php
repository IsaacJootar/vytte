<x-admin-layout title="New Framework">
    <div class="mb-5">
        <a href="{{ route('admin.framework-versions.index') }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-300">← Frameworks</a>
        <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">Create framework version</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Create a draft department or focused framework, then add sections, indicators, and placements.</p>
    </div>

    <form method="POST" action="{{ route('admin.framework-versions.store') }}" class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
        @csrf
        <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Department / scope
                <select name="module_id" class="mt-1 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                    @foreach ($modules as $module)
                        <option value="{{ $module->module_id }}">{{ $module->module_name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Framework type
                <select name="framework_type" class="mt-1 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                    <option value="DEPARTMENT">Department</option>
                    <option value="FOCUSED">Focused assessment</option>
                </select>
            </label>
        </div>
        <label class="mt-4 block text-sm font-semibold text-slate-700 dark:text-slate-200">Display name
            <input name="display_name" class="mt-1 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
        </label>
        <label class="mt-4 block text-sm font-semibold text-slate-700 dark:text-slate-200">Description
            <textarea name="description" rows="2" class="mt-1 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
        </label>
        <label class="mt-4 block text-sm font-semibold text-slate-700 dark:text-slate-200">Purpose
            <textarea name="purpose" rows="2" class="mt-1 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
        </label>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Source authority
                <input name="source_authority" class="mt-1 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
            </label>
            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">License code
                <input name="license_code" class="mt-1 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
            </label>
        </div>
        <label class="mt-4 block text-sm font-semibold text-slate-700 dark:text-slate-200">Source URL
            <input name="source_url" type="url" class="mt-1 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
        </label>
        <div class="mt-5 flex justify-end">
            <button class="rounded-xl bg-vytte-700 px-5 py-2 text-sm font-bold text-white">Create draft</button>
        </div>
    </form>
</x-admin-layout>
