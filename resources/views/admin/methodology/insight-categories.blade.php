<x-admin-layout title="Insight Categories">
    <div class="mb-5">
        <a href="{{ route('admin.methodology.index') }}" class="link-nav text-sm">&larr; Methodology</a>
        <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Insight Categories</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            The shapes a finding can take in a report. Diagnostic categories point at a cause rather than
            describing a symptom.
        </p>
    </div>

    <x-admin-table
        search-label="Search categories"
        search-placeholder="Search by category name"
        :headings="['Category', 'Reads as', 'What it means', 'Diagnostic']"
        :paginator="$categories"
        empty="No categories match your search"
        empty-hint="Try a different search, or clear the filters.">

        <x-slot:filters>
            <x-admin-filter label="Reads as" name="polarity">
                <option value="">Any kind</option>
                <option value="POSITIVE" @selected(request('polarity') === 'POSITIVE')>Good news</option>
                <option value="NEGATIVE" @selected(request('polarity') === 'NEGATIVE')>Needs attention</option>
                <option value="NEUTRAL" @selected(request('polarity') === 'NEUTRAL')>Neutral</option>
            </x-admin-filter>
        </x-slot:filters>

        @foreach ($categories as $category)
            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $category->category_name }}</td>
                <td class="px-4 py-3">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                        {{ match ($category->polarity) {
                            'POSITIVE' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
                            'NEGATIVE' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
                            default => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                        } }}">
                        {{ $category->polarityLabel() }}
                    </span>
                </td>
                <td class="px-4 py-3 max-w-lg text-sm text-slate-600 dark:text-slate-300">{{ $category->description }}</td>
                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                    {{ $category->is_diagnostic ? 'Points at a cause' : 'Describes a symptom' }}
                </td>
            </tr>
        @endforeach
    </x-admin-table>
</x-admin-layout>
