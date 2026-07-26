<x-app-layout title="Start assessment">
    <div class="max-w-3xl mx-auto">
        @php
            $target = $assessment->target;
            $areas = $assessment->moduleScope->where('in_scope', true)->map(fn ($m) => $m->module?->module_name)->filter()->values();
            $title = $assessment->catalogueRelease?->release_name
                ?? ($areas->count() === 1 ? $areas->first() : 'Health Assessment');
            $questionCount = collect($assessment->snapshot?->payload ?? [])->sum(fn ($m) => count($m['questions'] ?? []));
            $hasResponses = $assessment->responses()->exists();
        @endphp

        <a href="{{ route('projects.show', $assessment->project_id) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 mb-3">
            ← {{ $assessment->project?->name }}
        </a>

        <div class="mb-6">
            <p class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide">Ready to begin</p>
            <h1 class="mt-0.5 text-2xl font-bold text-slate-900 dark:text-white">{{ $title }}</h1>
            @if ($target)
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $target->name }}</p>
            @endif
        </div>

        {{-- What's inside --}}
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <div class="flex flex-wrap gap-x-8 gap-y-2 text-sm">
                <div>
                    <p class="text-xs text-slate-400 dark:text-slate-500">Questions</p>
                    <p class="font-bold text-slate-900 dark:text-white">{{ $questionCount ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 dark:text-slate-500">Areas covered</p>
                    <p class="font-bold text-slate-900 dark:text-white">{{ $areas->count() ?: 1 }}</p>
                </div>
            </div>
            @if ($areas->isNotEmpty())
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ $areas->join(' · ') }}</p>
            @endif
        </div>

        <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">How do you want to collect answers?</h2>

        <div class="grid gap-4 sm:grid-cols-2">
            {{-- Self-assessment --}}
            <div class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <div class="w-10 h-10 rounded-xl bg-vytte-50 dark:bg-vytte-900/30 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-vytte-600 dark:text-vytte-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zM6 12a4 4 0 00-4 4v1h16v-1a4 4 0 00-4-4H6z"/></svg>
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Answer it yourself</h3>
                <p class="mt-1 flex-1 text-xs text-slate-500 dark:text-slate-400">You (or your team) work through the questions now. Best when one person is assessing on the ground.</p>
                <a href="{{ route('assessments.run', $assessment) }}"
                   class="mt-4 inline-flex items-center justify-center gap-1.5 rounded-xl bg-vytte-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-vytte-800 transition-colors">
                    {{ $hasResponses ? 'Continue answering' : 'Start answering' }} →
                </a>
            </div>

            {{-- Share to others — available for any assessment; you decide. --}}
            <div class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <div class="w-10 h-10 rounded-xl bg-vytte-50 dark:bg-vytte-900/30 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-vytte-600 dark:text-vytte-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/></svg>
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Share it for others to answer</h3>
                <p class="mt-1 flex-1 text-xs text-slate-500 dark:text-slate-400">Send a link so several people can answer independently. Their answers are combined into one result, which you review and finalise.</p>
                <a href="{{ route('assessments.respondent-collection', $assessment) }}"
                   class="mt-4 inline-flex items-center justify-center gap-1.5 rounded-xl border border-vytte-600 px-4 py-2.5 text-sm font-semibold text-vytte-700 dark:text-vytte-400 hover:bg-vytte-50 dark:hover:bg-vytte-900/20 transition-colors">
                    Set up collection →
                </a>
            </div>
        </div>

        {{-- Optional: tailor the assessment with your own questions before you begin. --}}
        <div class="mt-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-4 py-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-slate-500 dark:text-slate-400">Want to ask something the template doesn't? Add your own questions — scored on their own, in a “Tailored by your team” section.</p>
            <a href="{{ route('assessments.custom.edit', $assessment) }}" class="flex-shrink-0 text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:text-vytte-900 dark:hover:text-vytte-200">Add your own questions →</a>
        </div>
    </div>
</x-app-layout>
