<x-admin-layout title="Official Content">
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Official Vytte Content Control Center</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Govern the platform-owned content that workspaces consume but cannot change.</p>
        </div>
        <span class="rounded-full bg-vytte-50 px-3 py-1 text-xs font-bold text-vytte-700 dark:bg-vytte-900/30 dark:text-vytte-300">Vytte Platform Admin</span>
    </div>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-6">
        @foreach ($stats as $label => $value)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</p>
                <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ([
            ['title' => 'Question Groups', 'body' => 'Group reusable question identities inside an official department or focused scope.', 'route' => 'admin.question-groups.index'],
            ['title' => 'Question Identities', 'body' => 'Create and inspect stable reusable questions before versioning.', 'route' => 'admin.question-identities.index'],
            ['title' => 'Question Versions', 'body' => 'Approve and publish immutable question wording, response types, and scoring metadata.', 'route' => 'admin.question-versions.index'],
            ['title' => 'Framework Versions', 'body' => 'Review sections, indicators, placements, scoring profile, and publication state.', 'route' => 'admin.framework-versions.index'],
            ['title' => 'Catalogue Releases', 'body' => 'Inspect the releases that power comprehensive and focused assessment creation.', 'route' => 'admin.catalogue-releases.index'],
            ['title' => 'Facility Profiles', 'body' => 'See which departments/services are valid for each setting profile.', 'route' => 'admin.facility-profiles.index'],
            ['title' => 'Scoring Policies', 'body' => 'View frozen scoring versions, aggregation policies, and multi-respondent settings.', 'route' => 'admin.scoring-policies.index'],
            ['title' => 'Analytical Domains', 'body' => 'Inspect platform governed taxonomies and mappings for dashboards and reports.', 'route' => 'admin.domain-taxonomies.index'],
        ] as $item)
            <a href="{{ route($item['route']) }}" class="rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-vytte-300 hover:shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $item['title'] }} →</p>
                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $item['body'] }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-6 grid gap-4 xl:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Latest question versions</h2>
            <div class="mt-3 space-y-3">
                @forelse ($latestQuestionVersions as $version)
                    <a href="{{ route('admin.question-versions.show', $version) }}" class="block rounded-xl bg-slate-50 p-3 text-xs dark:bg-slate-900">
                        <span class="font-semibold text-slate-900 dark:text-white">{{ $version->question?->question_code }}</span>
                        <span class="ml-2 rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $version->status }}</span>
                        <p class="mt-1 line-clamp-2 text-slate-500">{{ $version->question_text }}</p>
                    </a>
                @empty
                    <p class="text-xs text-slate-500">No question versions yet.</p>
                @endforelse
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Latest frameworks</h2>
            <div class="mt-3 space-y-3">
                @forelse ($latestFrameworks as $framework)
                    <a href="{{ route('admin.framework-versions.show', $framework) }}" class="block rounded-xl bg-slate-50 p-3 text-xs dark:bg-slate-900">
                        <span class="font-semibold text-slate-900 dark:text-white">{{ $framework->display_name }}</span>
                        <span class="ml-2 rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $framework->status }}</span>
                        <p class="mt-1 text-slate-500">{{ $framework->module?->module_name }}</p>
                    </a>
                @empty
                    <p class="text-xs text-slate-500">No framework versions yet.</p>
                @endforelse
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Latest catalogue releases</h2>
            <div class="mt-3 space-y-3">
                @forelse ($latestReleases as $release)
                    <a href="{{ route('admin.catalogue-releases.show', $release) }}" class="block rounded-xl bg-slate-50 p-3 text-xs dark:bg-slate-900">
                        <span class="font-semibold text-slate-900 dark:text-white">{{ $release->release_name }}</span>
                        <span class="ml-2 rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $release->status }}</span>
                        <p class="mt-1 text-slate-500">{{ $release->creation_path }}</p>
                    </a>
                @empty
                    <p class="text-xs text-slate-500">No catalogue releases yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-admin-layout>
