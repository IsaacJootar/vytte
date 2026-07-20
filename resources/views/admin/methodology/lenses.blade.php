<x-admin-layout title="Analysis Lenses">
    <div class="mb-5">
        <a href="{{ route('admin.methodology.index') }}" class="link-nav text-sm">&larr; Methodology</a>
        <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Analysis Lenses</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Ways of reading the same results. A lens never changes a score — it decides which findings are
            surfaced and in what order, so one assessment can produce a risk report and a performance report
            that read very differently.
        </p>
    </div>

    <x-admin-table
        search-label="Search lenses"
        search-placeholder="Search by name or the question it answers"
        :headings="['Lens', 'Answers the question', 'What it does']"
        :paginator="$lenses"
        empty="No lenses match your search"
        empty-hint="Try a different search.">

        @foreach ($lenses as $lens)
            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $lens->lens_name }}</td>
                <td class="px-4 py-3 text-sm italic text-slate-600 dark:text-slate-300">{{ $lens->question_it_answers }}</td>
                <td class="px-4 py-3 max-w-lg text-sm text-slate-600 dark:text-slate-300">{{ $lens->description }}</td>
            </tr>
        @endforeach
    </x-admin-table>
</x-admin-layout>
