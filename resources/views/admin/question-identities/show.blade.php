<x-admin-layout title="Question">
    <div class="mb-5">
        <a href="{{ route('admin.question-identities.index') }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-300">← Questions</a>
        <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $question->question_code }}</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $question->module?->module_name }} · {{ $question->questionGroup?->group_label ?? 'No group' }} · {{ $question->questionType?->type_code }}</p>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="xl:col-span-2 section-card p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Current identity</h2>
            <p class="mt-3 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $question->question_text }}</p>
            <div class="mt-4 grid grid-cols-2 gap-3 text-xs text-slate-500 md:grid-cols-4">
                <p><span class="font-bold text-slate-700 dark:text-slate-200">Active:</span> {{ $question->is_active ? 'Yes' : 'No' }}</p>
                <p><span class="font-bold text-slate-700 dark:text-slate-200">Scored:</span> {{ $question->is_scored ? 'Yes' : 'No' }}</p>
                <p><span class="font-bold text-slate-700 dark:text-slate-200">Source:</span> {{ $question->source ?? '—' }}</p>
                <p><span class="font-bold text-slate-700 dark:text-slate-200">Alignment:</span> {{ $question->standard_alignment_status ?? '—' }}</p>
            </div>
        </div>
        <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Administrative actions</h2>
            <form method="POST" action="{{ route('admin.questions.toggle', $question) }}" class="mt-3">
                @csrf
                @method('PATCH')
                <button class="btn-secondary w-full">{{ $question->is_active ? 'Disable question' : 'Enable question' }}</button>
                <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                    {{ $question->is_active
                        ? 'Disabling hides this question from new assessments. Assessments already published keep it.'
                        : 'Enabling makes this question available to new assessments again.' }}
                </p>
            </form>
            <form method="POST" action="{{ route('admin.questions.update', $question) }}" class="mt-5">
                @csrf
                @method('PUT')
                <label for="replacement_text" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Create replacement draft wording</label>
                <p class="text-xs text-slate-500 dark:text-slate-400">Published wording can never change. Edit here to start a new draft version instead.</p>
                <textarea id="replacement_text" name="question_text" rows="5" class="mt-1.5 w-full rounded-lg text-sm dark:bg-slate-900 dark:text-white">{{ $question->question_text }}</textarea>
                <button class="btn-primary mt-2 w-full" data-loading-label="Creating…">Create draft version</button>
            </form>
        </div>
    </div>

    <div class="mt-4 section-card p-5 dark:border-slate-700 dark:bg-slate-800">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Versions</h2>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Select a version to open it. Versions are never deleted, so past reports stay reproducible.</p>
        <div class="mt-3 space-y-3">
            @foreach ($question->versions as $version)
                <a href="{{ route('admin.question-versions.show', $version) }}" class="nav-card group block rounded-xl bg-slate-50 p-4 dark:bg-slate-900">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">Version {{ $version->version_number }}</p>
                        <span class="flex items-center gap-2">
                            <x-assessment-status-badge :status="$version->status" />
                            <span class="text-vytte-700 transition-transform group-hover:translate-x-0.5 dark:text-vytte-300" aria-hidden="true">&rarr;</span>
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">{{ $version->question_text }}</p>
                </a>
            @endforeach
        </div>
    </div>
</x-admin-layout>
