@php
    $wizard = $wizard ?? false;
    $localFormats = \App\Support\LocalQuestionFormat::all();
    $selectClasses = 'w-full px-3.5 py-2.5 text-sm text-slate-900 dark:text-white bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl shadow-sm focus:outline-none focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 transition-shadow duration-150';
    $textareaClasses = 'w-full px-3.5 py-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl shadow-sm focus:outline-none focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 transition-shadow duration-150';
@endphp

<form method="POST" action="{{ route('assessments.custom.save', $assessment) }}"
      x-data="localQuestionEditor(@js($section?->questions ?? []), @js($localFormats))"
      class="space-y-5">
    @csrf
    <input type="hidden" name="redirect_to" value="{{ $redirectTo ?? '' }}">

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">
            <p class="font-semibold">Please review the highlighted question details.</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5 text-xs">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <x-help-callout id="local-questions" title="Which one do I need?">
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Just for this assessment</p>
                <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-slate-300">Add it below so the answer appears in this assessment's report.</p>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Want other teams to use it too?</p>
                <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-slate-300">Use <a href="{{ route('contributions.create') }}" class="font-semibold text-vytte-700 hover:underline dark:text-vytte-300">Contribute Questions</a> instead. A reviewer can add it to Vytte's shared question list.</p>
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400"><a href="{{ route('scoring-model.index') }}" class="font-semibold text-vytte-700 hover:underline dark:text-vytte-300">See how scoring works →</a></p>
    </x-help-callout>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
        <x-form-section title="Local section" description="Give these questions a heading people will recognize." :last="true">
            <x-form-field label="Section name" name="section_title">
                <x-text-input id="section_title" name="section_title" type="text" class="block w-full"
                              :value="old('section_title', $section?->section_title ?? 'Local context')" maxlength="180" />
            </x-form-field>
            <x-form-field label="Instructions" name="instructions" hint="Shown before these questions." optional>
                <textarea id="instructions" name="instructions" rows="2" maxlength="1000"
                          class="block {{ $textareaClasses }}">{{ old('instructions', $section?->instructions) }}</textarea>
            </x-form-field>
        </x-form-section>
    </div>

    <div class="space-y-4">
        <template x-for="(q, i) in questions" :key="q.id">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="mb-4 flex items-center justify-between gap-3 border-b border-slate-100 pb-3 dark:border-slate-700">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-vytte-600" x-text="'Question ' + (i + 1)"></p>
                        <p class="mt-0.5 text-xs text-slate-500">Write the question, then choose how people answer it.</p>
                    </div>
                    <button type="button" @click="remove(i)" class="text-xs font-semibold text-slate-400 hover:text-red-600 dark:hover:text-red-400">Remove</button>
                </div>

                <input type="hidden" :name="'questions['+i+'][id]'" :value="q.id">
                <div class="space-y-5">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
                        Question wording
                        <textarea :name="'questions['+i+'][text]'" x-model="q.text" required rows="2" maxlength="500"
                                  placeholder="Write one clear question without combining two ideas."
                                  class="mt-1.5 block {{ $textareaClasses }} font-normal"></textarea>
                    </label>

                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
                        How should people answer?
                        <select :name="'questions['+i+'][type]'" x-model="q.response_type" @change="typeChanged(q)"
                                class="mt-1.5 {{ $selectClasses }} font-normal">
                            @foreach ($localFormats as $key => $format)
                                <option value="{{ $key }}">{{ $format['label'] }}</option>
                            @endforeach
                        </select>
                        <span class="mt-1 block text-xs font-normal text-slate-500" x-text="formatInfo(q.response_type).description"></span>
                    </label>

                    <template x-if="['SINGLE_SELECT', 'MULTI_SELECT'].includes(q.response_type)">
                        <fieldset>
                            <legend class="text-sm font-semibold text-slate-700 dark:text-slate-200">Answer choices</legend>
                            <p class="mt-0.5 text-xs text-slate-500">Add at least two different choices.</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <template x-for="(_, choiceIndex) in q.choices" :key="choiceIndex">
                                    <input type="text" :name="'questions['+i+'][choices]['+choiceIndex+']'" x-model="q.choices[choiceIndex]" maxlength="180"
                                           :placeholder="'Choice ' + (choiceIndex + 1) + (choiceIndex > 1 ? ' (optional)' : '')"
                                           class="block w-full px-3.5 py-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl shadow-sm focus:outline-none focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 transition-shadow duration-150">
                                </template>
                            </div>
                        </fieldset>
                    </template>

                    <template x-if="q.response_type === 'NUMERIC'">
                        <fieldset>
                            <legend class="text-sm font-semibold text-slate-700 dark:text-slate-200">Number details</legend>
                            <p class="mt-0.5 text-xs text-slate-500">Limits are optional. Add a unit when it prevents ambiguity.</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                <input type="number" step="any" :name="'questions['+i+'][numeric_min]'" x-model="q.numeric_min" placeholder="Minimum"
                                       class="block w-full px-3.5 py-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl shadow-sm focus:outline-none focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 transition-shadow duration-150">
                                <input type="number" step="any" :name="'questions['+i+'][numeric_max]'" x-model="q.numeric_max" placeholder="Maximum"
                                       class="block w-full px-3.5 py-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl shadow-sm focus:outline-none focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 transition-shadow duration-150">
                                <input type="text" :name="'questions['+i+'][numeric_unit]'" x-model="q.numeric_unit" maxlength="40" placeholder="Unit, e.g. days"
                                       class="block w-full px-3.5 py-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl shadow-sm focus:outline-none focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 transition-shadow duration-150">
                            </div>
                        </fieldset>
                    </template>

                    <template x-if="formatInfo(q.response_type).can_score">
                        <div class="rounded-xl border border-vytte-100 bg-vytte-50/60 p-4 dark:border-vytte-900 dark:bg-vytte-950/30">
                            <label class="flex items-start gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                <input type="hidden" :name="'questions['+i+'][is_scored]'" value="0">
                                <input type="checkbox" :name="'questions['+i+'][is_scored]'" value="1" x-model="q.is_scored"
                                       class="mt-0.5 rounded border-slate-300 text-vytte-600 focus:ring-vytte-500">
                                <span>Include in local score</span>
                            </label>

                            <div x-show="q.is_scored" class="mt-3">
                                <template x-if="['YES_NO', 'YES_NO_NA'].includes(q.response_type)">
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200">Which answer is the better one?
                                        <select :name="'questions['+i+'][good]'" x-model="q.good_answer"
                                                class="mt-1.5 {{ $selectClasses }} py-2 font-normal">
                                            <option value="YES">Yes is the better answer</option>
                                            <option value="NO">No is the better answer</option>
                                        </select>
                                    </label>
                                </template>
                                <template x-if="q.response_type === 'SCALE_5'">
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200">Which end of the scale is better?
                                        <select :name="'questions['+i+'][direction]'" x-model="q.score_direction"
                                                class="mt-1.5 {{ $selectClasses }} py-2 font-normal">
                                            <option value="HIGHER_IS_BETTER">5 is the better score</option>
                                            <option value="LOWER_IS_BETTER">1 is the better score</option>
                                        </select>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="!formatInfo(q.response_type).can_score">
                        <p class="rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-600 dark:bg-slate-900 dark:text-slate-300">This question won't get a score — Vytte doesn't yet have a fair way to score this kind of answer. To get it scored, use <a href="{{ route('contributions.create') }}" class="font-semibold text-vytte-700 hover:underline dark:text-vytte-300">Contribute Questions</a> instead.</p>
                    </template>
                </div>
            </section>
        </template>
    </div>

    <button type="button" @click="add()"
            class="inline-flex items-center gap-1.5 rounded-xl border border-dashed border-vytte-300 px-4 py-2.5 text-sm font-semibold text-vytte-700 transition-colors hover:bg-vytte-50 dark:border-vytte-800 dark:text-vytte-400 dark:hover:bg-vytte-900/20">
        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
        Add another question
    </button>

    <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center dark:border-slate-700">
        @unless ($wizard)
            <a href="{{ route('assessments.setup', $assessment) }}" class="text-center text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400">Cancel</a>
        @endunless
        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-vytte-700 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-vytte-800">
            {{ $wizard ? 'Save and choose collection method' : 'Save local questions' }}
        </button>
    </div>
</form>

<script>
    function localQuestionEditor(initial, formats) {
        return {
            formats,
            questions: (initial || []).map(q => ({
                id: q.id,
                text: q.text ?? '',
                response_type: q.response_type ?? 'YES_NO',
                choices: [...(q.choices ?? []), '', '', '', '', '', ''].slice(0, 6),
                numeric_min: q.numeric_min ?? '',
                numeric_max: q.numeric_max ?? '',
                numeric_unit: q.numeric_unit ?? '',
                is_scored: q.is_scored ?? ['YES_NO', 'YES_NO_NA', 'SCALE_5'].includes(q.response_type ?? 'YES_NO'),
                good_answer: q.good_answer ?? 'YES',
                score_direction: q.score_direction ?? (q.reversed ? 'LOWER_IS_BETTER' : 'HIGHER_IS_BETTER'),
            })),
            formatInfo(type) { return this.formats[type] ?? { description: '', can_score: false }; },
            typeChanged(question) {
                question.is_scored = this.formatInfo(question.response_type).can_score;
            },
            add() {
                this.questions.push({
                    id: crypto.randomUUID(), text: '', response_type: 'YES_NO', choices: ['', '', '', '', '', ''],
                    numeric_min: '', numeric_max: '', numeric_unit: '', is_scored: true,
                    good_answer: 'YES', score_direction: 'HIGHER_IS_BETTER',
                });
            },
            remove(index) { this.questions.splice(index, 1); },
            init() { if (this.questions.length === 0) this.add(); },
        };
    }
</script>
