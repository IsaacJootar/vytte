<x-admin-layout title="Assessments">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Assessments</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Build, review, and publish official Vytte assessments.</p>
        </div>
        <a href="{{ route('admin.assessments.create') }}"
           class="rounded-xl bg-vytte-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-500">
            + New Assessment
        </a>
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">In progress</p>
            <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $draftCount }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Published</p>
            <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $publishedCount }}</p>
        </div>
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
