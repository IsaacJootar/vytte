<x-admin-layout title="Questions">
    <div class="mb-5 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Reusable Question Identities</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Stable official question records; published wording lives in immutable versions.</p>
        </div>
        <a href="{{ route('admin.question-identities.create') }}" class="rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">New question</a>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
        <input name="search" value="{{ request('search') }}" placeholder="Search code or text" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
        <select name="module_id" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All departments</option>
            @foreach ($modules as $module)
                <option value="{{ $module->module_id }}" @selected(request('module_id') == $module->module_id)>{{ $module->module_name }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All status</option>
            @foreach (['DRAFT', 'PUBLISHED', 'ARCHIVED'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
            <tr>
                <th class="px-4 py-3">Question</th>
                <th class="px-4 py-3">Department / group</th>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Versions</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse ($questions as $question)
                <tr>
                    <td class="px-4 py-3">
                        <p class="font-semibold text-slate-900 dark:text-white">{{ $question->question_code }}</p>
                        <p class="mt-1 max-w-xl text-xs text-slate-500">{{ $question->question_text }}</p>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-500">{{ $question->module?->module_name }}<br>{{ $question->questionGroup?->group_label }}</td>
                    <td class="px-4 py-3 text-xs font-bold text-slate-500">{{ $question->questionType?->type_code }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $question->versions_count }}</td>
                    <td class="px-4 py-3 text-xs font-bold text-slate-500">{{ $question->question_status ?? 'DRAFT' }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.question-identities.show', $question) }}" class="text-sm font-semibold text-vytte-700 dark:text-vytte-300">Open</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No questions found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $questions->links() }}</div>
</x-admin-layout>
