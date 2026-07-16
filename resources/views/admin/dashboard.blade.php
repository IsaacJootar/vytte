<x-admin-layout title="Dashboard">

    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Platform Overview</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">System-wide stats and quick links.</p>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        @foreach ([
            ['label' => 'Workspaces', 'value' => $workspaceCount],
            ['label' => 'Modules', 'value' => $moduleCount . ' total / ' . $activeModuleCount . ' active'],
            ['label' => 'Completed Assessments', 'value' => $totalAssessments],
            ['label' => 'Email Notifications', 'value' => $emailEnabled ? 'ON' : 'OFF'],
        ] as $stat)
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4">
                <p class="text-xs text-slate-400 dark:text-slate-500 font-semibold mb-1">{{ $stat['label'] }}</p>
                <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Quick links --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="{{ route('admin.workspaces.index') }}"
           class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 hover:border-vytte-300 dark:hover:border-vytte-600 hover:shadow-sm transition-all group">
            <p class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-vytte-700 dark:group-hover:text-vytte-400">Workspaces →</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">View and search all workspaces</p>
        </a>
        <a href="{{ route('admin.modules.index') }}"
           class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 hover:border-vytte-300 dark:hover:border-vytte-600 hover:shadow-sm transition-all group">
            <p class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-vytte-700 dark:group-hover:text-vytte-400">Module Library →</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Manage assessment modules and questions</p>
        </a>
        <a href="{{ route('admin.geographic-usage.index') }}"
           class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 hover:border-vytte-300 dark:hover:border-vytte-600 hover:shadow-sm transition-all group">
            <p class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-vytte-700 dark:group-hover:text-vytte-400">Geographic Usage →</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Where assessments are being run</p>
        </a>
        <a href="{{ route('admin.plan-features.index') }}"
           class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 hover:border-vytte-300 dark:hover:border-vytte-600 hover:shadow-sm transition-all group">
            <p class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-vytte-700 dark:group-hover:text-vytte-400">Plan Features →</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Configure feature access per plan</p>
        </a>
        <a href="{{ route('admin.settings.index') }}"
           class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 hover:border-vytte-300 dark:hover:border-vytte-600 hover:shadow-sm transition-all group">
            <p class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-vytte-700 dark:group-hover:text-vytte-400">Settings →</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Toggle platform features</p>
        </a>
    </div>

</x-admin-layout>
