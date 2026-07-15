<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-200">
            Dashboard
        </h2>
    </x-slot>

    <div class="space-y-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                Welcome to Vytte
            </h3>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                {{ $currentWorkspace->name ?? 'Your workspace' }} is ready. Start by creating a project.
            </p>

            <div class="mt-4">
                <a href="#"
                   class="inline-flex items-center px-4 py-2 rounded-lg bg-vytte-600 text-white text-sm font-medium hover:bg-vytte-700 transition-colors">
                    New Project
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                <p class="text-sm text-slate-500 dark:text-slate-400">Projects</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">0</p>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                <p class="text-sm text-slate-500 dark:text-slate-400">Assessments</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">0</p>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                <p class="text-sm text-slate-500 dark:text-slate-400">Reports</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">0</p>
            </div>
        </div>
    </div>
</x-app-layout>
