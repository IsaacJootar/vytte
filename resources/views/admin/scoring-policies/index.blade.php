<x-admin-layout title="Scoring Policies">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Scoring and Aggregation Policies</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Read the scoring profile and aggregation policies pinned to official content.</p>
    </div>
    <div class="mb-4 rounded-2xl border border-vytte-200 bg-vytte-50 p-4 text-sm text-vytte-900 dark:border-vytte-800 dark:bg-vytte-900/20 dark:text-vytte-200">
        Current scoring engine version: <span class="font-bold">{{ $currentScoringVersion }}</span>. Arithmetic mean is the only initially supported multi-respondent aggregation method.
    </div>
    <div class="grid gap-4 xl:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Framework scoring profiles</h2>
            <div class="mt-3 space-y-3">
                @foreach ($frameworks as $framework)
                    <a href="{{ route('admin.framework-versions.show', $framework) }}" class="block rounded-xl bg-slate-50 p-4 dark:bg-slate-900">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $framework->display_name }}</p>
                            <span class="text-xs font-bold text-slate-500">{{ $framework->status }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ $framework->module?->module_name }} · {{ $framework->scoring_version ?? 'Not frozen yet' }}</p>
                    </a>
                @endforeach
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Catalogue aggregation policies</h2>
            <div class="mt-3 space-y-3">
                @foreach ($catalogues as $release)
                    <a href="{{ route('admin.catalogue-releases.show', $release) }}" class="block rounded-xl bg-slate-50 p-4 dark:bg-slate-900">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $release->release_name }}</p>
                            <span class="text-xs font-bold text-slate-500">{{ $release->status }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ $release->creation_path }} · {{ $release->aggregation_policy['method'] ?? 'No method' }}</p>
                        @if (($release->collection_config['allows_multi_respondent'] ?? false) === true)
                            <p class="mt-1 text-xs text-slate-500">Multi-respondent: enabled · minimum {{ $release->collection_config['minimum_completed_respondents'] ?? 'unset' }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-admin-layout>
