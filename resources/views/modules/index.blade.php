<x-app-layout title="Module Library">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Module Library</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Browse assessment modules available for each target type.</p>
    </div>

    @forelse ($targetTypes as $targetType)
        @if ($targetType->modules->isNotEmpty())
            <div class="mb-8">

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
                        @include('modules._module_card')
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

</x-app-layout>
