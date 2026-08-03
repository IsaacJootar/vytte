<x-admin-layout title="Scoring model">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.assessments.logic', $assessment) }}" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Back to logic</a>
            <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Scoring</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Define what the score means, then inspect every item that contributes to it.</p>
        </div>
        <x-assessment-status-badge :status="$assessment->status" />
    </div>

    <x-assessment-wizard-steps :steps="$steps" :current-step="$currentStep" :assessment="$assessment" />

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @foreach ($assessment->sections as $section)
                <section class="section-card overflow-hidden dark:border-slate-700 dark:bg-slate-800">
                    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-700">
                        <h2 class="font-bold text-slate-900 dark:text-white">{{ $section->section_name }}</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ $section->questionPlacements->where('scoring_contribution', true)->count() }} scored of {{ $section->questionPlacements->count() }} questions</p>
                    </div>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse ($section->questionPlacements as $placement)
                            @php($rule = $scoringModel->itemRules->firstWhere('framework_question_placement_id', $placement->framework_question_placement_id))
                            <li class="flex flex-wrap items-start justify-between gap-3 px-5 py-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-slate-800 dark:text-slate-100">{{ $placement->local_display_text ?: $placement->questionVersion?->question_text }}</p>
                                    <div class="mt-1 flex flex-wrap gap-2 text-xs text-slate-500">
                                        <span>{{ $placement->scoring_contribution ? 'Primary score' : 'Context only' }}</span>
                                        <span>&middot; {{ str($rule?->method ?? 'UNSCORED')->replace('_', ' ')->lower() }}</span>
                                        @if ($placement->scoring_contribution)<span>&middot; weight {{ number_format((float) $placement->weight, 1) }}</span>@endif
                                        @if ($placement->subIndex)<span>&middot; {{ $placement->subIndex->sub_index_name }}</span>@endif
                                        @if ($placement->criticality === 'CRITICAL')<span class="font-semibold text-red-700 dark:text-red-300">&middot; critical</span>@endif
                                    </div>
                                </div>
                                <a href="{{ route('admin.assessments.questions.settings', [$assessment, $placement]) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200">{{ $isEditable ? 'Edit item rule' : 'View item rule' }}</a>
                            </li>
                        @empty
                            <li class="px-5 py-4 text-sm text-slate-500">No questions in this section.</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>

        <aside class="space-y-4">
            <form method="POST" action="{{ route('admin.assessments.scoring.update', $assessment) }}" class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                @csrf @method('PUT')
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Score meaning</h2>
                <p class="mt-1 text-xs text-slate-500">These declarations travel with the immutable scoring model and report.</p>
                <label class="mt-4 block text-xs font-semibold text-slate-700 dark:text-slate-200">What does the score measure?
                    <select name="construct" @disabled(! $isEditable) class="mt-1.5 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900">
                        @foreach (['READINESS' => 'Readiness', 'COMPLIANCE' => 'Compliance', 'CAPACITY' => 'Capacity', 'EXPERIENCE' => 'Experience', 'NEED' => 'Need', 'PREVALENCE' => 'Prevalence'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('construct', $scoringModel->construct) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="mt-3 block text-xs font-semibold text-slate-700 dark:text-slate-200">How should a higher score be read?
                    <select name="direction" @disabled(! $isEditable) class="mt-1.5 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900">
                        <option value="HIGHER_IS_BETTER" @selected(old('direction', $scoringModel->direction) === 'HIGHER_IS_BETTER')>Higher is better</option>
                        <option value="HIGHER_IS_MORE_NEED" @selected(old('direction', $scoringModel->direction) === 'HIGHER_IS_MORE_NEED')>Higher means greater need</option>
                    </select>
                </label>
                <label class="mt-3 block text-xs font-semibold text-slate-700 dark:text-slate-200">If a required answer is missing
                    <select name="missing_policy" @disabled(! $isEditable) class="mt-1.5 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900">
                        @php($currentMissing = collect($scoringModel->missing_policy)->except('NOT_APPLICABLE')->contains('REQUIRE_RESPONSE') ? 'REQUIRE_RESPONSE' : 'EXCLUDE_AND_MARK_PARTIAL')
                        <option value="EXCLUDE_AND_MARK_PARTIAL" @selected(old('missing_policy', $currentMissing) === 'EXCLUDE_AND_MARK_PARTIAL')>Exclude it and mark the result partial</option>
                        <option value="REQUIRE_RESPONSE" @selected(old('missing_policy', $currentMissing) === 'REQUIRE_RESPONSE')>Require an answer before completion</option>
                    </select>
                </label>
                <p class="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-900 dark:text-slate-300">Not applicable is always excluded from the denominator. It is never converted to zero.</p>
                @if ($isEditable)<button class="mt-4 w-full rounded-xl bg-vytte-600 px-4 py-2 text-sm font-semibold text-white hover:bg-vytte-700">Save scoring model</button>@endif
            </form>

            <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">One score, disclosed clearly</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">Question origin does not create a separate score. Any approved item may contribute to the primary score when this publisher includes a complete item rule. Common-core comparison remains a separately governed view.</p>
            </div>
        </aside>
    </div>

    <div class="mt-5 flex flex-wrap justify-between gap-3">
        <a href="{{ route('admin.assessments.logic', $assessment) }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold dark:border-slate-600">&larr; Logic</a>
        <a href="{{ route('admin.assessments.quality', $assessment) }}" class="rounded-xl bg-vytte-600 px-5 py-2.5 text-sm font-semibold text-white">Continue to review &rarr;</a>
    </div>
</x-admin-layout>
