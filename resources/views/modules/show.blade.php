<x-app-layout :title="$module->module_name">

    {{-- Back link --}}
    <div class="mb-5">
        <a href="{{ route('modules.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">
            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd"/>
            </svg>
            Module Library
        </a>
    </div>

    {{-- Module header --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 mb-5">
        <div class="flex items-start gap-3 flex-wrap">
            <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-vytte-50 dark:bg-vytte-900/30 text-vytte-700 dark:text-vytte-400 text-xs font-bold tracking-wide uppercase border border-vytte-200 dark:border-vytte-800">
                {{ $module->module_code }}
            </span>
        </div>
        <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white tracking-tight leading-snug">{{ $module->module_name }}</h1>

        <div class="mt-3 flex flex-wrap gap-4 text-sm text-slate-500 dark:text-slate-400">
            <div class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M9.293 2.293a1 1 0 011.414 0l7 7A1 1 0 0117 11h-1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-3a1 1 0 00-1-1H9a1 1 0 00-1 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-6H3a1 1 0 01-.707-1.707l7-7z" clip-rule="evenodd"/>
                </svg>
                <span>{{ $module->targetType->target_type_name }}</span>
            </div>

            @if ($module->estimated_duration_minutes)
                <div class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/>
                    </svg>
                    <span>~{{ $module->estimated_duration_minutes }} min</span>
                </div>
            @endif

            @if ($module->primary_respondent)
                <div class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"/>
                    </svg>
                    <span>{{ $module->primary_respondent }}</span>
                </div>
            @endif

            @if ($module->data_collection_methods)
                <div class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                    </svg>
                    <span>{{ $module->data_collection_methods }}</span>
                </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Left: domains and questions --}}
        <div class="lg:col-span-2 space-y-4">

            <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                Domains &amp; Questions
                <span class="ml-1 text-slate-400 dark:text-slate-500 font-normal">({{ $module->moduleDomains->count() }} {{ Str::plural('domain', $module->moduleDomains->count()) }})</span>
            </h2>

            @forelse ($module->moduleDomains as $domain)
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">

                    {{-- Domain header --}}
                    <div class="px-4 py-3 bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-vytte-100 dark:bg-vytte-900/40 text-vytte-700 dark:text-vytte-400 text-[10px] font-bold flex items-center justify-center flex-shrink-0">
                                {{ $domain->domain_number }}
                            </span>
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wide">{{ $domain->domain_label }}</span>
                        </div>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500">{{ $domain->questions->count() }} {{ Str::plural('question', $domain->questions->count()) }}</span>
                    </div>

                    {{-- Questions --}}
                    <div class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach ($domain->questions as $question)
                            <div class="px-4 py-3.5">
                                <div class="flex items-start gap-2.5">
                                    <span class="text-[11px] font-mono text-slate-400 dark:text-slate-500 mt-0.5 flex-shrink-0 pt-px">{{ $question->question_code }}</span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-slate-800 dark:text-slate-200 leading-snug">{{ $question->question_text }}</p>

                                        @if ($question->options->isNotEmpty())
                                            <div class="mt-2 space-y-1">
                                                @foreach ($question->options as $option)
                                                    <div class="flex items-center gap-2">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-slate-600 flex-shrink-0"></span>
                                                        <span class="text-xs text-slate-600 dark:text-slate-300 flex-1">{{ $option->option_label }}</span>
                                                        @if ($option->score_weight !== null)
                                                            <span class="text-[10px] font-mono text-slate-400 dark:text-slate-500 flex-shrink-0">{{ (int) $option->score_weight }}</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        <div class="mt-1.5 flex items-center gap-2">
                                            @if (! $question->is_scored)
                                                <span class="text-[10px] text-amber-600 dark:text-amber-400 font-medium">Not scored</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400 dark:text-slate-500">No domains defined for this module yet.</p>
            @endforelse
        </div>

        {{-- Right: sub-indices --}}
        <div class="space-y-4">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                Sub-Indices
                <span class="ml-1 text-slate-400 dark:text-slate-500 font-normal">({{ $module->subIndices->count() }})</span>
            </h2>

            @forelse ($module->subIndices as $subIndex)
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <div class="flex items-start gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[10px] font-bold tracking-wide uppercase flex-shrink-0">
                            {{ $subIndex->acronym }}
                        </span>
                    </div>
                    <p class="mt-1.5 text-xs font-semibold text-slate-800 dark:text-slate-200">{{ $subIndex->full_name }}</p>
                    @if ($subIndex->description)
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">{{ $subIndex->description }}</p>
                    @endif
                    @if ($subIndex->domain)
                        <div class="mt-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                            <span class="text-[10px] text-slate-400 dark:text-slate-500">Domain: <span class="font-medium text-slate-500 dark:text-slate-400">{{ $subIndex->domain->domain_name }}</span></span>
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-xs text-slate-400 dark:text-slate-500">No sub-indices for this module.</p>
            @endforelse
        </div>

    </div>

</x-app-layout>
