<x-admin-layout title="Assessments in Use">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Assessments in Use</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Assessments customers are running in their own workspaces. You can see how far along each one is,
            but never the answers inside it.
        </p>
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card tone="blue" label="All time" :value="$counts['total']" sub="Assessments customers have started" />
        <x-stat-card tone="moderate" label="In progress" :value="$counts['in_progress']" sub="Still being filled in" />
        <x-stat-card tone="strong" label="Completed" :value="$counts['completed']" sub="Finished and scored" />
        <x-stat-card tone="slate" label="This month" :value="$counts['this_month']" sub="Started since the 1st" />
    </div>

    <x-admin-table
        search-label="Search assessments in use"
        search-placeholder="Search by facility, project or workspace"
        :headings="['What is being assessed', 'Workspace', 'Covers', 'Progress', 'Result']"
        :paginator="$assessments"
        empty="No assessments match your search"
        empty-hint="Try a different search, or clear the filters.">

        <x-slot:filters>
            <x-admin-filter label="Progress" name="status">
                <option value="">Any progress</option>
                <option value="IN_PROGRESS" @selected(request('status') === 'IN_PROGRESS')>In progress</option>
                <option value="COMPLETE" @selected(request('status') === 'COMPLETE')>Completed</option>
            </x-admin-filter>
            <x-admin-filter label="Type" name="creation_path">
                <option value="">Any type</option>
                <option value="COMPREHENSIVE" @selected(request('creation_path') === 'COMPREHENSIVE')>Whole facility</option>
                <option value="FOCUSED" @selected(request('creation_path') === 'FOCUSED')>One health area</option>
            </x-admin-filter>
        </x-slot:filters>

        @foreach ($assessments as $assessment)
            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                <td class="px-4 py-3">
                    <p class="font-semibold text-slate-900 dark:text-white">{{ $assessment->target?->name ?? 'Unnamed' }}</p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $assessment->project?->name ?? 'No project' }}</p>
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                    @if ($assessment->project?->workspace)
                        <a href="{{ route('admin.workspaces.show', $assessment->project->workspace) }}" class="link-nav">
                            {{ $assessment->project->workspace->name }}
                        </a>
                    @else
                        —
                    @endif
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ match ($assessment->creation_path) {
                        'COMPREHENSIVE' => 'Whole facility',
                        'FOCUSED' => 'One health area',
                        default => 'Not recorded',
                    } }}
                </td>
                <td class="px-4 py-3">
                    @if ($assessment->completed_at)
                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">Completed</span>
                        <p class="mt-0.5 text-xs text-slate-400">{{ $assessment->completed_at->diffForHumans() }}</p>
                    @else
                        <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">In progress</span>
                        <p class="mt-0.5 text-xs text-slate-400">Started {{ $assessment->created_at?->diffForHumans() }}</p>
                    @endif
                </td>
                <td class="px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">
                    {{ $assessment->score?->overall_score !== null ? round($assessment->score->overall_score).' / 100' : '—' }}
                </td>
            </tr>
        @endforeach
    </x-admin-table>
</x-admin-layout>
