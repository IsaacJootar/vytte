<x-admin-layout title="Dashboard">

    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900">Platform Overview</h1>
        <p class="text-sm text-slate-500 mt-0.5">System-wide stats and quick links.</p>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        @foreach ([
            ['label' => 'Workspaces', 'value' => $workspaceCount],
            ['label' => 'Modules', 'value' => $moduleCount . ' total / ' . $activeModuleCount . ' active'],
            ['label' => 'Completed Assessments', 'value' => $totalAssessments],
            ['label' => 'Email Notifications', 'value' => $emailEnabled ? 'ON' : 'OFF'],
        ] as $stat)
            <div class="bg-white rounded-2xl border border-slate-200 p-4">
                <p class="text-xs text-slate-400 font-semibold mb-1">{{ $stat['label'] }}</p>
                <p class="text-lg font-bold text-slate-900">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Quick links --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <a href="{{ route('admin.workspaces.index') }}"
           class="bg-white rounded-2xl border border-slate-200 p-5 hover:border-vytte-300 hover:shadow-sm transition-all group">
            <p class="text-sm font-bold text-slate-900 group-hover:text-vytte-700">Workspaces →</p>
            <p class="text-xs text-slate-400 mt-1">View and search all workspaces</p>
        </a>
        <a href="{{ route('admin.modules.index') }}"
           class="bg-white rounded-2xl border border-slate-200 p-5 hover:border-vytte-300 hover:shadow-sm transition-all group">
            <p class="text-sm font-bold text-slate-900 group-hover:text-vytte-700">Module Library →</p>
            <p class="text-xs text-slate-400 mt-1">Manage assessment modules and questions</p>
        </a>
        <a href="{{ route('admin.settings.index') }}"
           class="bg-white rounded-2xl border border-slate-200 p-5 hover:border-vytte-300 hover:shadow-sm transition-all group">
            <p class="text-sm font-bold text-slate-900 group-hover:text-vytte-700">Settings →</p>
            <p class="text-xs text-slate-400 mt-1">Toggle platform features</p>
        </a>
    </div>

</x-admin-layout>
