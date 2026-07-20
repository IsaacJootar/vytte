<x-admin-layout title="Question settings">
    <div class="mb-5">
        <a href="{{ route('admin.assessments.build', $assessment) }}" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Back to {{ $assessment->display_name }}</a>
        <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Scoring and evidence</h1>
        <p class="mt-1 max-w-2xl text-sm text-slate-700 dark:text-slate-200">{{ $version?->question_text }}</p>
        <p class="text-xs text-slate-500 dark:text-slate-400">In {{ $placement->section?->section_name }}</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 max-w-2xl rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950">
            <p class="text-sm font-semibold text-red-800 dark:text-red-200">Please fix the following:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700 dark:text-red-300">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($scoringGroups->isEmpty())
        <div class="mb-4 max-w-2xl rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950">
            <p class="text-sm font-semibold text-amber-900 dark:text-amber-100">This department has no score yet</p>
            <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">
                Questions can only affect a score once the department has one. Create a score to continue, or leave questions unscored for now.
            </p>
            @if ($isEditable)
                <form method="POST" action="{{ route('admin.assessments.scoring-groups.store', $assessment) }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                    @csrf
                    <input name="name" value="{{ old('name') }}" required maxlength="120" placeholder="Score name, e.g. Outpatient Readiness"
                           class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    <select name="domain_id" required class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        <option value="">What area does it measure?</option>
                        @foreach ($domains as $domain)
                            <option value="{{ $domain->domain_id }}" @selected((int) old('domain_id') === (int) $domain->domain_id)>{{ $domain->domain_name }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-xl bg-vytte-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700">Create score</button>
                </form>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('admin.assessments.questions.settings.save', [$assessment, $placement]) }}"
          x-data="{ scored: {{ old('is_scored', $placement->scoring_contribution) ? 'true' : 'false' }}, evidence: '{{ old('evidence_mode', $placement->evidence_expectation ? 'note' : 'none') }}' }"
          class="max-w-2xl space-y-6 section-card p-6 dark:border-slate-700 dark:bg-slate-800">
        @csrf
        @method('PUT')

        <fieldset>
            <legend class="text-sm font-semibold text-slate-700 dark:text-slate-200">Should this question affect the score?</legend>
            @if ($typeCode === 'OPEN_ENDED')
                <p class="mt-2 rounded-xl bg-slate-50 p-3 text-sm text-slate-600 dark:bg-slate-900/40 dark:text-slate-300">
                    Written answers are always kept as supporting context and are never scored.
                </p>
                <input type="hidden" name="is_scored" value="0">
            @else
                <div class="mt-2 flex gap-4">
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input type="radio" name="is_scored" value="1" x-model="scored" @checked(old('is_scored', $placement->scoring_contribution)) class="border-slate-300 text-vytte-600"> Yes
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input type="radio" name="is_scored" value="0" x-model="scored" @checked(! old('is_scored', $placement->scoring_contribution)) class="border-slate-300 text-vytte-600"> No
                    </label>
                </div>
            @endif
        </fieldset>

        <template x-if="scored == '1' || scored === true">
            <div class="space-y-6">
                @if ($scoringGroups->count() > 1)
                    <div>
                        <label for="scoring_group_id" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Which score should this contribute to?</label>
                        <select id="scoring_group_id" name="scoring_group_id" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                            @foreach ($scoringGroups as $group)
                                <option value="{{ $group->sub_index_id }}" @selected((int) old('scoring_group_id', $placement->sub_index_id) === (int) $group->sub_index_id)>{{ $group->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($scoringGroups->count() === 1)
                    <p class="rounded-xl bg-slate-50 p-3 text-xs text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
                        Contributes to <span class="font-semibold">{{ $scoringGroups->first()->full_name }}</span>, the only score in this department.
                    </p>
                @endif

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">How important is this question?</label>
                    <div class="mt-2 flex gap-4">
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                            <input type="radio" name="importance" value="normal" @checked((float) $placement->weight < 2) class="border-slate-300 text-vytte-600"> Normal
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                            <input type="radio" name="importance" value="high" @checked((float) $placement->weight >= 2) class="border-slate-300 text-vytte-600"> Counts double
                        </label>
                    </div>
                </div>

                @if (in_array($typeCode, ['SINGLE_SELECT', 'LIKERT'], true))
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Points for each answer</label>
                        @if ($answerIsLocked)
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">This is an official library question, so its points are fixed.</p>
                            <ul class="mt-2 space-y-1 text-sm text-slate-600 dark:text-slate-300">
                                @foreach ($version->options ?? [] as $option)
                                    <li class="flex justify-between rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-900/40">
                                        <span>{{ $option['option_label'] }}</span>
                                        <span class="font-semibold">{{ $option['score_weight'] ?? '—' }} points @if ($option['critical_failure'] ?? false) · critical @endif</span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">0 is the weakest answer, 100 the strongest.</p>
                            <div class="mt-2 space-y-2">
                                @foreach ($version->options ?? [] as $option)
                                    <div class="grid items-center gap-3 sm:grid-cols-[1fr_110px_auto]">
                                        <span class="text-sm text-slate-700 dark:text-slate-200">{{ $option['option_label'] }}</span>
                                        <input type="number" min="0" max="100" step="1" name="points[{{ $option['option_order'] }}]"
                                               value="{{ old('points.'.$option['option_order'], $option['score_weight'] ?? '') }}" placeholder="Points"
                                               class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                        <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                                            <input type="checkbox" name="critical[{{ $option['option_order'] }}]" value="1"
                                                   @checked(old('critical.'.$option['option_order'], $option['critical_failure'] ?? false))
                                                   class="rounded border-slate-300 dark:border-slate-600">
                                            Critical
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                Mark an answer as critical when choosing it should raise a serious warning, whatever the total score.
                            </p>
                        @endif
                    </div>
                @endif

                @if ($typeCode === 'NUMERIC' && ! $answerIsLocked)
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Points for each range</label>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">A number needs ranges before it can be scored, for example 0–49 scores 0 and 50–100 scores 100.</p>
                        <div class="mt-2 space-y-2">
                            @for ($i = 0; $i < 4; $i++)
                                @php $band = old('bands.'.$i, ($version->numeric_bands[$i] ?? null)); @endphp
                                <div class="grid gap-2 sm:grid-cols-4">
                                    <input name="bands[{{ $i }}][min]" value="{{ $band['min'] ?? $band['min_value'] ?? '' }}" type="number" step="any" placeholder="From"
                                           class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                    <input name="bands[{{ $i }}][max]" value="{{ $band['max'] ?? $band['max_value'] ?? '' }}" type="number" step="any" placeholder="To"
                                           class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                    <input name="bands[{{ $i }}][points]" value="{{ $band['points'] ?? $band['score_weight'] ?? '' }}" type="number" min="0" max="100" placeholder="Points"
                                           class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                    <input name="bands[{{ $i }}][label]" value="{{ $band['label'] ?? '' }}" maxlength="60" placeholder="Label (optional)"
                                           class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                </div>
                            @endfor
                        </div>
                    </div>
                @endif
            </div>
        </template>

        <fieldset class="border-t border-slate-200 pt-5 dark:border-slate-700">
            <legend class="text-sm font-semibold text-slate-700 dark:text-slate-200">Supporting evidence</legend>
            <div class="mt-2 space-y-2">
                <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="radio" name="evidence_mode" value="none" x-model="evidence" @checked(! $placement->evidence_expectation) class="mt-0.5 border-slate-300 text-vytte-600">
                    <span>Not needed</span>
                </label>
                <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="radio" name="evidence_mode" value="note" x-model="evidence" @checked((bool) $placement->evidence_expectation) class="mt-0.5 border-slate-300 text-vytte-600">
                    <span>Ask for a written note</span>
                </label>
            </div>
            <div x-show="evidence === 'note'" x-cloak class="mt-3">
                <input name="evidence_prompt" value="{{ old('evidence_prompt', $placement->evidence_expectation) }}" maxlength="500"
                       placeholder="What should they describe? e.g. Name the register you checked."
                       class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Respondents are shown this prompt with the answer. A note is requested, not required to finish the assessment.
                </p>
            </div>
        </fieldset>

        @if ($isEditable)
            <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5 dark:border-slate-700">
                <button type="submit" class="rounded-xl bg-vytte-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700">Save settings</button>
                <a href="{{ route('admin.assessments.build', $assessment) }}" class="text-sm font-semibold text-slate-600 hover:underline dark:text-slate-300">Cancel</a>
            </div>
        @endif
    </form>
</x-admin-layout>
