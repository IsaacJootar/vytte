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
            @if ($version->status === 'PUBLISHED')
                <form method="POST" action="{{ route('admin.question-versions.supersede', $version) }}">
                    @csrf
                    <button class="rounded-xl border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-700 dark:border-amber-700 dark:text-amber-300">Create successor draft</button>
                </form>
            @endif
            @if (in_array($version->status, ['DRAFT', 'INTERNAL_REVIEW', 'APPROVED', 'PUBLISHED'], true))
                <form method="POST" action="{{ route('admin.question-versions.archive', $version) }}">
                    @csrf
                    @method('PATCH')
                    <button class="rounded-xl border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 dark:border-red-700 dark:text-red-300">Archive</button>
                </form>
            @endif
        </div>
    </div>

    <div class="mb-5 grid gap-4 md:grid-cols-4">
        @foreach ($dependencySummary as $label => $count)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
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
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Draft question configuration</h2>
                    <label class="mt-4 block text-xs font-bold text-slate-500">Question wording</label>
                    <textarea name="question_text" rows="4" class="mt-1 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>{{ old('question_text', $version->question_text) }}</textarea>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-xs font-bold text-slate-500">Response type</label>
                            <select name="type_id" class="mt-1 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                                @foreach ($questionTypes as $type)
                                    <option value="{{ $type->type_id }}" @selected((int) old('type_id', $version->type_id) === (int) $type->type_id)>{{ $type->type_code }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Save after changing type to refresh the relevant option or numeric controls.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500">Respondent role hint</label>
                            <input name="respondent_role_hint" value="{{ old('respondent_role_hint', $version->respondent_role_hint) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        </div>
                    </div>

                    <label class="mt-4 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" name="requires_observation" value="1" @checked(old('requires_observation', $version->requires_observation))>
                        Requires observation
                    </label>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <textarea name="methodology_notes" rows="3" placeholder="Methodology notes" class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ old('methodology_notes', $version->methodology_notes) }}</textarea>
                        <textarea name="source_summary" rows="3" placeholder="Source summary" class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ old('source_summary', $version->source_summary) }}</textarea>
                    </div>
                    <textarea name="review_notes" rows="2" placeholder="Review notes" class="mt-4 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ old('review_notes', $version->review_notes) }}</textarea>
                    <input name="effective_date" type="date" value="{{ old('effective_date', $version->effective_date?->format('Y-m-d')) }}" class="mt-4 rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                </div>

                @if ($isOptionType)
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Response options</h2>
                        <p class="mt-1 text-xs text-slate-500">Blank rows are ignored. Scores must be 0–100.</p>
                        <div class="mt-4 space-y-2">
                            @foreach ($optionRows as $index => $option)
                                <div class="grid gap-2 md:grid-cols-[1fr_90px_110px]">
                                    <input type="hidden" name="options[{{ $index }}][option_id]" value="{{ $option['option_id'] ?? $index + 1 }}">
                                    <input name="options[{{ $index }}][option_label]" value="{{ $option['option_label'] ?? '' }}" placeholder="Option label" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                    <input name="options[{{ $index }}][option_order]" type="number" min="1" value="{{ $option['option_order'] ?? $index + 1 }}" placeholder="Order" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                    <input name="options[{{ $index }}][score_weight]" type="number" min="0" max="100" step="0.01" value="{{ $option['score_weight'] ?? '' }}" placeholder="Score" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($isNumericType)
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Numeric validation and bands</h2>
                        <div class="mt-4 grid gap-3 md:grid-cols-4">
                            <input name="numeric_min" type="number" step="0.0001" value="{{ old('numeric_min', $numericConfig['min_value'] ?? '') }}" placeholder="Minimum" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                            <input name="numeric_max" type="number" step="0.0001" value="{{ old('numeric_max', $numericConfig['max_value'] ?? '') }}" placeholder="Maximum" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                            <input name="numeric_unit" value="{{ old('numeric_unit', $numericConfig['unit'] ?? '') }}" placeholder="Unit" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                            <input name="numeric_step" type="number" min="0.0001" step="0.0001" value="{{ old('numeric_step', $numericConfig['step'] ?? '') }}" placeholder="Step" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        </div>

                        <p class="mt-4 text-xs text-slate-500">Numeric bands are used when the question placement is scored.</p>
                        <div class="mt-3 space-y-2">
                            @foreach ($bandRows as $index => $band)
                                <div class="grid gap-2 md:grid-cols-[1fr_100px_100px_100px_90px]">
                                    <input name="numeric_bands[{{ $index }}][label]" value="{{ $band['label'] ?? '' }}" placeholder="Band label" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                    <input name="numeric_bands[{{ $index }}][min_value]" type="number" step="0.0001" value="{{ $band['min_value'] ?? '' }}" placeholder="Min" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                    <input name="numeric_bands[{{ $index }}][max_value]" type="number" step="0.0001" value="{{ $band['max_value'] ?? '' }}" placeholder="Max" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                    <input name="numeric_bands[{{ $index }}][score_weight]" type="number" min="0" max="100" step="0.01" value="{{ $band['score_weight'] ?? '' }}" placeholder="Score" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                    <input name="numeric_bands[{{ $index }}][display_order]" type="number" min="1" value="{{ $band['display_order'] ?? $index + 1 }}" placeholder="Order" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="text-right">
                    <button class="rounded-xl bg-vytte-700 px-5 py-2.5 text-sm font-bold text-white">Save draft configuration</button>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
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
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Governance rule</h2>
                    <p class="mt-2 text-sm text-slate-500">Only this draft can be edited. Once published, the exact options, numeric rules, and scoring bands are frozen into framework and assessment snapshots.</p>
                </div>
            </aside>
        </form>
    @else
        <div class="grid gap-4 xl:grid-cols-3">
            <div class="xl:col-span-2 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Immutable content</h2>
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
    @endif
</x-admin-layout>
