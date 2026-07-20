<x-admin-layout title="Question Group">
    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.question-groups.index') }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-300">← Question groups</a>
            <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $group->group_label }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $group->module?->module_name }} · Group {{ $group->group_number }} · {{ $group->status }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.question-groups.edit', $group) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Edit</a>
            @if ($group->status !== 'ARCHIVED')
                <form method="POST" action="{{ route('admin.question-groups.archive', $group) }}">
                    @csrf
                    @method('PATCH')
                    <button class="rounded-xl border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-700 dark:border-amber-700 dark:text-amber-300" data-loading-label="Archiving…">Archive</button>
                </form>
            @endif
        </div>
    </div>

    <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Questions in this group</h2>
        <div class="mt-3 space-y-3">
            @forelse ($group->questions as $question)
                <a href="{{ route('admin.question-identities.show', $question) }}" class="block rounded-xl bg-slate-50 p-4 dark:bg-slate-900">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $question->question_code }}</p>
                        <span class="text-xs font-bold text-slate-500">{{ $question->questionType?->type_code }} · {{ $question->versions->count() }} versions</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">{{ $question->question_text }}</p>
                </a>
            @empty
                <p class="text-sm text-slate-500">No questions are assigned to this group yet.</p>
            @endforelse
        </div>
    </div>
</x-admin-layout>
