<x-admin-layout title="New Question">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Create Reusable Question</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">This creates the stable identity plus its first draft version.</p>
    </div>
    <form method="POST" action="{{ route('admin.question-identities.store') }}" class="max-w-3xl section-card p-5 dark:border-slate-700 dark:bg-slate-800">
        @csrf
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Department / scope</label>
                <select name="module_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                    @foreach ($modules as $module)
                        <option value="{{ $module->module_id }}">{{ $module->module_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Question group</label>
                <select name="question_group_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    <option value="">No group yet</option>
                    @foreach ($modules as $module)
                        @foreach ($module->questionGroups as $group)
                            <option value="{{ $group->question_group_id }}">{{ $module->module_name }} — {{ $group->group_label }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Question code</label>
                <input name="question_code" value="{{ old('question_code') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Response type</label>
                <select name="type_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                    @foreach ($questionTypes as $type)
                        <option value="{{ $type->type_id }}">{{ $type->type_code }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <label class="mt-4 block text-sm font-semibold text-slate-700 dark:text-slate-200">Question text</label>
        <textarea name="question_text" rows="4" class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>{{ old('question_text') }}</textarea>
        <div class="mt-4 grid gap-4 md:grid-cols-4">
            <input name="numeric_unit" value="{{ old('numeric_unit') }}" placeholder="Numeric unit" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <input name="numeric_min" value="{{ old('numeric_min') }}" placeholder="Min" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <input name="numeric_max" value="{{ old('numeric_max') }}" placeholder="Max" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <input name="numeric_step" value="{{ old('numeric_step') }}" placeholder="Step" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
        </div>
        <label class="mt-4 block text-sm font-semibold text-slate-700 dark:text-slate-200">Respondent role hint</label>
        <input name="respondent_role_hint" value="{{ old('respondent_role_hint') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
        <label class="mt-4 flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
            <input type="checkbox" name="is_scored" value="1" checked class="rounded border-slate-300">
            Scored question
        </label>
        <label class="mt-4 block text-sm font-semibold text-slate-700 dark:text-slate-200">Methodology notes</label>
        <textarea name="methodology_notes" rows="2" class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ old('methodology_notes') }}</textarea>
        <label class="mt-4 block text-sm font-semibold text-slate-700 dark:text-slate-200">Source summary</label>
        <textarea name="source_summary" rows="2" class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ old('source_summary') }}</textarea>
        <div class="mt-5 flex gap-3">
            <button class="rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">Create question</button>
            <a href="{{ route('admin.question-identities.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</a>
        </div>
    </form>
</x-admin-layout>
