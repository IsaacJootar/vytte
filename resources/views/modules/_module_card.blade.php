<a href="{{ route('modules.show', $module) }}"
   class="group bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 hover:border-vytte-400 dark:hover:border-vytte-600 hover:shadow-sm transition-all duration-150 block">

    {{-- Module code badge + name --}}
    <div class="flex items-start justify-between gap-2 mb-2">
        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-vytte-50 dark:bg-vytte-900/30 text-vytte-700 dark:text-vytte-400 text-[10px] font-bold tracking-wide uppercase border border-vytte-200 dark:border-vytte-800">
            {{ $module->module_code }}
        </span>
        <svg class="w-4 h-4 text-slate-300 dark:text-slate-600 group-hover:text-vytte-500 flex-shrink-0 mt-0.5 transition-colors" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
        </svg>
    </div>

    <h3 class="text-sm font-semibold text-slate-900 dark:text-white leading-snug mb-2.5">
        {{ $module->module_name }}
    </h3>

    {{-- Stats row --}}
    <div class="flex items-center gap-3 text-[11px] text-slate-400 dark:text-slate-500">
        <span class="flex items-center gap-1">
            <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M2 4.25A2.25 2.25 0 014.25 2h11.5A2.25 2.25 0 0118 4.25v8.5A2.25 2.25 0 0115.75 15h-3.105a3.501 3.501 0 001.1 1.677A.75.75 0 0113.26 18H6.74a.75.75 0 01-.484-1.323A3.501 3.501 0 007.355 15H4.25A2.25 2.25 0 012 12.75v-8.5z"/>
            </svg>
            {{ $module->question_groups_count }} {{ Str::plural('group', $module->question_groups_count) }}
        </span>
        <span class="text-slate-300 dark:text-slate-600">·</span>
        <span class="flex items-center gap-1">
            <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
            </svg>
            {{ $module->questions_count }} {{ Str::plural('question', $module->questions_count) }}
        </span>
        @if ($module->estimated_duration_minutes)
            <span class="text-slate-300 dark:text-slate-600">·</span>
            <span>~{{ $module->estimated_duration_minutes }}min</span>
        @endif
    </div>

    @if ($module->sub_indices_count > 0)
        <div class="mt-2.5 pt-2.5 border-t border-slate-100 dark:border-slate-700">
            <span class="text-[11px] text-slate-400 dark:text-slate-500">{{ $module->sub_indices_count }} {{ Str::plural('sub-index', $module->sub_indices_count) }}</span>
        </div>
    @endif
</a>
