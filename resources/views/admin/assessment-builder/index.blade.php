<x-admin-layout title="Governance Studio">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Governance Studio</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Build, test, review, and publish governed assessment versions.</p>
        </div>
        <a href="{{ route('admin.assessments.create') }}"
           class="rounded-xl bg-vytte-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-500">
            + New Assessment
        </a>
    </div>

    <div class="mb-5 grid gap-3 md:grid-cols-4">
        <a href="{{ route('admin.assessments.create') }}" class="section-card p-4 transition hover:border-vytte-300 dark:border-slate-700 dark:bg-slate-800">
            <p class="text-[10px] font-bold uppercase tracking-wide text-vytte-600">1 · Author</p>
            <h2 class="mt-1 text-sm font-bold text-slate-900 dark:text-white">Guided assessment builder</h2>
            <p class="mt-1 text-xs text-slate-500">Nine labelled steps from purpose to immutable publication.</p>
        </a>
        <a href="{{ route('admin.question-identities.index') }}" class="section-card p-4 transition hover:border-vytte-300 dark:border-slate-700 dark:bg-slate-800">
            <p class="text-[10px] font-bold uppercase tracking-wide text-vytte-600">2 · Reuse</p>
            <h2 class="mt-1 text-sm font-bold text-slate-900 dark:text-white">Question Library</h2>
            <p class="mt-1 text-xs text-slate-500">Inspect stable questions and their immutable semantic versions.</p>
        </a>
        <a href="{{ route('admin.contributions.index') }}" class="section-card p-4 transition hover:border-vytte-300 dark:border-slate-700 dark:bg-slate-800">
            <p class="text-[10px] font-bold uppercase tracking-wide text-vytte-600">3 · Govern</p>
            <h2 class="mt-1 text-sm font-bold text-slate-900 dark:text-white">Expert contributions</h2>
            <p class="mt-1 text-xs text-slate-500">Review local proposals before promoting a separate governed draft.</p>
        </a>
        <a href="{{ route('admin.publishers.index') }}" class="section-card p-4 transition hover:border-vytte-300 dark:border-slate-700 dark:bg-slate-800">
            <p class="text-[10px] font-bold uppercase tracking-wide text-vytte-600">4 · Account</p>
            <h2 class="mt-1 text-sm font-bold text-slate-900 dark:text-white">Publishers and trust</h2>
            <p class="mt-1 text-xs text-slate-500">Keep publisher identity separate from evidence-backed review claims.</p>
        </a>
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-2">
        <x-stat-card tone="blue" label="In progress" :value="$draftCount"
                     :sub="$draftCount === 1 ? '1 assessment being built' : $draftCount.' assessments being built'" />
        <x-stat-card tone="strong" label="Published" :value="$publishedCount"
                     :sub="$publishedCount === 1 ? '1 assessment workspaces can use' : $publishedCount.' assessments workspaces can use'" />
    </div>

    <x-admin-table
        search-placeholder="Search by assessment name"
        :headings="['Assessment', 'Department', 'Sections', 'Questions', 'Status', 'Last updated']"
        :paginator="$assessments"
        empty="No assessments yet"
        empty-hint="Create your first assessment to get started.">

        <x-slot:filters>
            <x-admin-filter label="Status" name="status">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(strtolower($status)) }}</option>
                @endforeach
            </x-admin-filter>
        </x-slot:filters>

        @foreach ($assessments as $assessment)
            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.assessments.show', $assessment) }}" class="font-semibold text-slate-900 hover:text-vytte-700 hover:underline dark:text-white dark:hover:text-vytte-300">
                        {{ $assessment->display_name }}
                    </a>
                    @if ($assessment->description)
                        <p class="mt-0.5 max-w-md truncate text-xs text-slate-500 dark:text-slate-400">{{ $assessment->description }}</p>
                    @endif
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $assessment->module?->module_name ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $assessment->sections_count }}</td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $assessment->question_placements_count }}</td>
                <td class="px-4 py-3"><x-assessment-status-badge :status="$assessment->status" /></td>
                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $assessment->updated_at?->diffForHumans() }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.assessments.show', $assessment) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-vytte-700 hover:underline dark:text-vytte-300">
                        {{ $assessment->status === \App\Models\DepartmentFrameworkVersion::STATUS_DRAFT ? 'Continue' : 'View' }} <span aria-hidden="true">→</span>
                    </a>
                </td>
            </tr>
        @endforeach
    </x-admin-table>
</x-admin-layout>
