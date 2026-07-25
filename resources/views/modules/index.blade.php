<x-app-layout title="Module Library">

    <div x-data="{ q: '', matches(hay) { return this.q === '' || hay.toLowerCase().includes(this.q.trim().toLowerCase()); } }">

    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Module Library</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Browse assessment modules available for each target type.</p>
    </div>

    {{-- Live search --}}
    <div class="mb-6 relative max-w-md">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 dark:text-slate-500 pointer-events-none" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/>
        </svg>
        <input type="text" x-model="q" placeholder="Search modules…"
               class="w-full pl-9 pr-3 py-2 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-vytte-700/20 focus:border-vytte-700 transition-shadow">
    </div>

    @forelse ($targetTypes as $targetType)
        @if ($targetType->modules->isNotEmpty())
            @php
                $groupHaystack = $targetType->modules
                    ->map(fn ($m) => $m->module_name.' '.$m->module_code)
                    ->implode(' ');
            @endphp
            <div class="mb-8" x-show="matches(@js($groupHaystack))">

                {{-- Target type header --}}
                <div class="flex items-center gap-2 mb-3">
                    <h2 class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">
                        {{ $targetType->target_type_name }}
                    </h2>
                    <div class="flex-1 h-px bg-slate-200 dark:bg-slate-700"></div>
                    <span class="text-[11px] text-slate-400 dark:text-slate-500">{{ $targetType->modules->count() }} {{ Str::plural('module', $targetType->modules->count()) }}</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach ($targetType->modules as $module)
                        <div x-show="matches(@js($module->module_name.' '.$module->module_code))">
                            @include('modules._module_card')
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @empty
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 px-5 py-12 flex flex-col items-center text-center">
            <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M2 4.25A2.25 2.25 0 014.25 2h11.5A2.25 2.25 0 0118 4.25v8.5A2.25 2.25 0 0115.75 15h-3.105a3.501 3.501 0 001.1 1.677A.75.75 0 0113.26 18H6.74a.75.75 0 01-.484-1.323A3.501 3.501 0 007.355 15H4.25A2.25 2.25 0 012 12.75v-8.5z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No modules available</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Assessment modules are added by the Vytte team.</p>
        </div>
    @endforelse

    </div>

</x-app-layout>
