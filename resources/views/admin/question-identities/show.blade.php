<x-admin-layout title="Question">
    <div class="mb-5">
        <a href="{{ route('admin.question-identities.index') }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-300">← Questions</a>
        <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $question->question_code }}</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $question->module?->module_name }} · {{ $question->questionGroup?->group_label ?? 'No group' }} · {{ $question->questionType?->type_code }}</p>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="xl:col-span-2 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Current identity</h2>
            <p class="mt-3 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $question->question_text }}</p>
            <div class="mt-4 grid grid-cols-2 gap-3 text-xs text-slate-500 md:grid-cols-4">
                <p><span class="font-bold text-slate-700 dark:text-slate-200">Active:</span> {{ $question->is_active ? 'Yes' : 'No' }}</p>
                <p><span class="font-bold text-slate-700 dark:text-slate-200">Scored:</span> {{ $question->is_scored ? 'Yes' : 'No' }}</p>
                <p><span class="font-bold text-slate-700 dark:text-slate-200">Source:</span> {{ $question->source ?? '—' }}</p>
                <p><span class="font-bold text-slate-700 dark:text-slate-200">Alignment:</span> {{ $question->standard_alignment_status ?? '—' }}</p>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Administrative actions</h2>
            <form method="POST" action="{{ route('admin.questions.toggle', $question) }}" class="mt-3">
                @csrf
                @method('PATCH')
                <button class="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">{{ $question->is_active ? 'Disable question' : 'Enable question' }}</button>
            </form>
            <form method="POST" action="{{ route('admin.questions.update', $question) }}" class="mt-4">
                @csrf
                @method('PUT')
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Create replacement draft wording</label>
                <textarea name="question_text" rows="5" class="mt-2 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ $question->question_text }}</textarea>
                <button class="mt-2 w-full rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">Create draft version</button>
            </form>
        </div>
    </div>

    <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Versions</h2>
        <div class="mt-3 space-y-3">
            @foreach ($question->versions as $version)
                <a href="{{ route('admin.question-versions.show', $version) }}" class="block rounded-xl bg-slate-50 p-4 dark:bg-slate-900">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">Version {{ $version->version_number }}</p>
                        <span class="text-xs font-bold text-slate-500">{{ $version->status }}</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">{{ $version->question_text }}</p>
                </a>
            @endforeach
        </div>
    </div>
</x-admin-layout>
