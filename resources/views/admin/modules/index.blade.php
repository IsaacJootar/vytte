<x-admin-layout title="Module Library">

    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Module Library</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                Parts of a facility that people tick on or off when setting up an assessment — the outpatient
                department, the laboratory, the pharmacy. Not to be confused with health domains, which are the
                <em>subjects</em> questions are about.
            </p>
        </div>
        <a href="{{ route('admin.modules.import') }}" class="btn-primary">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/>
            </svg>
            Import Module
        </a>
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <x-stat-card tone="blue" label="Modules" :value="$modules->total()"
                     :sub="$modules->total() === 1 ? '1 module in the library' : $modules->total().' modules in the library'" />
        <x-stat-card tone="strong" label="Active" :value="$modules->getCollection()->where('is_active', true)->count()"
                     sub="Shown on this page" />
        <x-stat-card tone="slate" label="Questions" :value="$modules->getCollection()->sum('questions_count')"
                     sub="Across the modules shown" />
    </div>

    <x-admin-table
        search-placeholder="Search modules by name or code"
        :headings="['Module', 'Code', 'Target Type', 'Questions', 'Status']"
        :paginator="$modules"
        empty="No modules match your search"
        empty-hint="Try a different search, or clear the filters.">

        <x-slot:filters>
            <x-admin-filter label="Target type" name="target_type_code">
                <option value="">All target types</option>
                @foreach ($targetTypes as $targetType)
                    <option value="{{ $targetType }}" @selected(request('target_type_code') === $targetType)>{{ $targetType }}</option>
                @endforeach
            </x-admin-filter>
            <x-admin-filter label="Status" name="status">
                <option value="">Any status</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </x-admin-filter>
        </x-slot:filters>

        @foreach ($modules as $module)
            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.modules.show', $module) }}" class="font-semibold text-slate-900 hover:text-vytte-700 hover:underline dark:text-white dark:hover:text-vytte-300">
                        {{ $module->module_name }}
                    </a>
                </td>
                <td class="px-4 py-3 font-mono text-xs text-slate-400 dark:text-slate-500">{{ $module->module_code }}</td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $module->targetType?->target_type_name ?? $module->target_type_code }}</td>
                <td class="px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">{{ $module->questions_count }}</td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold
                        {{ $module->is_active
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
                            : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}">
                        {{ $module->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.modules.show', $module) }}" class="link-nav inline-flex items-center gap-1 text-sm">
                        Manage <span aria-hidden="true">&rarr;</span>
                    </a>
                </td>
            </tr>
        @endforeach
    </x-admin-table>

</x-admin-layout>
