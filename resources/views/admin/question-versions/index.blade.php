<x-admin-layout title="Question Versions">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Question Versions</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Every version of every question, including replaced and archived ones. Approve and publish exact immutable wording here.
        </p>
    </div>

    <x-admin-table
        search-label="Search"
        search-placeholder="Search question wording or code"
        :headings="['Version', 'Question', 'Answer format', 'Status', 'Published']"
        :paginator="$versions"
        empty="No question versions match your search"
        empty-hint="Try a different search, or clear the filters.">

        <x-slot:filters>
            <x-admin-filter label="Status" name="status">
                <option value="">Any status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(strtolower(str_replace('_', ' ', $status))) }}</option>
                @endforeach
            </x-admin-filter>
        </x-slot:filters>

        @foreach ($versions as $version)
            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">v{{ $version->version_number }}</td>
                <td class="px-4 py-3">
                    <a href="{{ route('admin.question-versions.show', $version) }}" class="font-semibold text-slate-900 hover:text-vytte-700 hover:underline dark:text-white dark:hover:text-vytte-300">
                        {{ $version->question_text }}
                    </a>
                    <p class="mt-0.5 text-xs text-slate-400">{{ $version->question?->question_code }}</p>
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ \App\Support\AnswerFormat::labelForTypeCode($version->questionType?->type_code, $version->options ?? []) }}
                </td>
                <td class="px-4 py-3"><x-assessment-status-badge :status="$version->status" /></td>
                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $version->published_at?->format('j M Y') ?? '—' }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.question-versions.show', $version) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-vytte-700 hover:underline dark:text-vytte-300">
                        Open <span aria-hidden="true">→</span>
                    </a>
                </td>
            </tr>
        @endforeach
    </x-admin-table>
</x-admin-layout>
