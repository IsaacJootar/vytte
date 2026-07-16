<x-admin-layout :title="'Translations · ' . $module->module_name">

    {{-- Header --}}
    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.modules.show', $module) }}"
               class="inline-flex items-center gap-1 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 mb-2 transition-colors">
                ← {{ $module->module_name }}
            </a>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Translations</h1>
            <p class="text-xs text-slate-400 dark:text-slate-500 font-mono mt-0.5">{{ $module->module_code }}</p>
        </div>
        {{-- Locale tabs --}}
        <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden flex-shrink-0">
            <a href="{{ route('admin.modules.translations.edit', [$module, 'fr']) }}"
               class="px-3 py-1.5 text-xs font-bold transition-colors {{ $locale === 'fr' ? 'bg-vytte-700 text-white' : 'bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                FR
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-5 flex items-center gap-3 px-4 py-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-sm text-green-800 dark:text-green-300 font-medium">
            <svg class="w-4 h-4 text-green-600 dark:text-green-400 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @if ($questions->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 px-5 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
            No active questions in this module.
        </div>
    @else
        <form method="POST" action="{{ route('admin.modules.translations.update', [$module, $locale]) }}">
            @csrf

            <div class="space-y-4">
                @foreach ($questions as $question)
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">

                        {{-- Question header --}}
                        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/50">
                            <p class="text-[10px] font-mono text-slate-400 dark:text-slate-500 mb-0.5">{{ $question->question_code }}</p>
                        </div>

                        {{-- Question text row --}}
                        <div class="px-5 py-4 grid grid-cols-1 md:grid-cols-2 gap-4 border-b border-slate-100 dark:border-slate-700">
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-1.5">English</p>
                                <p class="text-sm text-slate-700 dark:text-slate-300 leading-snug">{{ $question->question_text }}</p>
                            </div>
                            <div>
                                <label for="q_{{ $question->question_id }}"
                                       class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-1.5">
                                    French
                                </label>
                                <textarea
                                    id="q_{{ $question->question_id }}"
                                    name="questions[{{ $question->question_id }}]"
                                    rows="2"
                                    placeholder="Leave blank to clear translation"
                                    class="w-full text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-vytte-500 resize-none bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-300 dark:placeholder-slate-600">{{ $questionTranslations[$question->question_id] ?? '' }}</textarea>
                            </div>
                        </div>

                        {{-- Option rows --}}
                        @if ($question->options->isNotEmpty())
                            <div class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                @foreach ($question->options as $option)
                                    <div class="px-5 py-3 grid grid-cols-1 md:grid-cols-2 gap-3 items-center pl-10">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] text-slate-400 dark:text-slate-500 font-mono flex-shrink-0">{{ $option->option_order }}.</span>
                                            <p class="text-sm text-slate-600 dark:text-slate-400">{{ $option->option_label }}</p>
                                        </div>
                                        <div>
                                            <input
                                                type="text"
                                                name="options[{{ $option->option_id }}]"
                                                value="{{ $optionTranslations[$option->option_id] ?? '' }}"
                                                placeholder="French label"
                                                class="w-full text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-vytte-500 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-300 dark:placeholder-slate-600">
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                    </div>
                @endforeach
            </div>

            <div class="mt-5 flex justify-end">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-vytte-700 text-white text-sm font-semibold rounded-lg hover:bg-vytte-800 transition-colors focus:outline-none focus:ring-2 focus:ring-vytte-400 focus:ring-offset-1">
                    Save Translations
                </button>
            </div>
        </form>
    @endif

</x-admin-layout>
