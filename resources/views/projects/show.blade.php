<x-app-layout :title="$project->name">

    {{-- Back + header --}}
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('projects.index') }}"
               class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors mb-2">
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 010 1.06L8.06 10l3.72 3.72a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd"/>
                </svg>
                Projects
            </a>
            @php
                $target   = $project->targets->first();
                $typeName = $target?->targetType?->target_type_name;
                $location = collect([$target?->country, $target?->region, $target?->sub_region])->filter()->implode(' · ');
            @endphp
            @if ($typeName)
                <p class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide mb-1">{{ $typeName }}</p>
            @endif
            <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">{{ $project->name }}</h1>
            @if ($target)
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $target->name }}{{ $location ? ' · ' . $location : '' }}</p>
            @endif
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            @if ($project->isArchived())
                <span class="px-2.5 py-1 text-xs font-semibold bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 rounded-lg">Archived</span>
            @endif
            <a href="{{ route('projects.edit', $project) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:border-slate-300 dark:hover:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all duration-150">
                Edit
            </a>
        </div>
    </div>

    @if (session('respondent_link'))
        <div x-data="{ copied: false }" class="mb-5 px-4 py-4 bg-vytte-50 dark:bg-vytte-900/20 border border-vytte-200 dark:border-vytte-800 rounded-xl">
            <p class="text-xs font-bold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide mb-2">Respondent link created</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">Share this link with respondents. They can open it on any device, no login required.</p>
            <div class="flex items-center gap-2">
                <code class="flex-1 text-xs bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-slate-700 dark:text-slate-300 truncate">{{ session('respondent_link') }}</code>
                <button
                    x-on:click="navigator.clipboard.writeText('{{ session('respondent_link') }}').then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                    class="flex-shrink-0 px-3 py-2 text-xs font-semibold text-vytte-700 dark:text-vytte-400 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                    <span x-show="!copied">Copy</span>
                    <span x-show="copied" x-cloak>Copied!</span>
                </button>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Target info card --}}
        @if ($target)
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <h2 class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-3">Target</h2>
                <div class="flex flex-col gap-2 text-sm">
                    <div>
                        <span class="text-slate-400 dark:text-slate-500 text-xs">Name</span>
                        <p class="font-semibold text-slate-900 dark:text-white">{{ $target->name }}</p>
                    </div>
                    <div>
                        <span class="text-slate-400 dark:text-slate-500 text-xs">Type</span>
                        <p class="font-medium text-slate-700 dark:text-slate-200">{{ $typeName ?? '—' }}</p>
                    </div>
                    @if ($location)
                        <div>
                            <span class="text-slate-400 dark:text-slate-500 text-xs">Location</span>
                            <p class="font-medium text-slate-700 dark:text-slate-200">{{ $location }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Assessments section --}}
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-3">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Assessments</h2>
                <div class="flex items-center gap-2">
                    @if ($project->assessments->where('status', 'COMPLETE')->isNotEmpty())
                        <a href="{{ route('projects.progress', $project) }}"
                           class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors duration-150">
                            <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                            </svg>
                            Progress
                        </a>
                        <a href="{{ route('actions.index', $project) }}"
                           class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors duration-150">
                            <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                            </svg>
                            Actions
                        </a>
                        <a href="{{ route('projects.export.csv', $project) }}"
                           class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors duration-150">
                            <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                            CSV
                        </a>
                    @endif
                    @if (! $project->isArchived())
                        <a href="{{ route('assessments.create', $project) }}"
                           class="inline-flex items-center gap-1 px-3 py-1.5 bg-vytte-700 text-white text-xs font-semibold rounded-lg hover:bg-vytte-800 transition-colors duration-150">
                            <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/>
                            </svg>
                            Start Assessment
                        </a>
                    @endif
                </div>
            </div>
            @if ($project->assessments->isEmpty())
                <div class="px-5 py-10 flex flex-col items-center text-center">
                    <div class="w-10 h-10 rounded-xl bg-vytte-50 dark:bg-vytte-900/30 flex items-center justify-center mb-3">
                        <x-heroicon-o-clipboard-document-list class="w-5 h-5 text-vytte-500 dark:text-vytte-400" />
                    </div>
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No assessments yet</p>
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500 max-w-xs">
                        Start an assessment to diagnose this {{ $target?->targetType?->target_type_name ?? 'target' }}.
                    </p>
                </div>
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($project->assessments as $assessment)
                        @php
                            $inScopeScopes  = $assessment->moduleScope->where('in_scope', true);
                            $inScopeCount   = $inScopeScopes->count();
                            $assessmentLabel = $assessment->reportSnapshot?->payload['title']
                                ?? $assessment->catalogueRelease?->release_name
                                ?? ($inScopeCount === 1
                                    ? ($inScopeScopes->first()?->module?->module_name ?? 'Focused Health Assessment')
                                    : 'Comprehensive Health Assessment');
                            $moduleCountLabel = $inScopeCount > 1 ? $inScopeCount . ' assessment areas' : null;
                        @endphp
                        <div class="flex items-center justify-between px-5 py-3.5">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="flex-shrink-0">
                                    @if ($assessment->status === 'COMPLETE')
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-green-100 dark:bg-green-900/40">
                                            <svg class="w-3.5 h-3.5 text-green-600 dark:text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                                            </svg>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-100 dark:bg-amber-900/40">
                                            <svg class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/>
                                            </svg>
                                        </span>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">
                                        {{ $assessmentLabel }}
                                        @if ($moduleCountLabel)
                                            <span class="ml-1 text-xs font-normal text-slate-400 dark:text-slate-500">· {{ $moduleCountLabel }}</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-slate-400 dark:text-slate-500">
                                        @if ($assessment->isComplete())
                                            Completed
                                        @elseif ($assessment->isClosed())
                                            Collection closed
                                        @elseif ($assessment->isPublished())
                                            Collecting responses
                                        @else
                                            Draft — not yet published
                                        @endif
                                        @if ($assessment->started_at)
                                            · {{ $assessment->started_at->format('d M Y') }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="flex-shrink-0 ml-4 flex items-center gap-3">
                                @if ($assessment->status === 'COMPLETE')
                                    @php
                                        $scoreRecord  = $assessment->score;
                                        $overallScore = $scoreRecord ? (float) $scoreRecord->overall_score : null;
                                        $band = match (true) {
                                            $overallScore === null => 'uncalibrated',
                                            $overallScore >= 70.0  => 'strong',
                                            $overallScore >= 45.0  => 'moderate',
                                            default                => 'weak',
                                        };
                                        $pillClass = match ($band) {
                                            'strong'       => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800',
                                            'moderate'     => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800',
                                            'weak'         => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800',
                                            default        => 'bg-slate-100 text-slate-400 border-slate-200 dark:bg-slate-700 dark:text-slate-500 dark:border-slate-600',
                                        };
                                    @endphp
                                    @if ($overallScore !== null)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold border {{ $pillClass }}">
                                            {{ number_format($overallScore, 1) }}
                                            <span class="font-normal capitalize">{{ $band }}</span>
                                        </span>
                                    @elseif ($scoreRecord && $scoreRecord->calibration_status === 'NOT_CALIBRATED')
                                        <span class="text-xs text-slate-400 dark:text-slate-500 italic">Not yet calibrated</span>
                                    @else
                                        <span class="text-xs text-slate-400 dark:text-slate-500">Scoring…</span>
                                    @endif
                                    <a href="{{ route('assessments.results', $assessment) }}"
                                       class="text-xs font-semibold text-vytte-700 hover:text-vytte-900 transition-colors">
                                        View →
                                    </a>
                                @elseif ($assessment->isDraft())
                                    {{-- Publishing is the deliberate act that opens the assessment for responses. --}}
                                    <form method="POST" action="{{ route('assessments.publish', $assessment) }}">
                                        @csrf
                                        <button type="submit"
                                                class="text-xs font-semibold text-vytte-700 hover:text-vytte-900 transition-colors">
                                            Publish
                                        </button>
                                    </form>
                                    <a href="{{ route('assessments.run', $assessment) }}"
                                       class="text-sm font-semibold text-vytte-700 hover:text-vytte-900 transition-colors">
                                        Continue →
                                    </a>
                                @else
                                    <a href="{{ route('assessments.monitor', $assessment) }}"
                                       class="text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">
                                        Monitor
                                    </a>
                                    <a href="{{ route('assessments.run', $assessment) }}"
                                       class="text-sm font-semibold text-vytte-700 hover:text-vytte-900 transition-colors">
                                        Continue →
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    {{-- Actions --}}
    <div class="mt-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
        <h2 class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-3">Actions</h2>
        <form method="POST" action="{{ route('projects.archive', $project) }}">
            @csrf
            @method('PATCH')
            <button type="submit"
                    class="text-sm font-medium {{ $project->isArchived() ? 'text-vytte-700 hover:text-vytte-900' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200' }} transition-colors">
                {{ $project->isArchived() ? 'Reactivate this project' : 'Archive this project' }}
            </button>
        </form>
    </div>

</x-app-layout>
