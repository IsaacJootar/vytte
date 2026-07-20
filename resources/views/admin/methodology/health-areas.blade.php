<x-admin-layout title="Health Areas">
    <div class="mb-5">
        <a href="{{ route('admin.methodology.index') }}" class="link-nav text-sm">&larr; Methodology</a>
        <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Health Areas</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Subdivisions of a health domain. Domains were flat, so HIV could not distinguish testing from
            treatment from prevention. Areas give enough resolution to recommend content precisely.
        </p>
    </div>

    <x-admin-table
        search-label="Search health areas"
        search-placeholder="Search by area name"
        :headings="['Area', 'Health domain']"
        :paginator="$areas"
        empty="No areas match your search"
        empty-hint="Try a different search, or clear the filters.">

        <x-slot:filters>
            <x-admin-filter label="Health domain" name="health_domain_id">
                <option value="">All domains</option>
                @foreach ($domains as $domain)
                    <option value="{{ $domain->health_domain_id }}" @selected((int) request('health_domain_id') === (int) $domain->health_domain_id)>
                        {{ $domain->domain_name }}
                    </option>
                @endforeach
            </x-admin-filter>
        </x-slot:filters>

        @foreach ($areas as $area)
            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $area->area_name }}</td>
                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $area->healthDomain?->domain_name ?? '—' }}</td>
            </tr>
        @endforeach
    </x-admin-table>
</x-admin-layout>
