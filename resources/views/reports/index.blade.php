<x-app-layout title="Reports">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Reports</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Final reports from completed assessments in this workspace.</p>
    </div>

    @if (session('error'))
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    {{-- Your latest report — the front-door preview, moved here from the dashboard. --}}
    @if ($latestReport && $search === '')
        @php $lr = $latestReport; $lrScore = $lr['score'] !== null ? (float) $lr['score'] : null; @endphp
        <div class="mb-5" x-data="{ show: localStorage.getItem('vytte.hideLatestReport') !== '1' }">
            <button type="button" x-show="!show" x-cloak
                    @click="show = true; localStorage.removeItem('vytte.hideLatestReport')"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:text-vytte-900 dark:hover:text-vytte-200">
                Show your latest report
            </button>
            <div x-show="show" x-cloak class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Your latest report</h2>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('assessments.results', $lr['assessment']) }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:text-vytte-900 dark:hover:text-vytte-200">Open full report →</a>
                        <button type="button" @click="show = false; localStorage.setItem('vytte.hideLatestReport', '1')"
                                class="text-xs font-semibold text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300">Hide</button>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-shrink-0 flex sm:flex-col items-center gap-2 sm:w-28">
                        <span class="text-3xl font-black tabular-nums" style="color: {{ $lrScore === null ? '#94A3B8' : ($lrScore >= 70 ? '#15803D' : ($lrScore >= 45 ? '#B45309' : '#B91C1C')) }}">{{ $lrScore !== null ? number_format($lrScore, 1) : '—' }}</span>
                        <span class="text-[11px] text-slate-500 dark:text-slate-400 text-center">{{ $lr['title'] }}</span>
                    </div>
                    <div class="flex-1 min-w-0 flex flex-col gap-2 border-l border-slate-100 dark:border-slate-700 sm:pl-4">
                        @if ($lr['top_finding'])
                            <div><span class="text-[10px] font-bold uppercase tracking-wide text-red-600 dark:text-red-400">Biggest issue</span>
                                <p class="text-sm text-slate-700 dark:text-slate-300">{{ $lr['top_finding']['statement'] }}</p></div>
                        @endif
                        @if ($lr['top_risk'])
                            <div><span class="text-[10px] font-bold uppercase tracking-wide text-amber-600 dark:text-amber-400">Top risk</span>
                                <p class="text-sm text-slate-700 dark:text-slate-300">{{ $lr['top_risk']['statement'] }}</p></div>
                        @endif
                        @if ($lr['top_action'])
                            <div><span class="text-[10px] font-bold uppercase tracking-wide text-vytte-600 dark:text-vytte-400">Do next</span>
                                <p class="text-sm text-slate-700 dark:text-slate-300">{{ $lr['top_action']['statement'] }}</p></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Search (loader suppressed). --}}
    @if (! $assessments->isEmpty() || $search !== '')
        <form method="GET" action="{{ route('reports.index') }}" class="mb-5 flex gap-2" data-no-loading>
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 dark:text-slate-500 pointer-events-none" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/>
                </svg>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search reports by target or project…"
                       class="w-full pl-9 pr-3 py-2 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-vytte-700/20 focus:border-vytte-700 transition-shadow">
            </div>
            @if ($search !== '')
                <a href="{{ route('reports.index') }}" class="px-3 py-2 text-sm font-medium text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 transition-colors">Clear</a>
            @endif
        </form>
    @endif

    <div class="space-y-4">
        @forelse ($assessments as $assessment)
            @php
                $payload = $assessment->reportSnapshot?->payload ?? [];
                $includedAreas = $assessment->moduleScope->where('in_scope', true);
                $title = $payload['title']
                    ?? $assessment->catalogueRelease?->release_name
                    ?? ($includedAreas->count() === 1
                        ? ($includedAreas->first()?->module?->module_name ?? 'Focused Health Assessment')
                        : 'Comprehensive Health Assessment');
                $activeLinks = $assessment->shareLinks->filter(fn ($link) => $link->isUsable());
            @endphp
            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-vytte-700 dark:text-vytte-400">{{ $assessment->target?->name }}</p>
                        <h2 class="mt-1 text-base font-bold text-slate-900 dark:text-white">{{ $title }}</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ $assessment->project?->name }}
                            @if ($assessment->completed_at)
                                · Completed {{ $assessment->completed_at->format('d M Y') }}
                            @endif
                            @if ($assessment->score?->overall_score !== null)
                                · Score {{ number_format((float) $assessment->score->overall_score, 1) }}
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('assessments.results', $assessment) }}"
                           class="inline-flex items-center rounded-lg bg-vytte-700 px-3 py-2 text-xs font-semibold text-white hover:bg-vytte-800">
                            View report
                        </a>
                        <a href="{{ route('projects.progress', $assessment->project_id) }}"
                           class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">
                            Progress
                        </a>
                        <a href="{{ route('actions.index', $assessment->project_id) }}"
                           class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">
                            Actions
                        </a>
                    </div>
                </div>

                {{-- Intelligence at a glance, from the frozen report. --}}
                @php $preview = $previews[$assessment->assessment_id] ?? null; @endphp
                @if ($preview && ($preview['top_finding'] || $preview['top_risk']))
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 border-t border-slate-100 dark:border-slate-700 pt-4">
                        @if ($preview['top_finding'])
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wide text-red-600 dark:text-red-400">Biggest issue</span>
                                <p class="text-xs text-slate-600 dark:text-slate-300 mt-0.5">{{ $preview['top_finding']['statement'] }}</p>
                            </div>
                        @endif
                        @if ($preview['top_risk'])
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wide text-amber-600 dark:text-amber-400">Top risk</span>
                                <p class="text-xs text-slate-600 dark:text-slate-300 mt-0.5">{{ $preview['top_risk']['statement'] }}</p>
                            </div>
                        @endif
                    </div>
                @endif

                @if ($activeLinks->isNotEmpty())
                    <details class="mt-4 border-t border-slate-100 pt-3 dark:border-slate-700">
                        <summary class="cursor-pointer text-xs font-semibold text-slate-600 dark:text-slate-300">
                            Manage active share links ({{ $activeLinks->count() }})
                        </summary>
                        <div class="mt-3 space-y-2">
                            @foreach ($activeLinks as $shareLink)
                                <div class="flex flex-col gap-2 rounded-lg bg-slate-50 p-3 dark:bg-slate-700/50 sm:flex-row sm:items-center">
                                    <input type="text"
                                           readonly
                                           value="{{ route('reports.shared.token', $shareLink->token) }}"
                                           class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    <span class="text-[11px] text-slate-400">
                                        Expires {{ $shareLink->expires_at?->format('d M Y') ?? 'never' }}
                                    </span>
                                    <form method="POST" action="{{ route('assessments.share.revoke', [$assessment, $shareLink]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-700 dark:text-red-400">
                                            Revoke
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif
            </section>
        @empty
            @if ($search !== '')
                <x-empty-state icon="chart-bar" title='No reports match "{{ $search }}"' message="Try a different target or project name." />
            @else
                <x-empty-state
                    icon="chart-bar"
                    title="No reports yet"
                    message="Run an assessment on one of your assessment targets. Its report — scores, findings, risks, and what to do — appears here."
                    :action="route('projects.index')"
                    action-label="Start an assessment" />
            @endif
        @endforelse
    </div>

    @if ($assessments->hasPages())
        <div class="mt-6">{{ $assessments->links() }}</div>
    @endif
</x-app-layout>
