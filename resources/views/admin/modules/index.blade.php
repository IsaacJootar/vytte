<x-admin-layout title="Module Library">

    <div class="mb-5 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Module Library</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $modules->count() }} modules total</p>
        </div>
        <a href="{{ route('admin.modules.import') }}"
           class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold bg-vytte-700 text-white rounded-lg hover:bg-vytte-800 transition-colors">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/>
            </svg>
            Import Module
        </a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        @if ($modules->isEmpty())
            <div class="px-5 py-10 text-center text-sm text-slate-400 dark:text-slate-500">No modules in the library yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-100 dark:border-slate-700">
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400">Module</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400">Code</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400">Target Type</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400">Questions</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach ($modules as $module)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-5 py-3 font-medium text-slate-900 dark:text-white">{{ $module->module_name }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-slate-400 dark:text-slate-500">{{ $module->module_code }}</td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $module->targetType?->target_type_name ?? $module->target_type_code }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-600 dark:text-slate-300">{{ $module->questions_count }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                        {{ $module->is_active ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-500' }}">
                                        {{ $module->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('admin.modules.show', $module) }}"
                                       class="text-xs font-semibold text-vytte-700 hover:text-vytte-900 transition-colors">
                                        Manage →
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</x-admin-layout>
