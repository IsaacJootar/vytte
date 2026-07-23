<x-app-layout title="Dashboard">

    @php
        $workspace = auth()->user()->activeWorkspace;
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $firstName = explode(' ', auth()->user()->name)[0];

        $avgBand = match (true) {
            $avgScore === null  => null,
            $avgScore >= 70     => 'strong',
            $avgScore >= 45     => 'moderate',
            default             => 'weak',
        };
        $scoreCardClass = match ($avgBand) {
            'strong'   => 'metric-strong',
            'moderate' => 'metric-moderate',
            'weak'     => 'metric-weak',
            default    => 'metric-none',
        };
        $distTotal = max(1, $distribution['strong'] + $distribution['moderate'] + $distribution['weak']);
        $hasScores = $distTotal > 1 || ($distribution['strong'] + $distribution['moderate'] + $distribution['weak']) > 0;
    @endphp

    {{-- Header --}}
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                {{ $greeting }}, {{ $firstName }}
            </h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                {{ $workspace?->name ?? 'Your workspace' }} · Here's your overview
            </p>
        </div>
        <a href="{{ route('projects.create') }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-vytte-700 text-white text-sm font-semibold rounded-lg hover:bg-vytte-800 transition-colors duration-150"
           style="box-shadow: 0 2px 8px rgba(3,105,161,0.30);">
            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/>
            </svg>
            New Project
        </a>
    </div>

    {{-- Operational row: the daily work — what is being set up, what is out collecting,
         and how many responses have arrived. --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-4">
        <x-stat-card :tone="$operations['awaiting_publish'] > 0 ? 'moderate' : 'slate'"
                     label="Awaiting publication" :value="$operations['awaiting_publish']"
                     sub="Set up, not yet opened for responses" />
        <x-stat-card tone="blue" label="Collecting now" :value="$operations['collecting']"
                     sub="Published and gathering responses" />
        <x-stat-card tone="strong" label="Responses in" :value="$operations['responses']"
                     sub="Completed responses across all assessments" />
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5"
         x-data="{ loaded: false }" x-init="$nextTick(() => { loaded = true })">

        {{-- Active Projects --}}
        <div class="metric-card metric-blue">
            <div class="flex items-start justify-between mb-3">
                <p class="metric-card-label">Active Projects</p>
                <div class="metric-icon-badge">
                    <svg class="w-4 h-4 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                    </svg>
                </div>
            </div>
            <div x-show="!loaded">
                <div class="h-8 w-16 rounded-lg mb-2" style="background:rgba(255,255,255,0.15);"></div>
                <div class="h-3 w-28 rounded" style="background:rgba(255,255,255,0.10);"></div>
            </div>
            <div x-show="loaded" x-cloak>
                <p class="metric-card-value">{{ $activeProjectCount }}</p>
                <p class="metric-card-sub">
                    {{ $activeProjectCount === 1 ? '1 active project' : $activeProjectCount . ' active projects' }}
                </p>
            </div>
        </div>

        {{-- Assessments Run --}}
        <div class="metric-card metric-slate">
            <div class="flex items-start justify-between mb-3">
                <p class="metric-card-label">Assessments Run</p>
                <div class="metric-icon-badge">
                    <svg class="w-4 h-4 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </div>
            <div x-show="!loaded">
                <div class="h-8 w-16 rounded-lg mb-2" style="background:rgba(255,255,255,0.15);"></div>
                <div class="h-3 w-32 rounded" style="background:rgba(255,255,255,0.10);"></div>
            </div>
            <div x-show="loaded" x-cloak>
                <p class="metric-card-value">{{ $totalAssessments }}</p>
                <p class="metric-card-sub">
                    {{ $totalAssessments === 0 ? 'No assessments completed' : 'Completed assessments' }}
                </p>
            </div>
        </div>

        {{-- Avg Score --}}
        <div class="metric-card {{ $scoreCardClass }}">
            <div class="flex items-start justify-between mb-3">
                <p class="metric-card-label">Avg. Score</p>
                <div class="metric-icon-badge">
                    <svg class="w-4 h-4 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                    </svg>
                </div>
            </div>
            <div x-show="!loaded">
                <div class="h-8 w-16 rounded-lg mb-2" style="background:rgba(255,255,255,0.15);"></div>
                <div class="h-3 w-24 rounded" style="background:rgba(255,255,255,0.10);"></div>
            </div>
            <div x-show="loaded" x-cloak>
                @if ($avgScore !== null)
                    <p class="metric-card-value">{{ number_format($avgScore, 1) }}</p>
                    <p class="metric-card-sub capitalize">{{ $avgBand }} performance</p>
                @else
                    <p class="metric-card-value">—</p>
                    <p class="metric-card-sub">No scores yet</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Score distribution --}}
    @if ($hasScores)
        <div class="section-card mb-5"
             x-data="{ loaded: false }" x-init="$nextTick(() => { loaded = true })">
            <div class="section-card-header">
                <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Score Distribution</p>
            </div>
            <div class="px-5 py-4">
                <div x-show="!loaded">
                    <x-skeleton class="h-2.5 w-full rounded-full mb-4" />
                    <div class="flex gap-4">
                        <x-skeleton class="h-3 w-20 rounded" />
                        <x-skeleton class="h-3 w-20 rounded" />
                        <x-skeleton class="h-3 w-20 rounded" />
                    </div>
                </div>
                <div x-show="loaded" x-cloak>
                    @php
                        $strongPct   = round($distribution['strong']   / $distTotal * 100, 1);
                        $moderatePct = round($distribution['moderate'] / $distTotal * 100, 1);
                        $weakPct     = round($distribution['weak']     / $distTotal * 100, 1);
                    @endphp
                    <div class="flex rounded-full overflow-hidden h-2.5 mb-4 gap-px bg-slate-100 dark:bg-slate-700">
                        @if ($distribution['strong'] > 0)
                            <div class="bg-emerald-500 rounded-full transition-all" style="width: {{ $strongPct }}%"></div>
                        @endif
                        @if ($distribution['moderate'] > 0)
                            <div class="bg-amber-400 rounded-full transition-all" style="width: {{ $moderatePct }}%"></div>
                        @endif
                        @if ($distribution['weak'] > 0)
                            <div class="bg-red-500 rounded-full transition-all" style="width: {{ $weakPct }}%"></div>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-4 text-xs">
                        <span class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 flex-shrink-0"></span>
                            <span class="text-slate-600 dark:text-slate-300 font-medium">Strong</span>
                            <span class="font-bold text-slate-900 dark:text-white tabular-nums">{{ $distribution['strong'] }}</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"></span>
                            <span class="text-slate-600 dark:text-slate-300 font-medium">Moderate</span>
                            <span class="font-bold text-slate-900 dark:text-white tabular-nums">{{ $distribution['moderate'] }}</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0"></span>
                            <span class="text-slate-600 dark:text-slate-300 font-medium">Weak</span>
                            <span class="font-bold text-slate-900 dark:text-white tabular-nums">{{ $distribution['weak'] }}</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Recent projects --}}
        <div class="section-card"
             x-data="{ loaded: false }" x-init="$nextTick(() => { loaded = true })">
            <div class="section-card-header">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Recent Projects</h2>
                <a href="{{ route('projects.index') }}" class="text-xs text-vytte-700 font-semibold hover:text-vytte-800 transition-colors">
                    View all
                </a>
            </div>

            <div x-show="!loaded" class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach (range(1, 3) as $_)
                    <div class="flex items-center gap-3 px-5 py-3.5">
                        <div class="flex-1">
                            <x-skeleton class="h-3.5 w-40 rounded mb-2" />
                            <x-skeleton class="h-3 w-24 rounded" />
                        </div>
                        <x-skeleton class="h-3 w-12 rounded" />
                    </div>
                @endforeach
            </div>

            <div x-show="loaded" x-cloak>
                @if ($recentProjects->isEmpty())
                    <div class="px-5 py-10 flex flex-col items-center text-center">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-3" style="background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);">
                            <x-heroicon-o-folder class="w-5 h-5 text-vytte-600" />
                        </div>
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">No projects yet</p>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Create a project to start diagnosing health systems.</p>
                        <a href="{{ route('projects.create') }}"
                           class="mt-4 mb-1 inline-flex items-center gap-1.5 px-4 py-2 bg-vytte-700 text-white text-xs font-semibold rounded-lg hover:bg-vytte-800 transition-colors"
                           style="box-shadow: 0 2px 8px rgba(3,105,161,0.25);">
                            <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                            Create first project
                        </a>
                    </div>
                @else
                    <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach ($recentProjects as $project)
                            @php
                                $target   = $project->targets->first();
                                $typeName = $target?->targetType?->target_type_name;
                            @endphp
                            <a href="{{ route('projects.show', $project) }}"
                               class="flex items-center gap-3 px-5 py-3.5 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors group">
                                <div class="w-8 h-8 rounded-lg bg-vytte-50 dark:bg-vytte-900/30 flex items-center justify-center flex-shrink-0">
                                    <x-heroicon-o-folder class="w-4 h-4 text-vytte-600 dark:text-vytte-400" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $project->name }}</p>
                                    @if ($typeName)
                                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">{{ $typeName }}</p>
                                    @endif
                                </div>
                                <svg class="w-4 h-4 text-slate-300 dark:text-slate-600 flex-shrink-0 group-hover:text-vytte-400 transition-colors" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
                                </svg>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Recent assessments --}}
        <div class="section-card"
             x-data="{ loaded: false }" x-init="$nextTick(() => { loaded = true })">
            <div class="section-card-header">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Recent Assessments</h2>
            </div>

            <div x-show="!loaded" class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach (range(1, 3) as $_)
                    <div class="flex items-center gap-3 px-5 py-3.5">
                        <div class="flex-1">
                            <x-skeleton class="h-3.5 w-36 rounded mb-2" />
                            <x-skeleton class="h-3 w-24 rounded" />
                        </div>
                        <x-skeleton class="h-5 w-16 rounded-full" />
                    </div>
                @endforeach
            </div>

            <div x-show="loaded" x-cloak>
                @if ($recentAssessments->isEmpty())
                    <div class="px-5 py-10 flex flex-col items-center text-center">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-3" style="background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%);">
                            <x-heroicon-o-clipboard-document-list class="w-5 h-5 text-emerald-600" />
                        </div>
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">No completed assessments</p>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Submit an assessment to see results here.</p>
                    </div>
                @else
                    <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach ($recentAssessments as $assessment)
                            @php
                                $overallScore = $assessment->score?->overall_score !== null
                                    ? (float) $assessment->score->overall_score
                                    : null;
                                $assessmentTitle = $assessment->reportSnapshot?->payload['title']
                                    ?? $assessment->catalogueRelease?->release_name
                                    ?? ($assessment->moduleScope->where('in_scope', true)->count() === 1
                                        ? $assessment->moduleScope->where('in_scope', true)->first()?->module?->module_name
                                        : 'Comprehensive Health Assessment');
                            @endphp
                            <a href="{{ route('assessments.results', $assessment) }}"
                               class="flex items-center gap-3 px-5 py-3.5 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors group">
                                <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                                    <x-heroicon-o-clipboard-document-list class="w-4 h-4 text-slate-500 dark:text-slate-400" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">
                                        {{ $assessmentTitle }}
                                    </p>
                                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">
                                        {{ $assessment->project?->name }}
                                        @if ($assessment->completed_at)
                                            · {{ $assessment->completed_at->format('d M Y') }}
                                        @endif
                                    </p>
                                </div>
                                <x-score-pill :score="$overallScore" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

    </div>

</x-app-layout>
