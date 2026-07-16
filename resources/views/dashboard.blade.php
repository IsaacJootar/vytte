<x-app-layout title="Dashboard">

    @php
        $workspace = auth()->user()->activeWorkspace;
        $avgBand = match (true) {
            $avgScore === null  => null,
            $avgScore >= 70     => 'strong',
            $avgScore >= 45     => 'moderate',
            default             => 'weak',
        };
        $avgColor = match ($avgBand) {
            'strong'   => 'text-emerald-700',
            'moderate' => 'text-amber-700',
            'weak'     => 'text-red-700',
            default    => 'text-slate-400',
        };
        $distTotal = max(1, $distribution['strong'] + $distribution['moderate'] + $distribution['weak']);
        $hasScores = $distTotal > 1 || ($distribution['strong'] + $distribution['moderate'] + $distribution['weak']) > 0;
    @endphp

    {{-- Header --}}
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Dashboard</h1>
            <p class="mt-0.5 text-sm text-slate-500">
                {{ $workspace?->name ?? 'Your workspace' }} · Welcome back
            </p>
        </div>
        <a href="{{ route('projects.create') }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-vytte-700 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-vytte-800 transition-colors duration-150">
            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/>
            </svg>
            New Project
        </a>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5"
         x-data="{ loaded: false }" x-init="$nextTick(() => { loaded = true })">

        {{-- Projects card --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Active Projects</p>
            <div x-show="!loaded">
                <x-skeleton class="mt-2 h-8 w-16 rounded" />
                <x-skeleton class="mt-2 h-3 w-32 rounded" />
            </div>
            <div x-show="loaded" x-cloak>
                <p class="mt-1.5 text-3xl font-bold text-slate-900 tabular-nums">{{ $activeProjectCount }}</p>
                <p class="mt-0.5 text-xs text-slate-400">
                    {{ $activeProjectCount === 1 ? '1 active project' : $activeProjectCount . ' active projects' }}
                </p>
            </div>
        </div>

        {{-- Assessments card --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Assessments Run</p>
            <div x-show="!loaded">
                <x-skeleton class="mt-2 h-8 w-16 rounded" />
                <x-skeleton class="mt-2 h-3 w-32 rounded" />
            </div>
            <div x-show="loaded" x-cloak>
                <p class="mt-1.5 text-3xl font-bold text-slate-900 tabular-nums">{{ $totalAssessments }}</p>
                <p class="mt-0.5 text-xs text-slate-400">
                    {{ $totalAssessments === 0 ? 'No assessments completed' : 'Completed assessments' }}
                </p>
            </div>
        </div>

        {{-- Avg score card --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Avg. Score</p>
            <div x-show="!loaded">
                <x-skeleton class="mt-2 h-8 w-16 rounded" />
                <x-skeleton class="mt-2 h-3 w-32 rounded" />
            </div>
            <div x-show="loaded" x-cloak>
                @if ($avgScore !== null)
                    <p class="mt-1.5 text-3xl font-bold tabular-nums {{ $avgColor }}">{{ number_format($avgScore, 1) }}</p>
                    <p class="mt-0.5 text-xs {{ $avgColor }} font-medium capitalize">{{ $avgBand }}</p>
                @else
                    <p class="mt-1.5 text-3xl font-bold text-slate-300 tabular-nums">—</p>
                    <p class="mt-0.5 text-xs text-slate-400">No scores yet</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Score distribution --}}
    @if ($hasScores)
        <div class="bg-white rounded-xl border border-slate-200 p-5 mb-5"
             x-data="{ loaded: false }" x-init="$nextTick(() => { loaded = true })">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-3">Score Distribution</p>

            <div x-show="!loaded">
                <x-skeleton class="h-3 w-full rounded-full mb-3" />
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
                <div class="flex rounded-full overflow-hidden h-2.5 mb-3 gap-px bg-slate-100">
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
                        <span class="text-slate-600 font-medium">Strong</span>
                        <span class="font-bold text-slate-900 tabular-nums">{{ $distribution['strong'] }}</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"></span>
                        <span class="text-slate-600 font-medium">Moderate</span>
                        <span class="font-bold text-slate-900 tabular-nums">{{ $distribution['moderate'] }}</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0"></span>
                        <span class="text-slate-600 font-medium">Weak</span>
                        <span class="font-bold text-slate-900 tabular-nums">{{ $distribution['weak'] }}</span>
                    </span>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Recent projects --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden"
             x-data="{ loaded: false }" x-init="$nextTick(() => { loaded = true })">
            <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900">Recent Projects</h2>
                <a href="{{ route('projects.index') }}" class="text-xs text-vytte-700 font-semibold hover:text-vytte-900 transition-colors">
                    View all
                </a>
            </div>

            {{-- Skeleton --}}
            <div x-show="!loaded" class="divide-y divide-slate-100">
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

            {{-- Real content --}}
            <div x-show="loaded" x-cloak>
                @if ($recentProjects->isEmpty())
                    <div class="px-5 py-10 flex flex-col items-center text-center">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center mb-3">
                            <x-heroicon-o-folder class="w-5 h-5 text-slate-400" />
                        </div>
                        <p class="text-sm font-semibold text-slate-700">No projects yet</p>
                        <p class="mt-1 text-xs text-slate-400">Create a project to start diagnosing health systems.</p>
                        <a href="{{ route('projects.create') }}"
                           class="mt-4 inline-flex items-center gap-1 px-3 py-1.5 bg-vytte-700 text-white text-xs font-semibold rounded-lg hover:bg-vytte-800 transition-colors">
                            Create first project
                        </a>
                    </div>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($recentProjects as $project)
                            @php
                                $target   = $project->targets->first();
                                $typeName = $target?->targetType?->target_type_name;
                            @endphp
                            <a href="{{ route('projects.show', $project) }}"
                               class="flex items-center gap-3 px-5 py-3.5 hover:bg-slate-50 transition-colors">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 truncate">{{ $project->name }}</p>
                                    @if ($typeName)
                                        <p class="text-xs text-slate-400 mt-0.5">{{ $typeName }}</p>
                                    @endif
                                </div>
                                <svg class="w-4 h-4 text-slate-300 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
                                </svg>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Recent assessments --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden"
             x-data="{ loaded: false }" x-init="$nextTick(() => { loaded = true })">
            <div class="px-5 py-3.5 border-b border-slate-100">
                <h2 class="text-sm font-bold text-slate-900">Recent Assessments</h2>
            </div>

            {{-- Skeleton --}}
            <div x-show="!loaded" class="divide-y divide-slate-100">
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

            {{-- Real content --}}
            <div x-show="loaded" x-cloak>
                @if ($recentAssessments->isEmpty())
                    <div class="px-5 py-10 flex flex-col items-center text-center">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center mb-3">
                            <x-heroicon-o-clipboard-document-list class="w-5 h-5 text-slate-400" />
                        </div>
                        <p class="text-sm font-semibold text-slate-700">No completed assessments</p>
                        <p class="mt-1 text-xs text-slate-400">Submit an assessment to see results here.</p>
                    </div>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($recentAssessments as $assessment)
                            @php
                                $scope        = $assessment->moduleScope->first();
                                $overallScore = $assessment->score?->overall_score !== null
                                    ? (float) $assessment->score->overall_score
                                    : null;
                            @endphp
                            <a href="{{ route('assessments.results', $assessment) }}"
                               class="flex items-center gap-3 px-5 py-3.5 hover:bg-slate-50 transition-colors">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 truncate">
                                        {{ $scope?->module?->module_name ?? 'Assessment' }}
                                    </p>
                                    <p class="text-xs text-slate-400 mt-0.5">
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
