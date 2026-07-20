<x-admin-layout :title="$assessment->display_name">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.assessments.index') }}" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Back to assessments</a>
            <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ $assessment->display_name }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ $assessment->module?->module_name ?? 'No department' }} · Version {{ $assessment->version_number }}
            </p>
        </div>
        <x-assessment-status-badge :status="$assessment->status" />
    </div>

    <x-assessment-wizard-steps :steps="$steps" :current-step="$currentStep" />

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="section-card p-6 dark:border-slate-700 dark:bg-slate-800">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Basic Information</h2>
                    @if ($isEditable)
                        <a href="{{ route('admin.assessments.edit', $assessment) }}" class="text-sm font-semibold text-vytte-700 hover:underline dark:text-vytte-300">Edit</a>
                    @endif
                </div>
                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Description</dt>
                        <dd class="mt-0.5 text-slate-700 dark:text-slate-200">{{ $assessment->description ?: 'Not set' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Department or category</dt>
                        <dd class="mt-0.5 text-slate-700 dark:text-slate-200">{{ $assessment->module?->module_name ?? 'Not set' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Intended use</dt>
                        <dd class="mt-0.5 text-slate-700 dark:text-slate-200">{{ $assessment->purpose ?: 'Not set' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="section-card p-6 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Build Assessment</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Add sections and the questions people will answer.
                </p>
                <a href="{{ route('admin.assessments.build', $assessment) }}"
                   class="mt-3 inline-block rounded-xl bg-vytte-600 px-4 py-2 text-sm font-semibold text-white hover:bg-vytte-700">
                    {{ $isEditable ? 'Add sections and questions' : 'View sections and questions' }}
                </a>
            </div>
        </div>

        <div class="space-y-4">
            <div class="section-card p-6 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Contents</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">Sections</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white">{{ $assessment->sections_count }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">Questions</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white">{{ $assessment->question_placements_count }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">Last updated</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white">{{ $assessment->updated_at?->diffForHumans() }}</dd>
                    </div>
                </dl>
            </div>

            @unless ($isEditable)
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6 dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">This assessment is locked</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                        Published assessments cannot be changed, so that reports already produced from them stay reproducible.
                        To make changes, create a new version.
                    </p>
                </div>
            @endunless

            @if ($isEditable)
                <div class="section-card p-6 dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Discard this draft</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Removes this draft and everything in it. Published assessments are never removed.
                    </p>
                    <form method="POST" action="{{ route('admin.assessments.destroy', $assessment) }}" class="mt-3"
                          onsubmit="return confirm('Discard the draft “{{ $assessment->display_name }}”? This cannot be undone.')">
                        @csrf @method('DELETE')
                        <button class="rounded-xl border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950">
                            Discard draft
                        </button>
                    </form>
                </div>
            @endif

            <div class="section-card p-6 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Advanced Tools</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Full governance detail for this assessment, including version history and structure.
                </p>
                <a href="{{ route('admin.framework-versions.show', $assessment) }}" class="mt-3 inline-block text-sm font-semibold text-vytte-700 hover:underline dark:text-vytte-300">
                    Open governance view
                </a>
            </div>
        </div>
    </div>
</x-admin-layout>
