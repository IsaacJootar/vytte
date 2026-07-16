<x-admin-layout title="Workspaces">

    <div class="mb-5 flex items-center justify-between gap-4">
        <h1 class="text-xl font-bold text-slate-900">Workspaces</h1>
        <span class="text-sm text-slate-400">{{ $workspaces->total() }} total</span>
    </div>

    {{-- Search / filter --}}
    <form method="GET" action="{{ route('admin.workspaces.index') }}" class="mb-5 flex gap-2 flex-wrap">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Search by name..."
               class="flex-1 min-w-48 px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-vytte-500 bg-white">
        <select name="plan"
                class="px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-vytte-500 bg-white">
            <option value="">All plans</option>
            <option value="FREE" @selected(request('plan') === 'FREE')>Free</option>
            <option value="PRO" @selected(request('plan') === 'PRO')>Pro</option>
            <option value="AGENCY" @selected(request('plan') === 'AGENCY')>Agency</option>
        </select>
        <button type="submit"
                class="px-4 py-2 text-sm font-semibold bg-vytte-700 text-white rounded-lg hover:bg-vytte-800 transition-colors">
            Search
        </button>
        @if (request()->hasAny(['search', 'plan']))
            <a href="{{ route('admin.workspaces.index') }}"
               class="px-4 py-2 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                Clear
            </a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden mb-5">
        @if ($workspaces->isEmpty())
            <div class="px-5 py-10 text-center text-sm text-slate-400">No workspaces found.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500">Workspace</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500">Plan</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-slate-500">Members</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-slate-500">Projects</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500">Created</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($workspaces as $workspace)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-slate-900">{{ $workspace->name }}</p>
                                    <p class="text-xs text-slate-400 font-mono">{{ substr($workspace->workspace_id, 0, 8) }}...</p>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                        {{ $workspace->plan === 'PRO' ? 'bg-vytte-50 text-vytte-700' : ($workspace->plan === 'AGENCY' ? 'bg-purple-50 text-purple-700' : 'bg-slate-100 text-slate-500') }}">
                                        {{ $workspace->plan ?? 'FREE' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-600">{{ $workspace->members_count }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-600">{{ $workspace->projects_count }}</td>
                                <td class="px-5 py-3 text-slate-400 text-xs">{{ $workspace->created_at->format('d M Y') }}</td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('admin.workspaces.show', $workspace) }}"
                                       class="text-xs font-semibold text-vytte-700 hover:text-vytte-900 transition-colors">
                                        View →
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($workspaces->hasPages())
                <div class="px-5 py-3 border-t border-slate-100">
                    {{ $workspaces->links() }}
                </div>
            @endif
        @endif
    </div>

</x-admin-layout>
