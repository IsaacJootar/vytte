<x-admin-layout title="Publishing">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Publishing</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            What workspaces can select today, and what has been replaced by a newer version.
        </p>
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            'Live now' => ['value' => $counts['live'], 'hint' => 'Workspaces can select these'],
            'Being prepared' => ['value' => $counts['draft'], 'hint' => 'Not yet available'],
            'Replaced' => ['value' => $counts['replaced'], 'hint' => 'A newer version exists'],
            'Archived' => ['value' => $counts['archived'], 'hint' => 'Withdrawn from use'],
        ] as $label => $stat)
            <div class="section-card p-4 dark:border-slate-700 dark:bg-slate-800">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $stat['value'] }}</p>
                <p class="mt-0.5 text-xs text-slate-400">{{ $stat['hint'] }}</p>
            </div>
        @endforeach
    </div>

    <x-admin-table
        search-placeholder="Search published assessments by name"
        :headings="['Assessment', 'Covers', 'Includes', 'Status', 'Published']"
        :paginator="$releases"
        empty="Nothing published yet"
        empty-hint="Assessments appear here once you publish them from the builder.">

        <x-slot:action>
            <a href="{{ route('admin.assessments.index') }}"
               class="inline-flex items-center gap-1.5 rounded-xl bg-vytte-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-500">
                Go to Assessments
            </a>
        </x-slot:action>

        <x-slot:filters>
            <x-admin-filter label="Status" name="status">
                <option value="">Any status</option>
                <option value="PUBLISHED" @selected(request('status') === 'PUBLISHED')>Live now</option>
                <option value="DRAFT" @selected(request('status') === 'DRAFT')>Being prepared</option>
                <option value="SUPERSEDED" @selected(request('status') === 'SUPERSEDED')>Replaced</option>
                <option value="ARCHIVED" @selected(request('status') === 'ARCHIVED')>Archived</option>
            </x-admin-filter>
            <x-admin-filter label="Type" name="creation_path">
                <option value="">Any type</option>
                <option value="FOCUSED" @selected(request('creation_path') === 'FOCUSED')>Focused</option>
                <option value="COMPREHENSIVE" @selected(request('creation_path') === 'COMPREHENSIVE')>Comprehensive</option>
            </x-admin-filter>
        </x-slot:filters>

        @foreach ($releases as $release)
            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.catalogue-releases.show', $release) }}" class="font-semibold text-slate-900 hover:text-vytte-700 hover:underline dark:text-white dark:hover:text-vytte-300">
                        {{ $release->release_name }}
                    </a>
                    @if ($release->description)
                        <p class="mt-0.5 max-w-md truncate text-xs text-slate-500 dark:text-slate-400">{{ $release->description }}</p>
                    @endif
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ $release->creation_path === 'FOCUSED'
                        ? ($release->healthDomain?->domain_name ?? 'One health area')
                        : ($release->facilityProfile?->profile_name ?? 'A facility type') }}
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ $release->department_framework_versions_count }} {{ Str::plural('department', $release->department_framework_versions_count) }}
                </td>
                <td class="px-4 py-3"><x-assessment-status-badge :status="$release->status" /></td>
                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                    {{ $release->published_at?->format('j M Y') ?? '—' }}
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.catalogue-releases.show', $release) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-vytte-700 hover:underline dark:text-vytte-300">
                        Open <span aria-hidden="true">→</span>
                    </a>
                </td>
            </tr>
        @endforeach
    </x-admin-table>
</x-admin-layout>
