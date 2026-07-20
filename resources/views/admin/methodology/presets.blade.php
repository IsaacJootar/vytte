<x-admin-layout title="Starting Points">
    <div class="mb-5">
        <a href="{{ route('admin.methodology.index') }}" class="link-nav text-sm">&larr; Methodology</a>
        <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Starting Points</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Saved combinations such as "Malaria Baseline Assessment". A starting point preselects an objective,
            its health domains, a template and a set of lenses — so a familiar name is available without the
            subject having to exist twice in the model.
        </p>
    </div>

    <x-admin-table
        search-label="Search starting points"
        search-placeholder="Search by name"
        :headings="['Starting point', 'Objective', 'Health domains', 'Template', 'Lenses']"
        :paginator="$presets"
        empty="No starting points match your search"
        empty-hint="Try a different search.">

        @foreach ($presets as $preset)
            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $preset->preset_name }}</td>
                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $preset->objective?->objective_name ?? '—' }}</td>
                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                    {{ collect($preset->health_domain_codes ?? [])->map(fn ($c) => Str::of($c)->replace('_', ' ')->title())->join(', ') ?: '—' }}
                </td>
                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                    {{ $preset->template_code ? Str::of($preset->template_code)->replace('_', ' ')->title() : '—' }}
                </td>
                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                    {{ collect($preset->analysis_lens_codes ?? [])->map(fn ($c) => Str::of($c)->replace('_', ' ')->title())->join(', ') ?: '—' }}
                </td>
            </tr>
        @endforeach
    </x-admin-table>
</x-admin-layout>
