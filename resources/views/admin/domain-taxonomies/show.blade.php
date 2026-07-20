<x-admin-layout title="Domain Taxonomy Version">

    <div class="mb-5">
        <a href="{{ route('admin.domain-taxonomies.index') }}" class="link-nav text-sm">&larr; Measurement domains</a>
        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                    {{ $version->taxonomy->taxonomy_name }} — version {{ $version->version_number }}
                </h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    The dimensions every score rolls up into, so a weakness can be compared across subjects.
                </p>
            </div>
            <x-assessment-status-badge :status="$version->status" class="px-3 py-1.5 text-sm" />
        </div>
    </div>

    @if ($undefinedDomains->isNotEmpty())
        <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950">
            <p class="text-sm font-bold text-amber-900 dark:text-amber-100">
                {{ $undefinedDomains->count() }} measurement {{ Str::plural('domain', $undefinedDomains->count()) }} not covered by this version
            </p>
            <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">
                {{ $undefinedDomains->pluck('domain_name')->join(', ') }}.
                {{ Str::plural('It', $undefinedDomains->count()) === 'It' ? 'It carries' : 'They carry' }}
                no scores and appears in no report while this version is the one in force.
                Start a new version to bring {{ $undefinedDomains->count() === 1 ? 'it' : 'them' }} into use.
            </p>
        </div>
    @endif

    <div class="mb-5 grid gap-4 lg:grid-cols-3">
        <div class="section-card p-5 lg:col-span-2">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Why versions cannot be edited</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                Every report ever produced was measured against one exact version of these dimensions.
                Editing a published version in place would silently change what past reports meant, so
                changes are made by publishing a new version instead. Reports already produced keep
                pointing at the version that was in force when they were made.
            </p>
        </div>

        <div class="section-card p-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Lifecycle</h2>

            @if ($version->status === \App\Models\DomainTaxonomyVersion::STATUS_DRAFT)
                <form method="POST" action="{{ route('admin.domain-taxonomies.publish', $version) }}" class="mt-3"
                      onsubmit="return confirm('Publish this version? It becomes the set of dimensions every new assessment is measured against, and its contents can never be changed afterwards.')">
                    @csrf @method('PATCH')
                    <button class="btn-primary w-full">Publish this version</button>
                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                        Brings it into force and retires the previous version. Reports already produced are unaffected.
                    </p>
                </form>
            @elseif ($version->status === \App\Models\DomainTaxonomyVersion::STATUS_PUBLISHED)
                <form method="POST" action="{{ route('admin.domain-taxonomies.versions.store', $version->domain_taxonomy_id) }}" class="mt-3">
                    @csrf
                    <button class="btn-secondary w-full">Start a new version</button>
                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                        Copies everything here into a draft you can change, and adds any measurement
                        domain this version does not cover.
                    </p>
                </form>
            @else
                <p class="mt-3 rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-900 dark:text-slate-300">
                    This version has been retired. It stays on record because reports produced against it
                    must remain readable.
                </p>
            @endif

            @if ($version->content_hash)
                <p class="mt-3 break-all text-xs text-slate-400">Fingerprint {{ substr($version->content_hash, 0, 16) }}…</p>
            @endif
        </div>
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
