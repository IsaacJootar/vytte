<x-app-layout title="Assessments">

    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Assessments</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">All assessments across your projects.</p>
    </div>

    @if ($assessments->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 px-5 py-14 flex flex-col items-center text-center">
            <div class="w-12 h-12 rounded-xl mb-4 flex items-center justify-center"
                 style="background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%)">
                <svg class="w-6 h-6 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">No assessments yet</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 max-w-xs mb-5">
                Open a project and start an assessment to see results here.
            </p>
            <a href="{{ route('projects.index') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-vytte-700 text-white text-sm font-semibold rounded-lg hover:bg-vytte-800 transition-colors">
                Go to Projects
            </a>
        </div>
    @else
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach ($assessments as $assessment)
                    @php
                        $score = $assessment->score;
                        $module = $assessment->moduleScope->first()?->module;
                        $isComplete = $assessment->status === 'COMPLETE';

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
                                <a href="{{ route('assessments.run', $assessment) }}"
                                   class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:underline whitespace-nowrap">
                                    Continue
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
