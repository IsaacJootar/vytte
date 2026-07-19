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

    <form method="GET" class="mb-4 flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
        <input name="search" value="{{ request('search') }}" placeholder="Search by assessment name"
               class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
        <select name="status" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(strtolower($status)) }}</option>
            @endforeach
        </select>
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
            <tr>
                <th class="px-4 py-3">Assessment</th>
                <th class="px-4 py-3">Department</th>
                <th class="px-4 py-3">Sections</th>
                <th class="px-4 py-3">Questions</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Last updated</th>
                <th class="px-4 py-3"></th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse ($assessments as $assessment)
                <tr>
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.assessments.show', $assessment) }}" class="font-semibold text-vytte-700 hover:underline dark:text-vytte-300">
                            {{ $assessment->display_name }}
                        </a>
                        @if ($assessment->description)
                            <p class="mt-0.5 max-w-md text-xs text-slate-500 dark:text-slate-400">{{ str($assessment->description)->limit(110) }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $assessment->module?->module_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $assessment->sections_count }}</td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $assessment->question_placements_count }}</td>
                    <td class="px-4 py-3">
                        <x-assessment-status-badge :status="$assessment->status" />
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $assessment->updated_at?->diffForHumans() }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.assessments.show', $assessment) }}" class="text-sm font-semibold text-vytte-700 hover:underline dark:text-vytte-300">
                            {{ $assessment->status === \App\Models\DepartmentFrameworkVersion::STATUS_DRAFT ? 'Continue' : 'View' }}
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No assessments yet</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Create your first assessment to get started.</p>
                        <a href="{{ route('admin.assessments.create') }}" class="mt-4 inline-block rounded-xl bg-vytte-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700">
                            + New Assessment
                        </a>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $assessments->links() }}</div>
</x-admin-layout>
