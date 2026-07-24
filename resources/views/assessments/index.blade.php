<x-app-layout title="Assessments">

    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Assessments</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">All assessments across your projects.</p>
    </div>

    @if ($assessments->isEmpty())
        <x-empty-state
            icon="clipboard-document-list"
            title="No assessments yet"
            message="Open a project and run its first assessment. Answer the questions, and your report appears the moment it is submitted."
            :action="route('projects.index')"
            action-label="Go to projects" />
    @else
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach ($assessments as $assessment)
                    @php
                        $score = $assessment->score;
                        $module = $assessment->moduleScope->first()?->module;
                        $isComplete = $assessment->status === 'COMPLETE';
                        $isMultiRespondent = $assessment->snapshot?->collection_config['allows_multi_respondent'] ?? false;

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
                            @if ($isComplete)
                                <span class="flex w-2.5 h-2.5 rounded-full bg-emerald-500 mt-0.5"></span>
                            @else
                                <span class="flex w-2.5 h-2.5 rounded-full bg-amber-400 mt-0.5"></span>
                            @endif
                        </div>

                        {{-- Main info --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-[10px] font-bold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide">
                                    {{ $module?->module_code ?? '—' }}
                                </span>
                                <span class="text-[10px] text-slate-300 dark:text-slate-600">·</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $isComplete ? 'Complete' : 'In progress' }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-white truncate">
                                {{ $assessment->target?->name ?? '—' }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                                {{ $assessment->project?->name }}
                                @if ($assessment->completed_at)
                                    · {{ $assessment->completed_at->format('d M Y') }}
                                @elseif ($assessment->started_at)
                                    · Started {{ $assessment->started_at->format('d M Y') }}
                                @endif
                            </p>
                        </div>

                        {{-- Score badge --}}
                        <div class="flex-shrink-0 flex items-center gap-3">
                            @if ($score && $score->overall_score !== null)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold {{ $scoreBg }} {{ $scoreColor }}">
                                    {{ number_format($score->overall_score, 1) }}
                                </span>
                            @elseif ($isComplete)
                                <span class="text-xs text-slate-400 dark:text-slate-500">—</span>
                            @endif

                            {{-- Action link --}}
                            @if ($isComplete)
                                <a href="{{ route('assessments.results', $assessment) }}"
                                   class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:underline whitespace-nowrap">
                                    View results
                                </a>
                            @else
                                <a href="{{ $isMultiRespondent ? route('assessments.respondent-collection', $assessment) : route('assessments.run', $assessment) }}"
                                   class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:underline whitespace-nowrap">
                                    {{ $isMultiRespondent ? 'Review collection' : 'Continue' }}
                                </a>
                            @endif
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
