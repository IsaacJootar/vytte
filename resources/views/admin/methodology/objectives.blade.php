<x-admin-layout title="Objectives">
    <div class="mb-5">
        <a href="{{ route('admin.methodology.index') }}" class="link-nav text-sm">&larr; Methodology</a>
        <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Assessment Objectives</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Why someone runs an assessment. Objectives are purposes only — the subject being assessed lives in
            health domains, so "Baseline" is an objective and "Malaria" is not.
        </p>
    </div>

    <x-admin-table
        search-label="Search objectives"
        search-placeholder="Search by name, purpose or description"
        :headings="['Objective', 'Answers the question', 'Group', 'Suggests']"
        :paginator="$objectives"
        empty="No objectives match your search"
        empty-hint="Try a different search, or clear the filters.">

        <x-slot:filters>
            <x-admin-filter label="Group" name="objective_group">
                <option value="">All groups</option>
                @foreach ($groups as $code => $label)
                    <option value="{{ $code }}" @selected(request('objective_group') === $code)>{{ $label }}</option>
                @endforeach
            </x-admin-filter>
        </x-slot:filters>

        @foreach ($objectives as $objective)
            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                <td class="px-4 py-3">
                    <p class="font-semibold text-slate-900 dark:text-white">{{ $objective->objective_name }}</p>
                    <p class="mt-0.5 max-w-md text-xs text-slate-500 dark:text-slate-400">{{ $objective->description }}</p>
                </td>
                <td class="px-4 py-3 text-sm italic text-slate-600 dark:text-slate-300">
                    {{ $objective->question_it_answers }}
                </td>
                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $objective->groupLabel() }}</td>
                <td class="px-4 py-3">
                    @php $count = $objective->recommendations->count(); @endphp
                    @if ($count > 0)
                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                            {{ $count }} {{ Str::plural('suggestion', $count) }}
                        </span>
                    @else
                        <span class="text-xs text-slate-400">None yet</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-admin-table>
</x-admin-layout>
