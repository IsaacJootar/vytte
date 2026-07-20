<x-admin-layout :title="$workspace->name">

    {{-- Back + header --}}
    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.workspaces.index') }}"
               class="inline-flex items-center gap-1 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 mb-2 transition-colors">
                ← Workspaces
            </a>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ $workspace->name }}</h1>
            <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5 font-mono">{{ $workspace->workspace_id }}</p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                {{ $workspace->plan === 'PRO' ? 'bg-vytte-50 text-vytte-700 border border-vytte-200 dark:bg-vytte-900/30 dark:text-vytte-400 dark:border-vytte-800' : ($workspace->plan === 'AGENCY' ? 'bg-purple-50 text-purple-700 border border-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:border-purple-800' : 'bg-slate-100 text-slate-500 border border-slate-200 dark:bg-slate-700 dark:text-slate-400 dark:border-slate-600') }}">
                {{ $workspace->plan ?? 'FREE' }}
            </span>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                {{ $workspace->status === 'ACTIVE' ? 'bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800' : 'bg-slate-100 text-slate-500 border border-slate-200 dark:bg-slate-700 dark:text-slate-400 dark:border-slate-600' }}">
                {{ $workspace->status ?? 'ACTIVE' }}
            </span>
            <form method="POST" action="{{ route('admin.workspaces.status', $workspace) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $workspace->status === 'ACTIVE' ? 'SUSPENDED' : 'ACTIVE' }}">
                <button class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border
                    {{ $workspace->status === 'ACTIVE' ? 'border-amber-300 text-amber-700 dark:border-amber-700 dark:text-amber-300' : 'border-green-300 text-green-700 dark:border-green-700 dark:text-green-300' }}">
                    {{ $workspace->status === 'ACTIVE' ? 'Suspend' : 'Reactivate' }}
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Members --}}
        <div class="section-card">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Members ({{ $workspace->members->count() }})</h2>
            </div>
            @if ($workspace->members->isEmpty())
                <p class="px-5 py-6 text-sm text-slate-400 dark:text-slate-500">No members.</p>
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($workspace->members as $member)
                        <div class="flex items-center justify-between px-5 py-3">
                            <div>
                                <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $member->user?->name ?? '—' }}</p>
                                <p class="text-xs text-slate-400 dark:text-slate-500">{{ $member->user?->email }}</p>
                            </div>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full
                                {{ $member->role === 'OWNER' ? 'bg-slate-900 text-white dark:bg-slate-600' : ($member->role === 'ADMIN' ? 'bg-vytte-100 text-vytte-800 dark:bg-vytte-900/40 dark:text-vytte-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300') }}">
                                {{ $member->role }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Projects --}}
        <div class="section-card">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Projects ({{ $workspace->projects->count() }})</h2>
            </div>
            @if ($workspace->projects->isEmpty())
                <p class="px-5 py-6 text-sm text-slate-400 dark:text-slate-500">No projects.</p>
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($workspace->projects as $project)
                        <div class="px-5 py-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $project->name }}</p>
                                <span class="text-xs {{ $project->status === 'ACTIVE' ? 'text-green-600 dark:text-green-400' : 'text-slate-400 dark:text-slate-500' }}">
                                    {{ $project->status }}
                                </span>
                            </div>
                            @if ($project->assessments->isNotEmpty())
                                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">
                                    {{ $project->assessments->count() }} assessment(s) ·
                                    {{ $project->assessments->where('status', 'COMPLETE')->count() }} complete
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

</x-admin-layout>
