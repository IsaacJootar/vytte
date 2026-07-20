<x-admin-layout title="Respondent preview">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.assessments.review', $assessment) }}" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Back to review</a>
            <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Respondent preview</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $assessment->display_name }}</p>
        </div>
        <x-assessment-status-badge :status="$assessment->status" />
    </div>

    <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-900 dark:bg-sky-950">
        <p class="text-sm font-semibold text-sky-900 dark:text-sky-100">This is how the assessment will look to the person answering it</p>
        <p class="mt-1 text-sm text-sky-800 dark:text-sky-200">
            Nothing here can be answered or changed. It is a preview only.
            {{ $isFrozen
                ? 'It shows the exact frozen content that was published.'
                : 'It shows the current draft, which will be frozen exactly as it appears here when you publish.' }}
        </p>
    </div>

    <div class="max-w-3xl space-y-5">
        @forelse ($sections as $section)
            <div class="section-card">
                <div class="border-b border-slate-100 p-5 dark:border-slate-700">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ $section['section_name'] }}</h2>
                    @if (! empty($section['purpose']))
                        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $section['purpose'] }}</p>
                    @endif
                </div>

                <ol class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse ($questionsBySection[$section['framework_section_id']] ?? [] as $index => $question)
                        <li class="p-5">
                            <p class="text-sm font-medium text-slate-900 dark:text-white">
                                {{ $index + 1 }}. {{ $question['question_text'] }}
                                @if ($question['is_required'] ?? false)
                                    <span class="text-red-500" aria-label="required">*</span>
                                @endif
                            </p>

                            @if (! empty($question['help_text']))
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $question['help_text'] }}</p>
                            @endif

                            <div class="mt-3">
                                @if (in_array($question['response_type'] ?? null, ['SINGLE_SELECT', 'LIKERT'], true))
                                    <div class="space-y-2">
                                        @foreach ($question['options'] ?? [] as $option)
                                            <label class="flex cursor-not-allowed items-center gap-2.5 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:text-slate-200">
                                                <input type="radio" disabled class="border-slate-300 dark:border-slate-600">
                                                {{ $option['option_label'] }}
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif (($question['response_type'] ?? null) === 'NUMERIC')
                                    <div class="flex items-center gap-2">
                                        <input type="number" disabled placeholder="Enter a number"
                                               class="w-48 rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900">
                                        @if (! empty($question['numeric_config']['unit']))
                                            <span class="text-sm text-slate-500 dark:text-slate-400">{{ $question['numeric_config']['unit'] }}</span>
                                        @endif
                                    </div>
                                    @if (isset($question['numeric_config']['min']) || isset($question['numeric_config']['max']))
                                        <p class="mt-1 text-xs text-slate-400">
                                            Allowed range: {{ $question['numeric_config']['min'] ?? '—' }} to {{ $question['numeric_config']['max'] ?? '—' }}
                                        </p>
                                    @endif
                                @else
                                    <textarea rows="2" disabled placeholder="Written answer"
                                              class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"></textarea>
                                @endif
                            </div>

                            @if (! empty($question['evidence_expectation']))
                                <div class="mt-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-900/40">
                                    <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">Supporting note</p>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $question['evidence_expectation'] }}</p>
                                    <textarea rows="2" disabled class="mt-2 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"></textarea>
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">No questions in this section.</li>
                    @endforelse
                </ol>
            </div>
        @empty
            <div class="section-card p-10 text-center dark:border-slate-700 dark:bg-slate-800">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Nothing to preview yet</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Add sections and questions first.</p>
            </div>
        @endforelse
    </div>
</x-admin-layout>
