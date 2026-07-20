@php
    $typeCode = $version->questionType?->type_code;
    $currentOptions = old('options', $version->options ?? []);
    $optionRows = collect($currentOptions)->pad(max(5, count($currentOptions) + 2), [])->values();
    $currentBands = old('numeric_bands', $version->numeric_bands ?? []);
    $bandRows = collect($currentBands)->pad(max(5, count($currentBands) + 2), [])->values();
    $numericConfig = $version->numeric_config ?? [];
@endphp

<x-admin-layout title="Question Version">
    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.question-versions.index') }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-300">&larr; Question versions</a>
            <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $version->question?->question_code }} · v{{ $version->version_number }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $version->question?->module?->module_name }} · {{ $typeCode }} · {{ $version->status }}</p>
            @if ($version->parentVersion)
                <p class="mt-1 text-xs text-slate-500">Successor of v{{ $version->parentVersion->version_number }}.</p>
            @endif
        </div>
        <div class="flex flex-wrap justify-end gap-2">
            @if (in_array($version->status, ['DRAFT', 'INTERNAL_REVIEW'], true))
                <form method="POST" action="{{ route('admin.question-versions.approve', $version) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn-secondary">Approve</button>
                </form>
            @endif
            @if ($version->status === 'APPROVED')
                <form method="POST" action="{{ route('admin.question-versions.publish', $version) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn-primary">Publish immutable version</button>
                </form>
            @endif
            @if ($version->status === 'PUBLISHED')
                <form method="POST" action="{{ route('admin.question-versions.supersede', $version) }}">
                    @csrf
                    <button class="btn-secondary">Create successor draft</button>
                </form>
            @endif
            @if (in_array($version->status, ['DRAFT', 'INTERNAL_REVIEW', 'APPROVED', 'PUBLISHED'], true))
                <form method="POST" action="{{ route('admin.question-versions.archive', $version) }}"
                      onsubmit="return confirm('Archive this version? It stays on record and can still be read, but it can no longer be used or edited.')">
                    @csrf
                    @method('PATCH')
                    <button class="btn-danger">Archive</button>
                </form>
            @endif
        </div>
    </div>

    @if ($version->status === 'DRAFT')
        <p class="-mt-2 mb-5 text-xs text-slate-500 dark:text-slate-400">
            Drafts are archived rather than deleted. Nothing in the question library is ever erased,
            so every report ever produced can still be traced back to the exact wording used.
        </p>
    @endif

    <div class="mb-5 grid gap-4 md:grid-cols-4">
        @foreach ($dependencySummary as $label => $count)
            <div class="section-card p-4 dark:border-slate-700 dark:bg-slate-800">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ str($label)->replace('_', ' ') }}</p>
                <p class="mt-1 text-2xl font-black text-slate-900 dark:text-white">{{ $count }}</p>
            </div>
        @endforeach
    </div>

    @if ($version->status === 'DRAFT')
        <form method="POST" action="{{ route('admin.question-versions.update', $version) }}" class="grid gap-4 xl:grid-cols-3">
            @csrf
            @method('PUT')

            <div class="xl:col-span-2 space-y-4">
                <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Draft question configuration</h2>

                    <div class="mt-4 space-y-4">
                        <x-form-field label="Question wording" name="question_text"
                                      hint="The exact words the respondent reads. Locked once published.">
                            <textarea id="question_text" name="question_text" rows="4" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white" required>{{ old('question_text', $version->question_text) }}</textarea>
                        </x-form-field>

                        <div class="grid gap-4 md:grid-cols-2">
                            <x-form-field label="Response type" name="type_id"
                                          hint="Save after changing this to refresh the option or numeric controls below.">
                                <select id="type_id" name="type_id" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white" required>
                                    @foreach ($questionTypes as $type)
                                        <option value="{{ $type->type_id }}" @selected((int) old('type_id', $version->type_id) === (int) $type->type_id)>{{ $type->type_code }}</option>
                                    @endforeach
                                </select>
                            </x-form-field>
                            <x-form-field label="Who should answer this?" name="respondent_role_hint" :optional="true"
                                          hint="For example: Facility Manager, Head of Nursing.">
                                <input id="respondent_role_hint" name="respondent_role_hint" value="{{ old('respondent_role_hint', $version->respondent_role_hint) }}" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white">
                            </x-form-field>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/50">
                            <label class="flex items-start gap-3 text-sm">
                                <input type="checkbox" id="requires_observation" name="requires_observation" value="1" class="mt-0.5" @checked(old('requires_observation', $version->requires_observation))>
                                <span>
                                    <span class="font-semibold text-slate-800 dark:text-slate-100">The answer must be seen, not recalled</span>
                                    <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
                                        Tick this when the respondent has to physically check something on site — look at the
                                        equipment, open the register, walk the ward — rather than answer from memory or from a
                                        record. Questions ticked here show a "Needs observation" badge to whoever builds an
                                        assessment, so they can plan a site visit.
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <x-form-field label="Methodology notes" name="methodology_notes" :optional="true"
                                          hint="How this question should be interpreted and answered consistently.">
                                <textarea id="methodology_notes" name="methodology_notes" rows="3" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white">{{ old('methodology_notes', $version->methodology_notes) }}</textarea>
                            </x-form-field>
                            <x-form-field label="Source summary" name="source_summary" :optional="true"
                                          hint="Where this question comes from — a standard, guideline, or reference.">
                                <textarea id="source_summary" name="source_summary" rows="3" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white">{{ old('source_summary', $version->source_summary) }}</textarea>
                            </x-form-field>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <x-form-field label="Review notes" name="review_notes" :optional="true"
                                          hint="Internal notes for whoever approves this draft.">
                                <textarea id="review_notes" name="review_notes" rows="3" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white">{{ old('review_notes', $version->review_notes) }}</textarea>
                            </x-form-field>
                            <x-form-field label="Effective date" name="effective_date" :optional="true"
                                          hint="The date this wording is considered to apply from.">
                                <input id="effective_date" name="effective_date" type="date" value="{{ old('effective_date', $version->effective_date?->format('Y-m-d')) }}" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white">
                            </x-form-field>
                        </div>
                    </div>
                </div>

                @if ($isOptionType)
                    <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Response options</h2>
                        <p class="mt-1 text-xs text-slate-500">Blank rows are ignored. Scores must be 0–100.</p>

                        {{-- Column headings, so each box is labelled once instead of relying on
                             placeholder text that vanishes the moment the user types. --}}
                        <div class="mt-4 hidden gap-2 px-1 text-xs font-semibold text-slate-500 md:grid md:grid-cols-[1fr_90px_110px] dark:text-slate-400">
                            <span>Answer the respondent can choose</span>
                            <span>Order</span>
                            <span>Score</span>
                        </div>
                        <div class="mt-1.5 space-y-2">
                            @foreach ($optionRows as $index => $option)
                                <div class="grid items-center gap-2 md:grid-cols-[1fr_90px_110px]">
                                    <input type="hidden" name="options[{{ $index }}][option_id]" value="{{ $option['option_id'] ?? $index + 1 }}">
                                    <input name="options[{{ $index }}][option_label]" value="{{ $option['option_label'] ?? '' }}" aria-label="Answer label for row {{ $index + 1 }}" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white">
                                    <input name="options[{{ $index }}][option_order]" type="number" min="1" value="{{ $option['option_order'] ?? $index + 1 }}" aria-label="Order for row {{ $index + 1 }}" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white">
                                    <input name="options[{{ $index }}][score_weight]" type="number" min="0" max="100" step="0.01" value="{{ $option['score_weight'] ?? '' }}" aria-label="Score for row {{ $index + 1 }}" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white">
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($isNumericType)
                    <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Numeric validation and bands</h2>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 md:grid-cols-4">
                            <x-form-field label="Minimum" name="numeric_min" :optional="true">
                                <input id="numeric_min" name="numeric_min" type="number" step="0.0001" value="{{ old('numeric_min', $numericConfig['min_value'] ?? '') }}" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white">
                            </x-form-field>
                            <x-form-field label="Maximum" name="numeric_max" :optional="true">
                                <input id="numeric_max" name="numeric_max" type="number" step="0.0001" value="{{ old('numeric_max', $numericConfig['max_value'] ?? '') }}" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white">
                            </x-form-field>
                            <x-form-field label="Unit" name="numeric_unit" :optional="true">
                                <input id="numeric_unit" name="numeric_unit" value="{{ old('numeric_unit', $numericConfig['unit'] ?? '') }}" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white">
                            </x-form-field>
                            <x-form-field label="Step" name="numeric_step" :optional="true">
                                <input id="numeric_step" name="numeric_step" type="number" min="0.0001" step="0.0001" value="{{ old('numeric_step', $numericConfig['step'] ?? '') }}" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white">
                            </x-form-field>
                        </div>

                        <p class="mt-5 text-xs text-slate-500">Numeric bands are used when the question placement is scored.</p>
                        <div class="mt-3 hidden gap-2 px-1 text-xs font-semibold text-slate-500 md:grid md:grid-cols-[1fr_100px_100px_100px_90px] dark:text-slate-400">
                            <span>Band label</span>
                            <span>Min</span>
                            <span>Max</span>
                            <span>Score</span>
                            <span>Order</span>
                        </div>
                        <div class="mt-1.5 space-y-2">
                            @foreach ($bandRows as $index => $band)
                                <div class="grid items-center gap-2 md:grid-cols-[1fr_100px_100px_100px_90px]">
                                    <input name="numeric_bands[{{ $index }}][label]" value="{{ $band['label'] ?? '' }}" aria-label="Band label for row {{ $index + 1 }}" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white">
                                    <input name="numeric_bands[{{ $index }}][min_value]" type="number" step="0.0001" value="{{ $band['min_value'] ?? '' }}" aria-label="Minimum for row {{ $index + 1 }}" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white">
                                    <input name="numeric_bands[{{ $index }}][max_value]" type="number" step="0.0001" value="{{ $band['max_value'] ?? '' }}" aria-label="Maximum for row {{ $index + 1 }}" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white">
                                    <input name="numeric_bands[{{ $index }}][score_weight]" type="number" min="0" max="100" step="0.01" value="{{ $band['score_weight'] ?? '' }}" aria-label="Score for row {{ $index + 1 }}" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white">
                                    <input name="numeric_bands[{{ $index }}][display_order]" type="number" min="1" value="{{ $band['display_order'] ?? $index + 1 }}" aria-label="Order for row {{ $index + 1 }}" class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white">
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="text-right">
                    <button class="btn-primary">Save draft configuration</button>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Preview</h2>
                    <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ old('question_text', $version->question_text) }}</p>
                    @if ($isOptionType)
                        <div class="mt-4 space-y-2">
                            @foreach (collect($version->options ?? [])->sortBy('option_order') as $option)
                                <label class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-900">
                                    <span><input type="radio" disabled class="mr-2">{{ $option['option_label'] }}</span>
                                    <span class="text-xs text-slate-500">{{ $option['score_weight'] ?? '—' }}</span>
                                </label>
                            @endforeach
                        </div>
                    @elseif ($isNumericType)
                        <input disabled type="number" placeholder="Numeric answer" class="mt-4 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        <div class="mt-4 space-y-2 text-xs text-slate-500">
                            @foreach (collect($version->numeric_bands ?? [])->sortBy('display_order') as $band)
                                <p>{{ $band['label'] ?? 'Band' }}: {{ $band['min_value'] ?? '—' }}–{{ $band['max_value'] ?? '—' }} = {{ $band['score_weight'] ?? '—' }}</p>
                            @endforeach
                        </div>
                    @else
                        <textarea disabled rows="3" placeholder="Open-ended answer" class="mt-4 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
                    @endif
                </div>
                <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Governance rule</h2>
                    <p class="mt-2 text-sm text-slate-500">Only this draft can be edited. Once published, the exact options, numeric rules, and scoring bands are frozen into framework and assessment snapshots.</p>
                </div>
            </aside>
        </form>
    @else
        <div class="grid gap-4 xl:grid-cols-3">
            <div class="xl:col-span-2 section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Immutable content</h2>
                <p class="mt-3 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $version->question_text }}</p>
                <dl class="mt-5 grid gap-3 text-xs text-slate-500 md:grid-cols-2">
                    <div><dt class="font-bold text-slate-700 dark:text-slate-200">Respondent role</dt><dd>{{ $version->respondent_role_hint ?? '—' }}</dd></div>
                    <div><dt class="font-bold text-slate-700 dark:text-slate-200">Answer must be seen, not recalled</dt><dd>{{ $version->requires_observation ? "Yes — needs a site visit to answer" : "No — can be answered from records" }}</dd></div>
                    <div><dt class="font-bold text-slate-700 dark:text-slate-200">Effective date</dt><dd>{{ $version->effective_date?->format('Y-m-d') ?? '—' }}</dd></div>
                    <div><dt class="font-bold text-slate-700 dark:text-slate-200">Content hash</dt><dd class="break-all">{{ $version->content_hash ?? 'Not published' }}</dd></div>
                </dl>
            </div>
            <div class="space-y-4">
                <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Methodology</h2>
                    <p class="mt-2 text-sm text-slate-500">{{ $version->methodology_notes ?? 'No methodology notes provided.' }}</p>
                </div>
                <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Source summary</h2>
                    <p class="mt-2 text-sm text-slate-500">{{ $version->source_summary ?? 'No source summary provided.' }}</p>
                </div>
            </div>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Options</h2>
                <pre class="mt-3 overflow-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100">{{ json_encode($version->options, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'None' }}</pre>
            </div>
            <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Numeric scoring</h2>
                <pre class="mt-3 overflow-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100">{{ json_encode(['config' => $version->numeric_config, 'bands' => $version->numeric_bands], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    @endif
</x-admin-layout>
