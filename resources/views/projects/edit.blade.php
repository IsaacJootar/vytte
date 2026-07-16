<x-app-layout :title="'Edit · ' . $project->name">

    <div class="mb-6">
        <a href="{{ route('projects.show', $project) }}"
           class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors mb-3">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 010 1.06L8.06 10l3.72 3.72a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd"/>
            </svg>
            {{ $project->name }}
        </a>
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Edit project</h1>
    </div>

    <form method="POST" action="{{ route('projects.update', $project) }}" x-data="{ loading: false }">
        @csrf
        @method('PATCH')

        <div class="max-w-2xl">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 flex flex-col gap-4">

                <div>
                    <x-input-label for="name" value="Project name" />
                    <x-text-input
                        id="name"
                        name="name"
                        type="text"
                        class="mt-1 block w-full"
                        :value="old('name', $project->name)"
                        required
                        autofocus
                    />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="description" value="Description (optional)" />
                    <textarea
                        id="description"
                        name="description"
                        rows="3"
                        class="mt-1 block w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-vytte-700 focus:border-transparent transition"
                    >{{ old('description', $project->description) }}</textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-vytte-700 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-vytte-800 transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-700 focus-visible:ring-offset-2"
                        x-on:click="loading = true"
                        :disabled="loading"
                    >
                        <svg class="w-3.5 h-3.5 btn-spinner hidden" x-show="loading" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" d="M12 2C6.477 2 2 6.477 2 12"/>
                        </svg>
                        <span x-text="loading ? 'Saving…' : 'Save changes'"></span>
                    </button>
                    <a href="{{ route('projects.show', $project) }}"
                       class="text-sm font-medium text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">Cancel</a>
                </div>

            </div>
        </div>
    </form>

</x-app-layout>
