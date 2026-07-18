<x-admin-layout title="Domain Taxonomies">

    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Domain Taxonomies</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            Governed analytical lenses used for official Vytte scoring interpretation, findings, recommendations, and reports.
        </p>
    </div>

    <div class="space-y-4">
        @forelse ($taxonomies as $taxonomy)
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-vytte-700 dark:text-vytte-400">{{ $taxonomy->taxonomy_code }}</p>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $taxonomy->taxonomy_name }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $taxonomy->description }}</p>
                    </div>
                    <span class="text-xs font-bold rounded-full px-2.5 py-1 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ $taxonomy->status }}</span>
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 uppercase">
                            <tr>
                                <th class="px-4 py-3 text-left">Version</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Domains</th>
                                <th class="px-4 py-3 text-left">Published</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($taxonomy->versions as $version)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">v{{ $version->version_number }}</td>
                                    <td class="px-4 py-3">{{ $version->status }}</td>
                                    <td class="px-4 py-3">{{ $version->definitions->count() }}</td>
                                    <td class="px-4 py-3">{{ $version->published_at?->format('d M Y') ?? 'Not published' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.domain-taxonomies.show', $version) }}" class="text-vytte-700 dark:text-vytte-400 font-semibold hover:underline">Inspect</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 text-sm text-slate-500">
                No governed domain taxonomy has been created yet.
            </div>
        @endforelse
    </div>

</x-admin-layout>
