<x-app-layout title="Dashboard">

    {{-- Page header --}}
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Dashboard</h1>
            <p class="mt-0.5 text-sm text-slate-500">
                {{ $currentWorkspace->name ?? 'Your workspace' }} · Welcome back
            </p>
        </div>
        <a href="{{ route('projects.create') }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-vytte-700 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-vytte-800 transition-colors duration-150">
            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/>
            </svg>
            New Project
        </a>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Projects</p>
            <p class="mt-1.5 text-3xl font-bold text-slate-900 tabular-nums">0</p>
            <p class="mt-0.5 text-xs text-slate-400">No active projects yet</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Assessments</p>
            <p class="mt-1.5 text-3xl font-bold text-slate-900 tabular-nums">0</p>
            <p class="mt-0.5 text-xs text-slate-400">No assessments run</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Avg. Score</p>
            <p class="mt-1.5 text-3xl font-bold text-slate-400 tabular-nums">—</p>
            <p class="mt-0.5 text-xs text-slate-400">No scores yet</p>
        </div>
    </div>

    {{-- Recent projects placeholder --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900">Recent Projects</h2>
        </div>
        <div class="px-5 py-12 flex flex-col items-center text-center">
            <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center mb-3">
                <x-heroicon-o-folder class="w-5 h-5 text-slate-400" />
            </div>
            <p class="text-sm font-semibold text-slate-700">No projects yet</p>
            <p class="mt-1 text-xs text-slate-400 max-w-xs">
                Create a project for each health facility or programme you want to diagnose.
            </p>
            <a href="{{ route('projects.create') }}"
               class="mt-4 inline-flex items-center gap-1.5 px-3.5 py-2 bg-vytte-700 text-white text-sm font-semibold rounded-lg hover:bg-vytte-800 transition-colors duration-150">
                Create first project
            </a>
        </div>
    </div>

</x-app-layout>
