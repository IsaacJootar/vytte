<x-admin-layout :title="$programme['name']">
    <div class="mb-5">
        <a href="{{ route('admin.programmes.index') }}" class="text-xs font-semibold text-vytte-700 hover:underline dark:text-vytte-300">← All programmes</a>
        <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $programme['name'] }}</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Every finished school and PHC report in this programme. There are two portfolio reports: one for the PHCs and one for the schools. Tick the accounts to include, then download.
            @if ($ticked !== null)
                The accounts ticked here are the saved field-team accounts; anything else (tests and trials) starts unticked.
            @endif
        </p>
    </div>

    <form method="GET" action="{{ route('admin.programmes.portfolio', $programme['slug']) }}"
          x-data="{ count: {{ count($reports) }}, sync() { this.count = this.$root.querySelectorAll('input[name=\'workspaces[]\']:checked').length } }" x-init="sync()">

        <div class="mb-3 flex flex-wrap items-center gap-2">
            <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                    @click="$root.querySelectorAll('input[name=\'workspaces[]\']').forEach(c => c.checked = true); sync()">Select all</button>
            <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                    @click="$root.querySelectorAll('input[name=\'workspaces[]\']').forEach(c => c.checked = false); sync()">Select none</button>
            <span class="text-xs text-slate-500 dark:text-slate-400"><span x-text="count"></span> of {{ count($reports) }} selected</span>
            <span class="ml-auto flex flex-wrap gap-2">
                <button type="submit" name="type" value="phc" :disabled="count === 0"
                        class="inline-flex items-center rounded-lg bg-vytte-700 px-4 py-2 text-sm font-semibold text-white hover:bg-vytte-800 disabled:cursor-not-allowed disabled:opacity-50">
                    Download PHC portfolio (PDF)
                </button>
                <button type="submit" name="type" value="school" :disabled="count === 0"
                        class="inline-flex items-center rounded-lg bg-vytte-700 px-4 py-2 text-sm font-semibold text-white hover:bg-vytte-800 disabled:cursor-not-allowed disabled:opacity-50">
                    Download School portfolio (PDF)
                </button>
            </span>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:text-slate-400">
                    <tr>
                        <th class="w-10 px-4 py-3"></th>
                        <th class="px-4 py-3">Place</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">People answered</th>
                        <th class="px-4 py-3">Account (workspace)</th>
                        <th class="px-4 py-3">Finished</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse ($reports as $report)
                        <tr class="text-slate-800 dark:text-slate-200">
                            <td class="px-4 py-3">
                                <input type="checkbox" name="workspaces[]" value="{{ $report['workspace_id'] }}" @checked($ticked === null || in_array($report['workspace_id'], $ticked, true)) @change="sync()"
                                       class="h-4 w-4 rounded border-slate-300 text-vytte-700 focus:ring-vytte-500" aria-label="Include {{ $report['place'] }}">
                            </td>
                            <td class="px-4 py-3 font-medium">{{ $report['place'] }}</td>
                            <td class="px-4 py-3">{{ ucfirst($report['type']) }}</td>
                            <td class="px-4 py-3 tabular-nums {{ $report['respondents'] < 10 ? 'text-amber-700 dark:text-amber-300' : '' }}">{{ $report['respondents'] }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $report['workspace'] }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $report['completed_at'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">No finished reports yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
            Ticking an account includes its finished report. A place that was assessed more than once is counted once, using the assessment answered by the most people.
            A board or ministry is not a school or PHC, so it is set aside rather than ranked. Numbers in amber mean fewer than 10 people answered.
        </p>
    </form>
</x-admin-layout>
