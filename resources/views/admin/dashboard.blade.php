<x-admin-layout title="Dashboard">

    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Vytte Platform Admin Control Center</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Govern official content, platform operations, roles, sharing, billing features, and compliance oversight.</p>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-6 gap-4 mb-6">
        @php
            $stats = [
            ['label' => 'Workspaces', 'value' => $workspaceCount],
            ['label' => 'Modules', 'value' => $moduleCount . ' total / ' . $activeModuleCount . ' active'],
            ['label' => 'Completed Assessments', 'value' => $totalAssessments],
            ['label' => 'Email Notifications', 'value' => $emailEnabled ? 'ON' : 'OFF'],
            ['label' => 'Platform Admins', 'value' => $platformAdminCount],
            ['label' => 'Published Questions', 'value' => $publishedQuestionVersions],
            ['label' => 'Published Frameworks', 'value' => $publishedFrameworks],
            ['label' => 'Published Releases', 'value' => $publishedCatalogueReleases],
            ['label' => '7-day Audit Events', 'value' => $recentAuditCount],
            ];

            $statStyles = [
                'bg-gradient-to-br from-vytte-700 to-vytte-900',
                'bg-gradient-to-br from-slate-800 to-slate-950',
                'bg-gradient-to-br from-slate-600 to-slate-800',
                'bg-gradient-to-br from-emerald-600 to-emerald-800',
                'bg-gradient-to-br from-indigo-600 to-indigo-800',
                'bg-gradient-to-br from-cyan-600 to-cyan-800',
            ];
        @endphp

        @foreach ($stats as $stat)
            <div class="{{ $statStyles[$loop->index % count($statStyles)] }} rounded-2xl p-4 shadow-card text-white">
                <p class="text-xs text-white/70 font-semibold mb-1">{{ $stat['label'] }}</p>
                <p class="text-lg font-bold text-white">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Quick links --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="{{ route('admin.official-content.index') }}"
           class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 hover:border-vytte-300 dark:hover:border-vytte-600 hover:shadow-sm transition-all group">
            <p class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-vytte-700 dark:group-hover:text-vytte-400">Official Content →</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Govern question groups, versions, frameworks, catalogue releases, and facility profiles</p>
        </a>
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
        <a href="{{ route('admin.platform-users.index') }}"
           class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 hover:border-vytte-300 dark:hover:border-vytte-600 hover:shadow-sm transition-all group">
            <p class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-vytte-700 dark:group-hover:text-vytte-400">Platform Users →</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Assign Vytte Platform Admin authority</p>
        </a>
        <a href="{{ route('admin.assessment-oversight.index') }}"
           class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 hover:border-vytte-300 dark:hover:border-vytte-600 hover:shadow-sm transition-all group">
            <p class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-vytte-700 dark:group-hover:text-vytte-400">Assessment Oversight →</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Review assessment lifecycle and immutable artifact status</p>
        </a>
        <a href="{{ route('admin.report-shares.index') }}"
           class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 hover:border-vytte-300 dark:hover:border-vytte-600 hover:shadow-sm transition-all group">
            <p class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-vytte-700 dark:group-hover:text-vytte-400">Report Shares →</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Audit and revoke public shared-report links</p>
        </a>
        <a href="{{ route('admin.audit-logs.index') }}"
           class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 hover:border-vytte-300 dark:hover:border-vytte-600 hover:shadow-sm transition-all group">
            <p class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-vytte-700 dark:group-hover:text-vytte-400">Audit Logs →</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Inspect immutable governance and operational audit events</p>
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
