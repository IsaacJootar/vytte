<x-admin-layout title="Question Version">
    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.question-versions.index') }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-300">← Question versions</a>
            <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $version->question?->question_code }} · v{{ $version->version_number }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $version->question?->module?->module_name }} · {{ $version->questionType?->type_code }} · {{ $version->status }}</p>
        </div>
        <div class="flex gap-2">
            @if (in_array($version->status, ['DRAFT', 'INTERNAL_REVIEW'], true))
                <form method="POST" action="{{ route('admin.question-versions.approve', $version) }}">
                    @csrf
                    @method('PATCH')
                    <button class="rounded-xl border border-green-300 px-4 py-2 text-sm font-semibold text-green-700 dark:border-green-700 dark:text-green-300">Approve</button>
                </form>
            @endif
            @if ($version->status === 'APPROVED')
                <form method="POST" action="{{ route('admin.question-versions.publish', $version) }}">
                    @csrf
                    @method('PATCH')
                    <button class="rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">Publish immutable version</button>
                </form>
            @endif
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="xl:col-span-2 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Frozen content</h2>
            <p class="mt-3 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $version->question_text }}</p>
            <dl class="mt-5 grid gap-3 text-xs text-slate-500 md:grid-cols-2">
                <div><dt class="font-bold text-slate-700 dark:text-slate-200">Respondent role</dt><dd>{{ $version->respondent_role_hint ?? '—' }}</dd></div>
                <div><dt class="font-bold text-slate-700 dark:text-slate-200">Requires observation</dt><dd>{{ $version->requires_observation ? 'Yes' : 'No' }}</dd></div>
                <div><dt class="font-bold text-slate-700 dark:text-slate-200">Effective date</dt><dd>{{ $version->effective_date?->format('Y-m-d') ?? '—' }}</dd></div>
                <div><dt class="font-bold text-slate-700 dark:text-slate-200">Content hash</dt><dd class="break-all">{{ $version->content_hash ?? 'Not published' }}</dd></div>
            </dl>
        </div>
        <div class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Methodology</h2>
                <p class="mt-2 text-sm text-slate-500">{{ $version->methodology_notes ?? 'No methodology notes provided.' }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Source summary</h2>
                <p class="mt-2 text-sm text-slate-500">{{ $version->source_summary ?? 'No source summary provided.' }}</p>
            </div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Options</h2>
            <pre class="mt-3 overflow-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100">{{ json_encode($version->options, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'None' }}</pre>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Numeric scoring</h2>
            <pre class="mt-3 overflow-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100">{{ json_encode(['config' => $version->numeric_config, 'bands' => $version->numeric_bands], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </div>
</x-admin-layout>
