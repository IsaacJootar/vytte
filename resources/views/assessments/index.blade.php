<x-app-layout title="Assessments">

    {{-- Header --}}
    <div class="mb-4">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Assessments</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Every assessment across your projects. Open one for its full details.</p>
    </div>

    {{-- Filter tabs — drafts, collecting, and complete are one click away, never lost. --}}
    <div class="mb-5 flex flex-wrap gap-1 border-b border-slate-200 dark:border-slate-700">
        @foreach (['all' => 'All', 'draft' => 'Drafts', 'collecting' => 'Collecting', 'complete' => 'Complete'] as $key => $label)
            <a href="{{ route('assessments.index', array_filter(['tab' => $key === 'all' ? null : $key, 'search' => $search ?: null])) }}"
               class="inline-flex items-center gap-1.5 -mb-px px-3 py-2 text-sm font-semibold border-b-2 transition-colors
                   {{ $tab === $key ? 'border-vytte-700 text-vytte-700 dark:text-vytte-400 dark:border-vytte-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                {{ $label }}
                <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $tab === $key ? 'bg-vytte-100 text-vytte-700 dark:bg-vytte-900/40 dark:text-vytte-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400' }}">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </div>

    {{-- Search (server-side; the loader is suppressed so it just searches). --}}
    @if (! $assessments->isEmpty() || $search !== '')
        <form method="GET" action="{{ route('assessments.index') }}" class="mb-5 flex gap-2" data-no-loading>
            @if ($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 dark:text-slate-500 pointer-events-none" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/>
                </svg>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by target or project…"
                       class="w-full pl-9 pr-3 py-2 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-vytte-700/20 focus:border-vytte-700 transition-shadow">
            </div>
            @if ($search !== '')
                <a href="{{ route('assessments.index', array_filter(['tab' => $tab === 'all' ? null : $tab])) }}" class="px-3 py-2 text-sm font-medium text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 transition-colors">Clear</a>
            @endif
        </form>
    @endif

    @if ($assessments->isEmpty())
        @if ($search !== '')
            <x-empty-state icon="clipboard-document-list" title='No assessments match "{{ $search }}"' message="Try a different target or project name." />
        @elseif ($tab !== 'all')
            <x-empty-state icon="clipboard-document-list" title="Nothing here yet" :message="'No '.($tab === 'draft' ? 'draft' : $tab).' assessments right now.'" />
        @else
            <x-empty-state
                icon="clipboard-document-list"
                title="No assessments yet"
                message="Open a project and run its first assessment. Answer the questions, and your report appears the moment it is submitted."
                :action="route('projects.index')"
                action-label="Go to projects" />
        @endif
    @else
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach ($assessments as $assessment)
                    @php
                        $score = $assessment->score;
                        $module = $assessment->moduleScope->first()?->module;
                        $isComplete = $assessment->status === 'COMPLETE';

                        // The precise state of this row, so drafts and collections read clearly.
                        $rowStatus = $isComplete ? 'Complete'
                            : ($assessment->publish_status === 'PUBLISHED'
                                ? ($assessment->isClosed() ? 'Collection closed' : 'Collecting')
                                : (($assessment->responses_count ?? 0) > 0 ? 'Answering' : 'Draft'));
                        $dotColor = match ($rowStatus) {
                            'Complete' => 'bg-emerald-500',
                            'Collecting' => 'bg-vytte-500',
                            'Answering' => 'bg-amber-400',
                            default => 'bg-slate-300 dark:bg-slate-600',
                        };

                        if ($score && $score->overall_score !== null) {
                            $s = (float) $score->overall_score;
                            $band = $s >= 70 ? 'strong' : ($s >= 45 ? 'moderate' : 'weak');
                            $scoreColor = match($band) {
                                'strong'   => 'text-emerald-600 dark:text-emerald-400',
                                'moderate' => 'text-amber-600 dark:text-amber-400',
                                'weak'     => 'text-red-600 dark:text-red-400',
                            };
                            $scoreBg = match($band) {
                                'strong'   => 'bg-emerald-50 dark:bg-emerald-900/20',
                                'moderate' => 'bg-amber-50 dark:bg-amber-900/20',
                                'weak'     => 'bg-red-50 dark:bg-red-900/20',
                            };
                        }
                    @endphp
                    <div class="flex items-start gap-4 px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors">

                        {{-- Status dot --}}
                        <div class="flex-shrink-0 mt-1">
                            <span class="flex w-2.5 h-2.5 rounded-full mt-0.5 {{ $dotColor }}"></span>
                        </div>

                        {{-- Main info — clicking it opens the assessment's hub (its full details).
                             The subject leads so each row is recognisable, even the same subject
                             retaken later, told apart by its date. --}}
                        <a href="{{ route('assessments.show', $assessment) }}" class="flex-1 min-w-0 group">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-[10px] font-bold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide">
                                    {{ $module?->module_code ?? '—' }}
                                </span>
                                <span class="text-[10px] text-slate-300 dark:text-slate-600">·</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $rowStatus }}</span>
                            </div>
                            <p class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-white truncate group-hover:text-vytte-700 dark:group-hover:text-vytte-400 transition-colors">
                                {{ $assessment->catalogueRelease?->release_name ?? $module?->module_name ?? 'Assessment' }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500 truncate">
                                {{ $assessment->target?->name ?? '—' }}
                                @if ($assessment->project?->name)
                                    · {{ $assessment->project->name }}
                                @endif
                                @if ($assessment->completed_at)
                                    · {{ $assessment->completed_at->format('d M Y') }}
                                @elseif ($assessment->started_at)
                                    · Started {{ $assessment->started_at->format('d M Y') }}
                                @endif
                            </p>
                        </a>

                        {{-- Score + a clear way into the hub --}}
                        <div class="flex-shrink-0 flex items-center gap-3">
                            @if ($score && $score->overall_score !== null)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold {{ $scoreBg }} {{ $scoreColor }}">
                                    {{ number_format($score->overall_score, 1) }}
                                </span>
                            @endif
                            <a href="{{ route('assessments.show', $assessment) }}"
                               class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 hover:text-vytte-700 dark:hover:text-vytte-400 transition-colors whitespace-nowrap">
                                Details →
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($assessments->hasPages())
                <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-700">
                    {{ $assessments->links() }}
                </div>
            @endif
        </div>
    @endif

</x-app-layout>
