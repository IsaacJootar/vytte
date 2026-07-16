<x-app-layout title="Start Assessment">

    <div class="mb-5">
        <a href="{{ route('projects.show', $project) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">
            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd"/>
            </svg>
            {{ $project->name }}
        </a>
    </div>

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Start Assessment</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Choose a module to assess {{ $project->targets->first()?->name ?? 'this target' }}.</p>
    </div>

    @if ($modules->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 px-5 py-12 flex flex-col items-center text-center">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No modules available</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500 max-w-xs">
                No assessment modules have been set up for this target type yet.
            </p>
        </div>
    @else
        <form method="POST" action="{{ route('assessments.store', $project) }}" x-data="{ selected: null }">
            @csrf

            <div class="space-y-2 mb-6">
                @foreach ($modules as $module)
                    <label class="block cursor-pointer">
                        <input type="radio" name="module_id" value="{{ $module->module_id }}"
                               x-model="selected"
                               class="sr-only" required>
                        <div class="flex items-start gap-4 p-4 bg-white dark:bg-slate-800 rounded-xl border transition-all duration-150"
                             :class="selected == '{{ $module->module_id }}'
                                 ? 'border-vytte-500 ring-2 ring-vytte-200 dark:ring-vytte-800 bg-vytte-50 dark:bg-vytte-900/20'
                                 : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600'">
                            <div class="flex-shrink-0 mt-0.5">
                                <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition-colors"
                                     :class="selected == '{{ $module->module_id }}'
                                         ? 'border-vytte-600'
                                         : 'border-slate-300 dark:border-slate-600'">
                                    <div class="w-2 h-2 rounded-full bg-vytte-600 transition-opacity"
                                         :class="selected == '{{ $module->module_id }}' ? 'opacity-100' : 'opacity-0'"></div>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span class="text-[10px] font-bold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide">{{ $module->module_code }}</span>
                                </div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $module->module_name }}</p>
                                <div class="mt-1 flex flex-wrap gap-3 text-[11px] text-slate-400 dark:text-slate-500">
                                    @if ($module->estimated_duration_minutes)
                                        <span>~{{ $module->estimated_duration_minutes }} min</span>
                                    @endif
                                    @if ($module->primary_respondent)
                                        <span>{{ $module->primary_respondent }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>

            @error('module_id')
                <p class="mb-4 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <button type="submit"
                    :disabled="!selected"
                    x-bind:class="selected ? 'bg-vytte-700 hover:bg-vytte-800 cursor-pointer' : 'bg-slate-200 dark:bg-slate-700 text-slate-400 dark:text-slate-500 cursor-not-allowed'"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-semibold text-white rounded-lg transition-colors duration-150">
                Start Assessment
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd"/>
                </svg>
            </button>
        </form>
    @endif

</x-app-layout>
