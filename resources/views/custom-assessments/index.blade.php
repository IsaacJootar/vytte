<x-app-layout title="Custom assessments">
    <div class="mb-5">
        <a href="{{ route('assessments.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 mb-2">
            ← Assessments
        </a>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Custom assessments — your own</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400 max-w-2xl">Questionnaires you design yourself, for your own context.</p>
            </div>
            <a href="{{ route('custom-assessments.create') }}"
               class="inline-flex flex-shrink-0 items-center gap-1.5 rounded-xl bg-vytte-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-vytte-800 transition-colors self-start">
                <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                New custom assessment
            </a>
        </div>
    </div>

    {{-- Make the model unmistakable. --}}
    <x-help-callout id="custom-assessments" title="How custom assessments work">
        <ul class="text-xs text-slate-600 dark:text-slate-300 space-y-1 list-disc list-inside">
            <li>These are <span class="font-semibold">your own questionnaires</span> — private to this workspace.</li>
            <li>They are <span class="font-semibold">not part of the official Vytte score</span> and don't change the governed templates. They get their own private result.</li>
            <li>Use them for surveys, local context, or internal reviews that the official assessments don't cover.</li>
        </ul>
    </x-help-callout>

    <x-plan-gate feature="workspace_custom_assessments">
        @if ($designs->isEmpty())
            <x-empty-state
                icon="document-text"
                title="No custom assessments yet"
                message="Design your own questionnaire for a survey, local context, or an internal review. It stays private to your workspace."
                :action="route('custom-assessments.create')"
                action-label="Create a custom assessment" />
        @else
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                        <tr><th class="px-5 py-3">Design</th><th class="px-5 py-3">Scope</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach ($designs as $design)
                            @php
                                $statusStyle = match ($design->status) {
                                    'ACTIVE' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
                                    'ARCHIVED' => 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400',
                                    default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors">
                                <td class="px-5 py-3"><p class="font-semibold text-slate-900 dark:text-white">{{ $design->title }}</p><p class="text-xs text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Str::limit($design->purpose, 80) }}</p></td>
                                <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $design->scope ?? '—' }}</td>
                                <td class="px-5 py-3"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $statusStyle }}">{{ ucfirst(strtolower($design->status)) }}</span></td>
                                <td class="px-5 py-3 text-right"><a href="{{ route('custom-assessments.show', $design) }}" class="text-sm font-semibold text-vytte-700 dark:text-vytte-300">Open ›</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($designs->hasPages())
                <div class="mt-4">{{ $designs->links() }}</div>
            @endif
        @endif
    </x-plan-gate>
</x-app-layout>
