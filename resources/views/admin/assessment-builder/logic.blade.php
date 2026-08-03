<x-admin-layout title="Question logic">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.assessments.build', $assessment) }}" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Back to questions</a>
            <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Logic</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Show follow-up questions only when earlier answers make them relevant.</p>
        </div>
        <x-assessment-status-badge :status="$assessment->status" />
    </div>

    <x-assessment-wizard-steps :steps="$steps" :current-step="$currentStep" />

    <div class="mb-5 rounded-xl border border-vytte-200 bg-vytte-50 p-4 dark:border-vytte-900 dark:bg-vytte-950/40">
        <p class="text-sm font-semibold text-vytte-900 dark:text-vytte-100">Safe branching, without loops</p>
        <p class="mt-1 text-sm text-vytte-800 dark:text-vytte-200">A question may depend only on questions above it. Combine up to three conditions here using “all” or “any”. Hidden questions do not count toward completion or scoring.</p>
    </div>

    <div class="space-y-4">
        @foreach ($placements as $placement)
            @php
                $eligible = $placements->filter(fn ($candidate) => (int) $candidate->display_order < (int) $placement->display_order);
                $rule = $placement->applicability;
                $savedConditions = collect(old('conditions', ($rule['type'] ?? null) === 'response_rule' ? $rule['conditions'] : []));
            @endphp
            <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $placement->section?->section_name }}</p>
                        <h2 class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $placement->local_display_text ?: $placement->questionVersion?->question_text }}</h2>
                    </div>
                    @if (($rule['type'] ?? null) === 'response_rule')
                        <span class="rounded-full bg-vytte-100 px-2.5 py-1 text-xs font-semibold text-vytte-800 dark:bg-vytte-900/40 dark:text-vytte-200">Conditional</span>
                    @else
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">Always shown</span>
                    @endif
                </div>

                @if ($eligible->isEmpty())
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">This is the first question, so it is always shown.</p>
                @elseif ($isEditable)
                    <form method="POST" action="{{ route('admin.assessments.logic.update', [$assessment, $placement]) }}" class="mt-4 space-y-3">
                        @csrf @method('PUT')
                        <div class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                            <span>Show this question when</span>
                            <select name="operator" class="rounded-lg border-slate-300 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                                <option value="ALL" @selected(old('operator', $rule['operator'] ?? 'ALL') === 'ALL')>all conditions match</option>
                                <option value="ANY" @selected(old('operator', $rule['operator'] ?? 'ALL') === 'ANY')>any condition matches</option>
                            </select>
                        </div>

                        @for ($row = 0; $row < 3; $row++)
                            @php $condition = $savedConditions->get($row, []); @endphp
                            <div x-data="{ comparison: @js($condition['comparison'] ?? '') }" class="grid gap-2 rounded-xl bg-slate-50 p-3 md:grid-cols-3 dark:bg-slate-900/50">
                                <select name="conditions[{{ $row }}][source_question_id]" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900">
                                    <option value="">{{ $row === 0 ? 'Choose an earlier question' : 'Optional extra condition' }}</option>
                                    @foreach ($eligible as $source)
                                        <option value="{{ $source->question_id }}" @selected(($condition['source_question_id'] ?? '') === $source->question_id)>{{ Str::limit($source->local_display_text ?: $source->questionVersion?->question_text, 80) }}</option>
                                    @endforeach
                                </select>
                                <select name="conditions[{{ $row }}][comparison]" x-model="comparison" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900">
                                    <option value="">Choose a condition</option>
                                    @foreach ($comparisons as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                                <div>
                                    <select x-show="['OPTION_SELECTED','OPTION_NOT_SELECTED'].includes(comparison)" name="conditions[{{ $row }}][option_value]" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900">
                                        <option value="">Choose the answer</option>
                                        @foreach ($eligible as $source)
                                            @if (collect($source->questionVersion?->options ?? [])->isNotEmpty())
                                                <optgroup label="{{ Str::limit($source->local_display_text ?: $source->questionVersion?->question_text, 55) }}">
                                                    @foreach ($source->questionVersion->options as $option)
                                                        <option value="{{ $option['option_id'] }}" @selected((string) ($condition['value'] ?? '') === (string) $option['option_id'])>{{ $option['option_label'] }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endif
                                        @endforeach
                                    </select>
                                    <select x-show="comparison === 'STATE_IS'" name="conditions[{{ $row }}][state_value]" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900">
                                        <option value="">Choose the response state</option>
                                        @foreach ($states as $value => $label)<option value="{{ $value }}" @selected(($condition['value'] ?? '') === $value)>{{ $label }}</option>@endforeach
                                    </select>
                                    <input x-show="comparison.startsWith('NUMBER_')" name="conditions[{{ $row }}][number_value]" type="number" step="any" value="{{ str_starts_with($condition['comparison'] ?? '', 'NUMBER_') ? ($condition['value'] ?? '') : '' }}" placeholder="Enter a number" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900">
                                    <p x-show="!['OPTION_SELECTED','OPTION_NOT_SELECTED','STATE_IS'].includes(comparison) && !comparison.startsWith('NUMBER_')" class="px-2 py-2 text-xs text-slate-400">No extra value needed.</p>
                                </div>
                            </div>
                        @endfor

                        <div class="flex flex-wrap items-center gap-2">
                            <button class="rounded-xl bg-vytte-600 px-4 py-2 text-sm font-semibold text-white hover:bg-vytte-700">Save logic</button>
                        </div>
                    </form>
                    @if (($rule['type'] ?? null) === 'response_rule')
                        <form method="POST" action="{{ route('admin.assessments.logic.destroy', [$assessment, $placement]) }}" class="mt-2">
                            @csrf @method('DELETE')
                            <button class="text-xs font-semibold text-slate-500 hover:text-red-600">Clear logic and always show this question</button>
                        </form>
                    @endif
                @elseif (($rule['type'] ?? null) === 'response_rule')
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">This published rule uses {{ strtolower($rule['operator'] ?? 'ALL') }} of {{ count($rule['conditions'] ?? []) }} frozen conditions.</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-5 flex flex-wrap justify-between gap-3">
        <a href="{{ route('admin.assessments.build', $assessment) }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">← Questions</a>
        <a href="{{ route('admin.assessments.preview', $assessment) }}" class="rounded-xl bg-vytte-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700">Test respondent view →</a>
    </div>
</x-admin-layout>
