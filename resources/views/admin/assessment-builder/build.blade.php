<x-admin-layout :title="$assessment->display_name">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.assessments.show', $assessment) }}" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Back to assessment</a>
            <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Build Assessment</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ $assessment->display_name }} · {{ $assessment->sections->count() }} {{ Str::plural('section', $assessment->sections->count()) }} · {{ $questionCount }} {{ Str::plural('question', $questionCount) }}
            </p>
        </div>
        <x-assessment-status-badge :status="$assessment->status" />
    </div>

    <x-assessment-wizard-steps :steps="$steps" :current-step="$currentStep" />

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950">
            <ul class="list-disc space-y-1 pl-5 text-sm text-red-700 dark:text-red-300">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @unless ($isEditable)
        <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">This assessment is published and cannot be changed</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Create a new version to make changes.</p>
        </div>
    @endunless

    <div class="space-y-4">
        @forelse ($assessment->sections as $section)
            <div class="rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 p-5 dark:border-slate-700">
                    <div class="min-w-0">
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ $section->section_name }}</h2>
                        @if ($section->purpose)
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $section->purpose }}</p>
                        @endif
                        <p class="mt-1 text-xs text-slate-400">{{ $section->questionPlacements->count() }} {{ Str::plural('question', $section->questionPlacements->count()) }}</p>
                    </div>

                    @if ($isEditable)
                        <div class="flex flex-wrap items-center gap-1.5">
                            @if (! $loop->first)
                                <form method="POST" action="{{ route('admin.assessments.sections.move', [$assessment, $section]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="direction" value="up">
                                    <button class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300" aria-label="Move {{ $section->section_name }} up">↑</button>
                                </form>
                            @endif
                            @if (! $loop->last)
                                <form method="POST" action="{{ route('admin.assessments.sections.move', [$assessment, $section]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="direction" value="down">
                                    <button class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300" aria-label="Move {{ $section->section_name }} down">↓</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.assessments.sections.destroy', [$assessment, $section]) }}"
                                  onsubmit="return confirm('Remove the section “{{ $section->section_name }}”?')">
                                @csrf @method('DELETE')
                                <button class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300">Remove</button>
                            </form>
                        </div>
                    @endif
                </div>

                <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse ($section->questionPlacements as $placement)
                        <li class="flex flex-wrap items-start justify-between gap-3 px-5 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-slate-800 dark:text-slate-100">{{ $placement->local_display_text ?: $placement->questionVersion?->question_text }}</p>
                                <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 font-semibold dark:bg-slate-700">
                                        {{ \App\Support\AnswerFormat::labelForTypeCode($placement->questionVersion?->questionType?->type_code, $placement->questionVersion?->options ?? []) }}
                                    </span>
                                    @if ($placement->scoring_contribution)
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">Scored</span>
                                        @if ((float) $placement->weight >= 2)
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 dark:bg-slate-700">Counts double</span>
                                        @endif
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 dark:bg-slate-700">Not scored</span>
                                    @endif
                                    @if ($placement->criticality === 'CRITICAL')
                                        <span class="rounded-full bg-red-100 px-2 py-0.5 font-semibold text-red-800 dark:bg-red-900/40 dark:text-red-200">Critical</span>
                                    @endif
                                    @if ($placement->evidence_expectation)
                                        <span class="rounded-full bg-sky-100 px-2 py-0.5 font-semibold text-sky-800 dark:bg-sky-900/40 dark:text-sky-200">Asks for a note</span>
                                    @endif
                                    @if ($placement->questionVersion?->status !== \App\Models\QuestionVersion::STATUS_PUBLISHED)
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Needs approval</span>
                                    @endif
                                </p>
                            </div>

                            @if ($isEditable)
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <a href="{{ route('admin.assessments.questions.settings', [$assessment, $placement]) }}"
                                       class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200">
                                        Scoring &amp; evidence
                                    </a>
                                    @if ($placement->questionVersion?->status !== \App\Models\QuestionVersion::STATUS_PUBLISHED)
                                        <form method="POST" action="{{ route('admin.assessments.questions.approve', [$assessment, $placement]) }}"
                                              onsubmit="return confirm('Approve this question? Its wording and answers are locked permanently once approved.')">
                                            @csrf @method('PATCH')
                                            <button class="rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">Approve</button>
                                        </form>
                                    @endif
                                    @if (! $loop->first)
                                        <form method="POST" action="{{ route('admin.assessments.questions.move', [$assessment, $placement]) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="direction" value="up">
                                            <button class="rounded-lg border border-slate-300 px-2 py-1 text-xs text-slate-600 dark:border-slate-600 dark:text-slate-300" aria-label="Move question up">↑</button>
                                        </form>
                                    @endif
                                    @if (! $loop->last)
                                        <form method="POST" action="{{ route('admin.assessments.questions.move', [$assessment, $placement]) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="direction" value="down">
                                            <button class="rounded-lg border border-slate-300 px-2 py-1 text-xs text-slate-600 dark:border-slate-600 dark:text-slate-300" aria-label="Move question down">↓</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.assessments.questions.destroy', [$assessment, $placement]) }}"
                                          onsubmit="return confirm('Remove this question from the assessment?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg border border-slate-300 px-2 py-1 text-xs text-slate-600 dark:border-slate-600 dark:text-slate-300">Remove</button>
                                    </form>
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">No questions in this section yet.</li>
                    @endforelse
                </ul>

                @if ($isEditable)
                    <div class="flex flex-wrap gap-2 border-t border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/40">
                        <a href="{{ route('admin.assessments.questions.library', [$assessment, $section]) }}"
                           class="rounded-xl bg-vytte-600 px-4 py-2 text-sm font-semibold text-white hover:bg-vytte-700">
                            + Add from Question Library
                        </a>
                        <a href="{{ route('admin.assessments.questions.create', [$assessment, $section]) }}"
                           class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white dark:border-slate-600 dark:text-slate-200">
                            Write a new question
                        </a>
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center dark:border-slate-700 dark:bg-slate-800">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No sections yet</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Sections group related questions, for example Leadership or Infection Control.</p>
            </div>
        @endforelse
    </div>

    @if ($isEditable)
        <form method="POST" action="{{ route('admin.assessments.sections.store', $assessment) }}"
              class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            @csrf
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Add a section</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                <input name="section_name" value="{{ old('section_name') }}" required maxlength="180" placeholder="Section name, e.g. Infection Control"
                       class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <input name="purpose" value="{{ old('purpose') }}" maxlength="1000" placeholder="Short description (optional)"
                       class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <button class="rounded-xl bg-vytte-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700">Add section</button>
            </div>
        </form>
    @endif
</x-admin-layout>
