<x-admin-layout title="Domain Taxonomy Version">

    <div class="mb-5">
        <a href="{{ route('admin.domain-taxonomies.index') }}" class="text-sm text-slate-500 hover:text-vytte-700">Back to domain taxonomies</a>
        <h1 class="text-xl font-bold text-slate-900 dark:text-white mt-2">
            {{ $version->taxonomy->taxonomy_name }} v{{ $version->version_number }}
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            {{ $version->status }} | Hash {{ $version->content_hash ?? 'not published' }}
        </p>
    </div>

    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-2xl p-4 mb-5">
        <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Immutable publication rule</p>
        <p class="text-sm text-amber-800 dark:text-amber-300 mt-1">
            Published taxonomy versions are not edited in place. Create a new version to change definitions or mappings.
        </p>
    </div>

    <div class="grid gap-4">
        @foreach ($version->definitions as $definition)
            <div class="section-card p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-vytte-700 dark:text-vytte-400">{{ $definition->domain_code }}</p>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $definition->domain_name }}</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-300 mt-2">{{ $definition->definition }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">{{ $definition->rationale }}</p>
                    </div>
                    <span class="text-xs font-bold rounded-full px-2.5 py-1 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ $definition->indicatorMappings->count() }} mappings</span>
                </div>

                @if ($definition->indicatorMappings->isNotEmpty())
                    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 uppercase">
                                <tr>
                                    <th class="px-4 py-3 text-left">Framework</th>
                                    <th class="px-4 py-3 text-left">Section</th>
                                    <th class="px-4 py-3 text-left">Indicator</th>
                                    <th class="px-4 py-3 text-left">Weight</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach ($definition->indicatorMappings as $mapping)
                                    <tr>
                                        <td class="px-4 py-3">{{ $mapping->indicator->frameworkVersion->display_name }}</td>
                                        <td class="px-4 py-3">{{ $mapping->indicator->section->section_name }}</td>
                                        <td class="px-4 py-3">{{ $mapping->indicator->indicator_name }}</td>
                                        <td class="px-4 py-3">{{ (float) $mapping->contribution_weight }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

</x-admin-layout>
