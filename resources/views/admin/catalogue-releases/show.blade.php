<x-admin-layout title="Catalogue Release">
    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.catalogue-releases.index') }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-300">← Catalogue releases</a>
            <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $release->release_name }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $release->release_code }} · {{ $release->creation_path }} · {{ $release->status }}</p>
        </div>
        <div class="flex flex-wrap justify-end gap-2">
            @if ($release->status === 'DRAFT')
                <form method="POST" action="{{ route('admin.catalogue-releases.publish', $release) }}">
                    @csrf
                    @method('PATCH')
                    <button class="rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">Publish release</button>
                </form>
            @endif
            @if ($release->status === 'PUBLISHED')
                <form method="POST" action="{{ route('admin.catalogue-releases.supersede', $release) }}">
                    @csrf
                    <button class="rounded-xl border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-700 dark:border-amber-700 dark:text-amber-300">Create successor draft</button>
                </form>
            @endif
            @if (in_array($release->status, ['DRAFT', 'PUBLISHED'], true))
                <form method="POST" action="{{ route('admin.catalogue-releases.archive', $release) }}">
                    @csrf
                    @method('PATCH')
                    <button class="rounded-xl border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 dark:border-red-700 dark:text-red-300">Archive</button>
                </form>
            @endif
        </div>
    </div>

    <div class="mb-5 grid gap-4 md:grid-cols-4">
        @foreach ($dependencySummary as $label => $count)
            <x-stat-card :tone="$count > 0 ? 'blue' : 'slate'"
                         :label="str($label)->replace('_', ' ')->title()"
                         :value="$count" />
        @endforeach
    </div>

    @if ($release->status === 'DRAFT')
        <form method="POST" action="{{ route('admin.catalogue-releases.update', $release) }}" class="mb-5 section-card p-5 dark:border-slate-700 dark:bg-slate-800">
            @csrf
            @method('PUT')
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Release settings</h2>
            <input name="release_name" value="{{ $release->release_name }}" class="mt-4 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
            <textarea name="description" rows="2" class="mt-4 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ $release->description }}</textarea>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                @if ($release->creation_path === 'COMPREHENSIVE')
                    <select name="facility_profile_id" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                        @foreach ($facilityProfiles as $profile)
                            <option value="{{ $profile->facility_profile_id }}" @selected($release->facility_profile_id === $profile->facility_profile_id)>{{ $profile->profile_name }}</option>
                        @endforeach
                    </select>
                @else
                    <select name="health_domain_id" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                        @foreach ($healthDomains as $domain)
                            <option value="{{ $domain->health_domain_id }}" @selected($release->health_domain_id === $domain->health_domain_id)>{{ $domain->domain_name }}</option>
                        @endforeach
                    </select>
                @endif
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input type="checkbox" name="allows_multi_respondent" value="1" @checked($release->collection_config['allows_multi_respondent'] ?? false)> Multi-respondent</label>
                <input name="minimum_completed_respondents" type="number" min="1" value="{{ $release->collection_config['minimum_completed_respondents'] ?? 1 }}" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            </div>
            <div class="mt-4 text-right"><button class="rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">Save release</button></div>
        </form>
    @endif

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="xl:col-span-2 section-card p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Pinned framework versions</h2>
            <div class="mt-3 space-y-3">
                @forelse ($release->departmentFrameworkVersions as $framework)
                    <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-900">
                        <div class="flex items-center justify-between gap-3">
                            <a href="{{ route('admin.framework-versions.show', $framework) }}" class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $framework->module?->module_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $framework->display_name }} · v{{ $framework->version_number }} · {{ $framework->pivot->applicability }}</p>
                            </a>
                            @if ($release->status === 'DRAFT')
                                <form method="POST" action="{{ route('admin.catalogue-releases.frameworks.detach', [$release, $framework]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-xs font-semibold text-red-600">Remove</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No framework versions pinned yet.</p>
                @endforelse
            </div>

            @if ($release->status === 'DRAFT')
                <form method="POST" action="{{ route('admin.catalogue-releases.frameworks.attach', $release) }}" class="mt-5 rounded-xl border border-dashed border-slate-300 p-4 dark:border-slate-700">
                    @csrf
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Pin published framework</h3>
                    <select name="framework_version_id" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                        @foreach ($publishedFrameworks as $framework)
                            <option value="{{ $framework->framework_version_id }}">{{ $framework->module?->module_name }} · {{ $framework->display_name }} · v{{ $framework->version_number }}</option>
                        @endforeach
                    </select>
                    <div class="mt-3 grid gap-3 md:grid-cols-3">
                        <select name="applicability" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                            <option value="DEFAULT">Default</option>
                            <option value="REQUIRED">Required</option>
                            <option value="OPTIONAL">Optional</option>
                        </select>
                        <input name="display_order" type="number" min="1" value="{{ $release->departmentFrameworkVersions->count() + 1 }}" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                        <input name="area_label" placeholder="Optional area label" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    </div>
                    <button class="mt-3 rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">Pin framework</button>
                </form>
            @endif
        </div>
        <div class="space-y-4">
            <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Aggregation policy</h2>
                <pre class="mt-3 overflow-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100">{{ json_encode($release->aggregation_policy, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
            <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Collection config</h2>
                <pre class="mt-3 overflow-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100">{{ json_encode($release->collection_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    </div>
</x-admin-layout>
