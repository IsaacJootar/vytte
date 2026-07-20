<x-admin-layout title="Question Library">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Question Library</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Official questions you can reuse across assessments. Wording is locked once approved.
        </p>
    </div>

    <x-admin-table
        search-placeholder="Search question wording or code"
        :headings="['Question', 'Answer format', 'Department', 'Status', 'Used in']"
        :paginator="$questions"
        empty="No questions match your search"
        empty-hint="Try a different search, or add a question to the library.">

        <x-slot:action>
            <a href="{{ route('admin.question-identities.create') }}"
               class="inline-flex items-center gap-1.5 rounded-xl bg-vytte-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-500">
                + Add question
            </a>
        </x-slot:action>

        <x-slot:filters>
            <x-admin-filter label="Department" name="module_id">
                <option value="">All departments</option>
                @foreach ($modules as $module)
                    <option value="{{ $module->module_id }}" @selected(request('module_id') == $module->module_id)>{{ $module->module_name }}</option>
                @endforeach
            </x-admin-filter>
            <x-admin-filter label="Answer format" name="format">
                <option value="">Any format</option>
                @foreach (collect($formats)->unique('type_code') as $format)
                    <option value="{{ $format['type_code'] }}" @selected(request('format') === $format['type_code'])>{{ $format['label'] }}</option>
                @endforeach
            </x-admin-filter>
            <x-admin-filter label="Status" name="readiness">
                <option value="">Any status</option>
                <option value="ready" @selected(request('readiness') === 'ready')>Ready to use</option>
                <option value="pending" @selected(request('readiness') === 'pending')>Needs approval</option>
            </x-admin-filter>
        </x-slot:filters>

        @foreach ($questions as $question)
            @php
                $published = $question->versions->firstWhere('status', \App\Models\QuestionVersion::STATUS_PUBLISHED);
                $current = $published ?? $question->versions->sortByDesc('version_number')->first();
                $usedIn = (int) ($usage[$question->question_id] ?? 0);
            @endphp
            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.question-identities.show', $question) }}" class="font-semibold text-slate-900 hover:text-vytte-700 hover:underline dark:text-white dark:hover:text-vytte-300">
                        {{ $current?->question_text ?: $question->question_text }}
                    </a>
                    @if (filled($current?->options))
                        <p class="mt-0.5 max-w-lg truncate text-xs text-slate-500 dark:text-slate-400">
                            {{ collect($current->options)->pluck('option_label')->filter()->join(' · ') }}
                        </p>
                    @endif
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ \App\Support\AnswerFormat::labelForTypeCode($question->questionType?->type_code, $current?->options ?? []) }}
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $question->module?->module_name ?? '—' }}</td>
                <td class="px-4 py-3">
                    @if ($published)
                        <span class="inline-block rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">Ready to use</span>
                    @else
                        <span class="inline-block rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Needs approval</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ $usedIn === 0 ? 'Not used yet' : $usedIn.' '.Str::plural('assessment', $usedIn) }}
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.question-identities.show', $question) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-vytte-700 hover:underline dark:text-vytte-300">
                        Open <span aria-hidden="true">→</span>
                    </a>
                </td>
            </tr>
        @endforeach
    </x-admin-table>
</x-admin-layout>
