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

    {{-- Compare — folded into the Reports hub: how your assessment targets stack up. --}}
    @if ($canCompare)
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800" x-data="{ open: false }">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Compare your assessment targets</h2>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Latest score for each, ranked. Workspace average: <span class="font-bold text-slate-600 dark:text-slate-300">{{ $workspaceAverage !== null ? number_format($workspaceAverage, 1) : '—' }}</span></p>
                </div>
                <button type="button" @click="open = !open" class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:text-vytte-900 dark:hover:text-vytte-200">
                    <span x-show="!open">Show</span><span x-show="open" x-cloak>Hide</span>
                </button>
            </div>
            <div x-show="open" x-cloak class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="border-b border-slate-100 dark:border-slate-700">
                            <th class="py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">#</th>
                            <th class="py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">Target</th>
                            <th class="py-2 text-right text-[10px] font-bold uppercase tracking-wide text-slate-400">Score</th>
                            <th class="py-2 text-right text-[10px] font-bold uppercase tracking-wide text-slate-400">vs Avg</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach ($facilities as $f)
                                <tr>
                                    <td class="py-2 text-xs text-slate-400 tabular-nums">{{ $f['rank'] }}</td>
                                    <td class="py-2"><a href="{{ route('projects.show', $f['project_id']) }}" class="font-medium text-slate-800 dark:text-slate-200 hover:text-vytte-700">{{ $f['project_name'] }}</a></td>
                                    <td class="py-2 text-right font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($f['score'], 1) }}</td>
                                    <td class="py-2 text-right font-semibold tabular-nums {{ $f['vs_average'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ $f['vs_average'] >= 0 ? '+' : '' }}{{ number_format($f['vs_average'], 1) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if (! empty($domainComparison))
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-2">Across all targets, by area</p>
                        <div class="flex flex-col gap-1.5">
                            @foreach ($domainComparison as $dc)
                                @php $avg = $dc['average']; $col = $avg >= 70 ? '#15803D' : ($avg >= 45 ? '#B45309' : '#B91C1C'); @endphp
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-slate-600 dark:text-slate-300 w-28 flex-shrink-0 truncate">{{ $dc['domain_name'] }}</span>
                                    <div class="flex-1 h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                        <div class="h-full rounded-full" style="width: {{ min(100, $avg) }}%; background: {{ $col }}"></div>
                                    </div>
                                    <span class="text-xs font-bold tabular-nums w-10 text-right" style="color: {{ $col }}">{{ number_format($avg, 1) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
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
                        <a href="{{ route('assessments.export.pdf', $assessment) }}"
                           class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">
                            PDF
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
            <x-empty-state
                icon="chart-bar"
                title="No reports yet"
                message="Run an assessment on one of your assessment targets. Its report — scores, findings, risks, and what to do — appears here."
                :action="route('projects.index')"
                action-label="Start an assessment" />
        @endforelse
    </div>

    @if ($assessments->hasPages())
        <div class="mt-6">{{ $assessments->links() }}</div>
    @endif
</x-app-layout>
