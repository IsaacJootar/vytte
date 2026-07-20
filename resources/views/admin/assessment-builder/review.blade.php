<x-admin-layout title="Review and publish">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.assessments.build', $assessment) }}" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Back to building</a>
            <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Review and publish</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $assessment->display_name }}</p>
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
            <p class="text-sm font-semibold text-red-800 dark:text-red-200">Publishing could not continue:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700 dark:text-red-300">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($publishedRelease)
        <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-900 dark:bg-emerald-950">
            <p class="text-sm font-bold text-emerald-900 dark:text-emerald-100">This assessment is published</p>
            <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-200">
                Published {{ $publishedRelease->published_at?->format('j F Y') }}. Workspaces can now select it.
                Its content is locked so that reports stay reproducible; to change anything, create a new version.
            </p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.assessments.preview', $assessment) }}"
                   class="rounded-xl border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50 dark:border-emerald-800 dark:bg-slate-800 dark:text-emerald-200">
                    Preview what respondents see
                </a>
                @if ($openDraftVersion)
                    <a href="{{ route('admin.assessments.build', $openDraftVersion) }}"
                       class="rounded-xl border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50 dark:border-emerald-800 dark:bg-slate-800 dark:text-emerald-200">
                        Continue version {{ $openDraftVersion->version_number }}
                    </a>
                @else
                    <form method="POST" action="{{ route('admin.assessments.versions.store', $assessment) }}"
                          onsubmit="return confirm('Create a new version? This one stays published and in use until the new version is published.')">
                        @csrf
                        <button class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Create new version</button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Blockers --}}
            @if (! $readiness['ready'] && $isEditable)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950">
                    <h2 class="text-sm font-bold text-amber-900 dark:text-amber-100">
                        {{ count($readiness['blockers']) }} {{ Str::plural('thing', count($readiness['blockers'])) }} to fix before publishing
                    </h2>
                    <ul class="mt-3 space-y-2">
                        @foreach ($readiness['blockers'] as $blocker)
                            <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white px-4 py-3 text-sm dark:bg-slate-800">
                                <span class="text-slate-800 dark:text-slate-100">{{ $blocker['message'] }}</span>
                                @if ($blocker['kind'] === 'approval')
                                    <form method="POST" action="{{ route('admin.assessments.questions.approve', [$assessment, $blocker['placement_id']]) }}"
                                          onsubmit="return confirm('Approve this question? Its wording and answers are locked permanently once approved.')">
                                        @csrf @method('PATCH')
                                        <button class="rounded-lg bg-vytte-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-vytte-700">Review and approve</button>
                                    </form>
                                @elseif ($blocker['kind'] === 'scoring' || $blocker['kind'] === 'answer')
                                    <a href="{{ route('admin.assessments.questions.settings', [$assessment, $blocker['placement_id']]) }}"
                                       class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Fix</a>
                                @elseif ($blocker['kind'] === 'structure')
                                    <a href="{{ route('admin.assessments.build', $assessment) }}"
                                       class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Open</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @elseif ($isEditable)
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-900 dark:bg-emerald-950">
                    <p class="text-sm font-bold text-emerald-900 dark:text-emerald-100">Everything checks out</p>
                    <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-200">This assessment is ready to publish.</p>
                </div>
            @endif

            {{-- Content --}}
            @foreach ($assessment->sections as $section)
                <div class="section-card">
                    <div class="border-b border-slate-100 p-5 dark:border-slate-700">
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ $section->section_name }}</h2>
                        @if ($section->purpose)
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $section->purpose }}</p>
                        @endif
                    </div>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse ($section->questionPlacements as $placement)
                            <li class="px-5 py-3">
                                <p class="text-sm text-slate-800 dark:text-slate-100">{{ $placement->local_display_text ?: $placement->questionVersion?->question_text }}</p>
                                <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 font-semibold dark:bg-slate-700">
                                        {{ \App\Support\AnswerFormat::labelForTypeCode($placement->questionVersion?->questionType?->type_code, $placement->questionVersion?->options ?? []) }}
                                    </span>
                                    @if ($placement->scoring_contribution)
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
                                            Scored{{ (float) $placement->weight >= 2 ? ' · counts double' : '' }}
                                        </span>
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
                                @if (filled($placement->questionVersion?->options))
                                    <p class="mt-1 text-xs text-slate-400">
                                        {{ collect($placement->questionVersion->options)->map(fn ($o) => $o['option_label'].($placement->scoring_contribution && isset($o['score_weight']) ? ' ('.$o['score_weight'].')' : ''))->join(' · ') }}
                                    </p>
                                @endif
                            </li>
                        @empty
                            <li class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">No questions in this section.</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="space-y-4">
            <div class="section-card p-6 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Preview</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">See exactly what the person answering will see.</p>
                <a href="{{ route('admin.assessments.preview', $assessment) }}"
                   class="mt-3 inline-block rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200">
                    Open respondent preview
                </a>
            </div>

            <div class="section-card p-6 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Summary</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    @foreach ([
                        'Sections' => $readiness['summary']['sections'],
                        'Questions' => $readiness['summary']['questions'],
                        'Scored questions' => $readiness['summary']['scored_questions'],
                        'Not scored' => $readiness['summary']['unscored_questions'],
                        'Critical questions' => $readiness['summary']['critical_questions'],
                        'Ask for a note' => $readiness['summary']['evidence_questions'],
                        'Waiting for approval' => $readiness['summary']['awaiting_approval'],
                    ] as $label => $value)
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                            <dd class="font-semibold text-slate-900 dark:text-white">{{ $value }}</dd>
                        </div>
                    @endforeach
                    <div class="flex items-center justify-between border-t border-slate-100 pt-2 dark:border-slate-700">
                        <dt class="text-slate-500 dark:text-slate-400">Highest possible score</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white">{{ $readiness['summary']['maximum_score'] !== null ? (int) $readiness['summary']['maximum_score'] : '—' }}</dd>
                    </div>
                </dl>
            </div>

            @if ($isEditable)
                <form method="POST" action="{{ route('admin.assessments.provenance', $assessment) }}"
                      class="section-card p-6 dark:border-slate-700 dark:bg-slate-800">
                    @csrf @method('PUT')
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Source and usage</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Required before publishing, so anyone reading a report can see where this came from.</p>
                    <div class="mt-3 space-y-3">
                        <input name="source_authority" value="{{ old('source_authority', $assessment->source_authority) }}" maxlength="180"
                               placeholder="Who published the underlying guidance?"
                               class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        <input name="license_code" value="{{ old('license_code', $assessment->license_code) }}" maxlength="80"
                               placeholder="How may it be used? e.g. CC-BY-4.0"
                               class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        <input name="source_url" value="{{ old('source_url', $assessment->source_url) }}" maxlength="2000"
                               placeholder="Link to the source (optional)"
                               class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        <button class="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200">Save source details</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.assessments.publish', $assessment) }}"
                      class="section-card p-6 dark:border-slate-700 dark:bg-slate-800">
                    @csrf
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Publish</h2>

                    <label for="health_domain_id" class="mt-3 block text-xs font-semibold text-slate-700 dark:text-slate-200">Which health area does this cover?</label>
                    <select id="health_domain_id" name="health_domain_id" required
                            class="mt-1.5 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        <option value="">Choose a health area</option>
                        @foreach ($healthAreas as $area)
                            <option value="{{ $area->health_domain_id }}" @selected((int) old('health_domain_id', $suggestedHealthAreaId) === (int) $area->health_domain_id)>{{ $area->domain_name }}</option>
                        @endforeach
                    </select>

                    <label class="mt-4 flex items-start gap-2 text-xs text-slate-600 dark:text-slate-300">
                        <input type="checkbox" name="confirm" value="1" required class="mt-0.5 rounded border-slate-300 dark:border-slate-600">
                        <span>I understand that publishing is permanent. The questions, answers and scoring are locked so reports stay reproducible, and changes need a new version.</span>
                    </label>

                    <button @disabled(! $readiness['ready'])
                            class="mt-4 w-full rounded-xl bg-vytte-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700 disabled:cursor-not-allowed disabled:bg-slate-300 dark:disabled:bg-slate-600">
                        Publish assessment
                    </button>
                    @unless ($readiness['ready'])
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Fix the items listed on the left to enable publishing.</p>
                    @endunless
                </form>
            @endif
        </div>
    </div>
</x-admin-layout>
