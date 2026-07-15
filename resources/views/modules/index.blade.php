<x-app-layout title="Module Library">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 tracking-tight">Module Library</h1>
        <p class="mt-0.5 text-sm text-slate-500">Browse assessment modules available for each target type.</p>
    </div>

    @forelse ($targetTypes as $targetType)
        @if ($targetType->modules->isNotEmpty())
            <div class="mb-8">

                {{-- Target type header --}}
                <div class="flex items-center gap-2 mb-3">
                    <h2 class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">
                        {{ $targetType->target_type_name }}
                    </h2>
                    <div class="flex-1 h-px bg-slate-200"></div>
                    <span class="text-[11px] text-slate-400">{{ $targetType->modules->count() }} {{ Str::plural('module', $targetType->modules->count()) }}</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach ($targetType->modules as $module)
                        <a href="{{ route('modules.show', $module) }}"
                           class="group bg-white rounded-xl border border-slate-200 p-4 hover:border-vytte-400 hover:shadow-sm transition-all duration-150 block">

                            {{-- Module code badge + name --}}
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-vytte-50 text-vytte-700 text-[10px] font-bold tracking-wide uppercase border border-vytte-200">
                                    {{ $module->module_code }}
                                </span>
                                <svg class="w-4 h-4 text-slate-300 group-hover:text-vytte-500 flex-shrink-0 mt-0.5 transition-colors" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                </svg>
                            </div>

                            <h3 class="text-sm font-semibold text-slate-900 leading-snug mb-2.5">
                                {{ $module->module_name }}
                            </h3>

                            {{-- Stats row --}}
                            <div class="flex items-center gap-3 text-[11px] text-slate-400">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path d="M2 4.25A2.25 2.25 0 014.25 2h11.5A2.25 2.25 0 0118 4.25v8.5A2.25 2.25 0 0115.75 15h-3.105a3.501 3.501 0 001.1 1.677A.75.75 0 0113.26 18H6.74a.75.75 0 01-.484-1.323A3.501 3.501 0 007.355 15H4.25A2.25 2.25 0 012 12.75v-8.5z"/>
                                    </svg>
                                    {{ $module->module_domains_count }} {{ Str::plural('domain', $module->module_domains_count) }}
                                </span>
                                <span class="text-slate-300">·</span>
                                <span class="flex items-center gap-1">
                                    <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ $module->questions_count }} {{ Str::plural('question', $module->questions_count) }}
                                </span>
                                @if ($module->estimated_duration_minutes)
                                    <span class="text-slate-300">·</span>
                                    <span>~{{ $module->estimated_duration_minutes }}min</span>
                                @endif
                            </div>

                            @if ($module->sub_indices_count > 0)
                                <div class="mt-2.5 pt-2.5 border-t border-slate-100">
                                    <span class="text-[11px] text-slate-400">{{ $module->sub_indices_count }} {{ Str::plural('sub-index', $module->sub_indices_count) }}</span>
                                </div>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @empty
        <div class="bg-white rounded-xl border border-slate-200 px-5 py-12 flex flex-col items-center text-center">
            <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M2 4.25A2.25 2.25 0 014.25 2h11.5A2.25 2.25 0 0118 4.25v8.5A2.25 2.25 0 0115.75 15h-3.105a3.501 3.501 0 001.1 1.677A.75.75 0 0113.26 18H6.74a.75.75 0 01-.484-1.323A3.501 3.501 0 007.355 15H4.25A2.25 2.25 0 012 12.75v-8.5z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-slate-700">No modules available</p>
            <p class="mt-1 text-xs text-slate-400">Assessment modules are added by the Vytte team.</p>
        </div>
    @endforelse

</x-app-layout>
