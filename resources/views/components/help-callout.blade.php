@props(['id', 'title' => 'How this works'])

{{--
    A dismissible "how this works" strip. The reader can hide it once they know the
    ropes, and the choice is remembered per browser via localStorage under the given id.
--}}
<div x-data="{ show: localStorage.getItem('vytte.help.{{ $id }}') !== '0' }" class="mb-5">
    <div x-show="show" x-cloak class="rounded-xl border border-vytte-100 bg-vytte-50/60 dark:border-slate-700 dark:bg-slate-800/60 px-4 py-3">
        <div class="flex items-start justify-between gap-3">
            <p class="text-xs font-bold uppercase tracking-wide text-vytte-700 dark:text-vytte-400 mb-1.5">{{ $title }}</p>
            <button type="button" @click="show = false; localStorage.setItem('vytte.help.{{ $id }}', '0')"
                    class="flex-shrink-0 text-xs font-semibold text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300">Hide</button>
        </div>
        {{ $slot }}
    </div>
    <button type="button" x-show="!show" x-cloak
            @click="show = true; localStorage.removeItem('vytte.help.{{ $id }}')"
            class="inline-flex items-center gap-1.5 text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:text-vytte-900 dark:hover:text-vytte-200">
        <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
        Show tips
    </button>
</div>
