<x-admin-layout title="Frameworks">
    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Framework Versions</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Official department and focused-assessment frameworks.</p>
        </div>
        <a href="{{ route('admin.framework-versions.create') }}" class="rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">New framework</a>
    </div>
    <form method="GET" class="mb-4 flex flex-wrap gap-3 section-card p-4 dark:border-slate-700 dark:bg-slate-800">
        <select name="framework_type" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All types</option>
            <option value="DEPARTMENT" @selected(request('framework_type') === 'DEPARTMENT')>Department</option>
            <option value="FOCUSED" @selected(request('framework_type') === 'FOCUSED')>Focused</option>
        </select>
        <select name="status" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All status</option>
            @foreach (['DRAFT', 'PUBLISHED', 'SUPERSEDED', 'ARCHIVED'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Filter</button>
    </form>
    <div class="section-card">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
            <tr><th class="px-4 py-3">Framework</th><th class="px-4 py-3">Department / scope</th><th class="px-4 py-3">Structure</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse ($frameworks as $framework)
                <tr>
                    <td class="px-4 py-3"><p class="font-semibold text-slate-900 dark:text-white">{{ $framework->display_name }}</p><p class="text-xs text-slate-500">{{ $framework->framework_type }} · v{{ $framework->version_number }}</p></td>
                    <td class="px-4 py-3 text-slate-500">{{ $framework->module?->module_name }}</td>
                    <td class="px-4 py-3 text-xs text-slate-500">{{ $framework->sections_count }} sections · {{ $framework->indicators_count }} indicators · {{ $framework->question_placements_count }} placements</td>
                    <td class="px-4 py-3 text-xs font-bold text-slate-500">{{ $framework->status }}</td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('admin.framework-versions.show', $framework) }}" class="text-sm font-semibold text-vytte-700 dark:text-vytte-300">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No framework versions found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $frameworks->links() }}</div>
</x-admin-layout>
