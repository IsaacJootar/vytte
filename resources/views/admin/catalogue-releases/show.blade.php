<x-admin-layout title="Catalogue Release">
    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.catalogue-releases.index') }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-300">← Catalogue releases</a>
            <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $release->release_name }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $release->release_code }} · {{ $release->creation_path }} · {{ $release->status }}</p>
        </div>
        @if ($release->status === 'DRAFT')
            <form method="POST" action="{{ route('admin.catalogue-releases.publish', $release) }}">
                @csrf
                @method('PATCH')
                <button class="rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">Publish release</button>
            </form>
        @endif
    </div>
    <div class="grid gap-4 xl:grid-cols-3">
        <div class="xl:col-span-2 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Pinned framework versions</h2>
            <div class="mt-3 space-y-3">
                @foreach ($release->departmentFrameworkVersions as $framework)
                    <a href="{{ route('admin.framework-versions.show', $framework) }}" class="block rounded-xl bg-slate-50 p-4 dark:bg-slate-900">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $framework->module?->module_name }}</p>
                            <span class="text-xs font-bold text-slate-500">{{ $framework->status }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ $framework->display_name }} · v{{ $framework->version_number }} · {{ $framework->pivot->applicability }}</p>
                    </a>
                @endforeach
            </div>
        </div>
        <div class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Aggregation policy</h2>
                <pre class="mt-3 overflow-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100">{{ json_encode($release->aggregation_policy, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Collection config</h2>
                <pre class="mt-3 overflow-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100">{{ json_encode($release->collection_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    </div>
</x-admin-layout>
