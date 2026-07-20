<x-admin-layout title="Template Catalogue">
    <div class="mb-5">
        <a href="{{ route('admin.methodology.index') }}" class="link-nav text-sm">&larr; Methodology</a>
        <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Template Catalogue</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Official starting points. A template is a suggestion, never a constraint — whatever an author
            starts from, the builder and publication rules remain the only authority on what can be published.
        </p>
    </div>

    <x-admin-table
        search-label="Search templates"
        search-placeholder="Search by template name or description"
        :headings="['Template', 'Covers', 'Typical length', 'What it is for']"
        :paginator="$templates"
        empty="No templates match your search"
        empty-hint="Try a different search, or clear the filters.">

        <x-slot:filters>
            <x-admin-filter label="Covers" name="scope_type">
                <option value="">Any breadth</option>
                <option value="ENTERPRISE" @selected(request('scope_type') === 'ENTERPRISE')>Whole organisation</option>
                <option value="FOCUSED" @selected(request('scope_type') === 'FOCUSED')>One subject area</option>
            </x-admin-filter>
        </x-slot:filters>

        @foreach ($templates as $template)
            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $template->template_name }}</td>
                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $template->scopeLabel() }}</td>
                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                    {{ $template->typical_duration_minutes ? $template->typical_duration_minutes.' min' : '—' }}
                </td>
                <td class="px-4 py-3 max-w-lg text-sm text-slate-600 dark:text-slate-300">{{ $template->description }}</td>
            </tr>
        @endforeach
    </x-admin-table>
</x-admin-layout>
