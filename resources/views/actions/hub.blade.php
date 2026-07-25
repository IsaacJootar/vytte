<x-app-layout title="Actions">

    <div class="mb-6">
        <p class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide">Actions</p>
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight mt-0.5">Your action plan</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Everything to do across all your projects. Each action traces back to a report finding.</p>
    </div>

    @if ($summary['total'] === 0)
        <x-empty-state
            icon="check-circle"
            title="No actions yet"
            message="Open a completed report, go to 'What to do', and add a recommendation to the plan. It appears here to own, schedule, and verify."
            :action="route('reports.index')"
            action-label="Go to reports" />
    @else
        {{-- Summary --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <x-stat-card tone="slate" label="Total" :value="$summary['total']" sub="Actions in the plan" />
            <x-stat-card tone="blue" label="Still open" :value="$summary['open']" sub="Not yet done" />
            <x-stat-card tone="strong" label="Done" :value="$summary['done']" sub="Completed or verified" />
            <x-stat-card :tone="$summary['overdue'] > 0 ? 'weak' : 'slate'" label="Overdue" :value="$summary['overdue']" sub="Past their due date" />
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('actions.hub') }}" class="mb-4 flex flex-wrap items-end gap-2">
            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                Project
                <select name="project" onchange="this.form.submit()" class="mt-1 block rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="">All projects</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->project_id }}" @selected($filters['project'] === $p->project_id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                Owner
                <select name="owner" onchange="this.form.submit()" class="mt-1 block rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="">Anyone</option>
                    @foreach ($members as $m)
                        <option value="{{ $m->user_id }}" @selected($filters['owner'] === $m->user_id)>{{ $m->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                Status
                <select name="status" onchange="this.form.submit()" class="mt-1 block rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="">Any status</option>
                    @foreach (['OPEN' => 'Open', 'IN_PROGRESS' => 'In progress', 'DONE' => 'Done', 'VERIFIED' => 'Verified'] as $val => $lbl)
                        <option value="{{ $val }}" @selected($filters['status'] === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </label>
            <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 pb-1.5">
                <input type="checkbox" name="overdue" value="1" onchange="this.form.submit()" @checked($filters['overdue'] === '1')
                       class="rounded border-slate-300 text-vytte-700 focus:ring-vytte-700">
                Overdue only
            </label>
            @if ($filters['project'] || $filters['owner'] || $filters['status'] || $filters['overdue'])
                <a href="{{ route('actions.hub') }}" class="pb-1.5 text-xs font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">Clear</a>
            @endif
        </form>

        {{-- Action list --}}
        @if ($actions->isEmpty())
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                No actions match these filters.
            </div>
        @else
            <div class="flex flex-col gap-3">
                @foreach ($actions as $action)
                    @php
                        $statusStyle = match ($action->status) {
                            'OPEN' => ['bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300', 'Open'],
                            'IN_PROGRESS' => ['bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300', 'In progress'],
                            'DONE' => ['bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300', 'Done'],
                            'VERIFIED' => ['bg-vytte-100 text-vytte-700 dark:bg-vytte-900/30 dark:text-vytte-300', 'Verified'],
                            default => ['bg-slate-100 text-slate-600', $action->status],
                        };
                    @endphp
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full {{ $statusStyle[0] }}">{{ $statusStyle[1] }}</span>
                                    @if ($action->isOverdue())
                                        <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">Overdue</span>
                                    @endif
                                    <a href="{{ route('projects.show', $action->project_id) }}" class="text-[11px] font-semibold text-vytte-700 dark:text-vytte-400 hover:underline">{{ $action->project?->name }}</a>
                                </div>
                                <p class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-white">{{ $action->title }}</p>
                                <div class="mt-1 flex items-center gap-3 flex-wrap text-xs text-slate-500 dark:text-slate-400">
                                    <span>Owner: <span class="font-medium text-slate-700 dark:text-slate-300">{{ $action->owner?->name ?? 'Unassigned' }}</span></span>
                                    @if ($action->due_date)<span>Due {{ $action->due_date->format('d M Y') }}</span>@endif
                                </div>
                            </div>
                            <div class="flex-shrink-0 flex flex-col items-end gap-2">
                                <form method="POST" action="{{ route('actions.update', $action) }}">
                                    @csrf @method('PATCH')
                                    <select name="status" onchange="this.form.submit()" class="text-xs rounded-lg border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 py-1">
                                        @foreach (['OPEN' => 'Open', 'IN_PROGRESS' => 'In progress', 'DONE' => 'Done', 'VERIFIED' => 'Verified'] as $val => $lbl)
                                            <option value="{{ $val }}" @selected($action->status === $val)>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </form>
                                <a href="{{ route('actions.index', $action->project_id) }}" class="text-[11px] font-semibold text-slate-400 hover:text-vytte-700 dark:hover:text-vytte-400">Manage →</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

</x-app-layout>
