<x-admin-layout title="Question Library">
    <div class="mb-5">
        <a href="{{ route('admin.assessments.build', $assessment) }}" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Back to {{ $assessment->display_name }}</a>
        <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Question Library</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Official Vytte questions, ready to reuse. Adding one to <span class="font-semibold">{{ $section->section_name }}</span>.
        </p>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950">
            <ul class="list-disc space-y-1 pl-5 text-sm text-red-700 dark:text-red-300">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
        <input name="search" value="{{ request('search') }}" placeholder="Search question wording"
               class="min-w-[16rem] flex-1 rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
        <select name="department" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All departments</option>
            @foreach ($departments as $department)
                <option value="{{ $department->module_id }}" @selected((int) request('department') === (int) $department->module_id)>{{ $department->module_name }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
            <input type="checkbox" name="unused_only" value="1" @checked(request()->boolean('unused_only')) class="rounded border-slate-300 dark:border-slate-600">
            Hide questions already used
        </label>
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Search</button>
    </form>

    <div class="space-y-3">
        @forelse ($versions as $version)
            @php $alreadyUsed = in_array($version->question_id, $usedQuestionIds, true); @endphp
            <div class="flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $version->question_text }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 font-semibold dark:bg-slate-700">
                            {{ \App\Support\AnswerFormat::labelForTypeCode($version->questionType?->type_code, $version->options ?? []) }}
                        </span>
                        <span>{{ $version->question?->module?->module_name }}</span>
                        @if ($version->requires_observation)
                            <span class="rounded-full bg-sky-100 px-2 py-0.5 font-semibold text-sky-800 dark:bg-sky-900/40 dark:text-sky-200">Needs observation</span>
                        @endif
                    </div>
                    @if (filled($version->options))
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            Answers: {{ collect($version->options)->pluck('option_label')->filter()->join(' · ') }}
                        </p>
                    @endif
                </div>

                <div class="shrink-0">
                    @if ($alreadyUsed)
                        <span class="inline-block rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-500 dark:bg-slate-700 dark:text-slate-300">Already used</span>
                    @else
                        <form method="POST" action="{{ route('admin.assessments.questions.add-from-library', [$assessment, $section]) }}">
                            @csrf
                            <input type="hidden" name="question_version_id" value="{{ $version->question_version_id }}">
                            <button class="rounded-xl bg-vytte-600 px-4 py-2 text-sm font-semibold text-white hover:bg-vytte-700">Add to section</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center dark:border-slate-700 dark:bg-slate-800">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No questions match your search</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Try a different search, or write a new question.</p>
                <a href="{{ route('admin.assessments.questions.create', [$assessment, $section]) }}" class="mt-4 inline-block rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">
                    Write a new question
                </a>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $versions->links() }}</div>
</x-admin-layout>
