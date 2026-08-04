<x-app-layout title="Suggest a question">
    <div class="max-w-2xl mx-auto">

        {{-- Back link + page header --}}
        <div class="mb-6">
            <a href="{{ route('contributions.index') }}"
               class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors mb-3">
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 010 1.06L8.06 10l3.72 3.72a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd"/>
                </svg>
                My contributions
            </a>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Suggest an expert question</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Submitting does not make the question public or change any existing score. A reviewer checks wording, evidence, and scoring before it becomes a governed draft.</p>
        </div>

        <x-help-callout id="contribute-question" title="What happens next">
            <p class="text-xs leading-5 text-slate-600 dark:text-slate-300">A publisher reviews the wording, answer design, and evidence you provide, then decides the scoring model and which future published assessment version includes it. Until then, this contribution stays a private draft and cannot affect any workspace's assessments, scores, or benchmarks.</p>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400"><a href="{{ route('scoring-model.index') }}" class="font-semibold text-vytte-700 hover:underline dark:text-vytte-300">See how scoring works →</a></p>
        </x-help-callout>

        <form method="POST" action="{{ route('contributions.store') }}"
              x-data="{ format: @js(old('response_format', 'yes_no')) }"
              class="flex flex-col gap-5">
            @csrf

            {{-- ===== SECTION 1 — Question ===== --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                <x-form-section title="Question" description="Name the contribution and write the question exactly as it should be asked.">
                    <x-form-field label="Contribution title" name="title">
                        <x-text-input id="title" name="title" type="text" class="block w-full"
                                      :value="old('title')" maxlength="180" placeholder="A short name reviewers can recognize" required autofocus />
                    </x-form-field>

                    <x-form-field label="Suggested department or health area" name="module_id" optional hint="Leave this to reviewers if you are not sure.">
                        <select id="module_id" name="module_id"
                                class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-vytte-500 focus:outline-none focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100">
                            <option value="">Let reviewers decide</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->module_id }}" @selected((int) old('module_id') === $department->module_id)>{{ $department->module_name }}</option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-form-field label="Question wording" name="question_text">
                        <textarea id="question_text" name="question_text" required rows="3" maxlength="5000"
                                  placeholder="Write one clear question without combining two ideas."
                                  class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-vytte-500 focus:outline-none focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100">{{ old('question_text') }}</textarea>
                    </x-form-field>
                </x-form-section>
            </div>

            {{-- ===== SECTION 2 — Answer design ===== --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                <x-form-section title="Answer design" description="Choose how people answer. A publisher decides later whether and how this contributes to a score.">
                    <x-form-field label="How should people answer?" name="response_format">
                        <select id="response_format" name="response_format" x-model="format"
                                class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-vytte-500 focus:outline-none focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100">
                            @foreach ($formats as $key => $formatInfo)
                                <option value="{{ $key }}">{{ $formatInfo['label'] }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400" x-text="({{ Illuminate\Support\Js::from(collect($formats)->pluck('description', 'key')) }})[format] ?? ''"></p>
                    </x-form-field>

                    <div x-show="['multiple_choice', 'multi_select'].includes(format)" x-cloak>
                        <fieldset>
                            <legend class="text-sm font-semibold text-slate-700 dark:text-slate-200">Answer choices</legend>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Add at least two different choices.</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                @for ($i = 0; $i < 6; $i++)
                                    <x-text-input name="answer_options[]" type="text" class="block w-full"
                                                  :value="old('answer_options.'.$i)"
                                                  placeholder="{{ 'Choice '.($i + 1).($i > 1 ? ' (optional)' : '') }}" />
                                @endfor
                            </div>
                            <x-input-error :messages="$errors->get('choices')" class="mt-1.5" />
                        </fieldset>
                    </div>

                    <div x-show="format === 'number'" x-cloak>
                        <fieldset>
                            <legend class="text-sm font-semibold text-slate-700 dark:text-slate-200">Number details</legend>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Limits are optional. Add a unit when it prevents ambiguity.</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                <x-text-input name="numeric_min" type="number" step="any" class="block w-full" :value="old('numeric_min')" placeholder="Minimum" />
                                <x-text-input name="numeric_max" type="number" step="any" class="block w-full" :value="old('numeric_max')" placeholder="Maximum" />
                                <x-text-input name="numeric_unit" type="text" class="block w-full" :value="old('numeric_unit')" placeholder="Unit, e.g. days" />
                            </div>
                            <x-input-error :messages="$errors->get('numeric_min')" class="mt-1.5" />
                        </fieldset>
                    </div>
                </x-form-section>
            </div>

            {{-- ===== SECTION 3 — Purpose and evidence ===== --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                <x-form-section title="Purpose and evidence" description="Help reviewers judge whether this question is ready to become governed content." :last="true">
                    <x-form-field label="When and why should this question be used?" name="intended_use">
                        <textarea id="intended_use" name="intended_use" required rows="3" maxlength="3000"
                                  class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-vytte-500 focus:outline-none focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100">{{ old('intended_use') }}</textarea>
                    </x-form-field>

                    <fieldset class="space-y-4 rounded-xl bg-slate-50 p-4 dark:bg-slate-900/50">
                        <legend class="px-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Source and method</legend>

                        <x-form-field label="Source authority or expert group" name="source_authority" optional>
                            <x-text-input id="source_authority" name="source_authority" type="text" class="block w-full" :value="old('source_authority')" />
                        </x-form-field>

                        <x-form-field label="Source link" name="source_url" optional>
                            <x-text-input id="source_url" name="source_url" type="url" class="block w-full" :value="old('source_url')" placeholder="https://" />
                        </x-form-field>

                        <x-form-field label="Usage permission or licence" name="license_code" optional>
                            <x-text-input id="license_code" name="license_code" type="text" class="block w-full" :value="old('license_code')" />
                        </x-form-field>

                        <x-form-field label="Evidence, definitions, timeframe or validation notes" name="methodology_notes" optional>
                            <textarea id="methodology_notes" name="methodology_notes" rows="3" maxlength="5000"
                                      class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-vytte-500 focus:outline-none focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100">{{ old('methodology_notes') }}</textarea>
                        </x-form-field>
                    </fieldset>
                </x-form-section>
            </div>

            {{-- Form actions --}}
            <div class="flex items-center gap-3">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-vytte-700 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-vytte-800 transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-700 focus-visible:ring-offset-2">
                    Submit for governed review
                </button>
                <a href="{{ route('contributions.index') }}"
                   class="text-sm font-medium text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">Cancel</a>
            </div>

        </form>

    </div>{{-- /max-w-2xl mx-auto --}}
</x-app-layout>
