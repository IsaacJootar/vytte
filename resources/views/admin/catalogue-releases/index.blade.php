<x-admin-layout title="Catalogue Releases">
    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Catalogue Releases</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Published releases are the official entry points for assessment creation.</p>
        </div>
        <a href="{{ route('admin.catalogue-releases.create') }}" class="rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">New release</a>
    </div>
    <form method="GET" class="mb-4 flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
        <select name="creation_path" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All paths</option>
            <option value="COMPREHENSIVE" @selected(request('creation_path') === 'COMPREHENSIVE')>Comprehensive</option>
            <option value="FOCUSED" @selected(request('creation_path') === 'FOCUSED')>Focused</option>
        </select>
        <select name="status" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All status</option>
            <option value="DRAFT" @selected(request('status') === 'DRAFT')>Draft</option>
            <option value="PUBLISHED" @selected(request('status') === 'PUBLISHED')>Published</option>
        </select>
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Filter</button>
    </form>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
            <tr><th class="px-4 py-3">Release</th><th class="px-4 py-3">Path</th><th class="px-4 py-3">Profile/domain</th><th class="px-4 py-3">Frameworks</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse ($releases as $release)
                <tr>
                    <td class="px-4 py-3"><p class="font-semibold text-slate-900 dark:text-white">{{ $release->release_name }}</p><p class="text-xs text-slate-500">{{ $release->release_code }}</p></td>
                    <td class="px-4 py-3 text-xs font-bold text-slate-500">{{ $release->creation_path }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $release->facilityProfile?->profile_name ?? $release->healthDomain?->domain_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $release->department_framework_versions_count }}</td>
                    <td class="px-4 py-3 text-xs font-bold text-slate-500">{{ $release->status }}</td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('admin.catalogue-releases.show', $release) }}" class="text-sm font-semibold text-vytte-700 dark:text-vytte-300">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No catalogue releases found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $releases->links() }}</div>
</x-admin-layout>
