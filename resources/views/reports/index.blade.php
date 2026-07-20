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
                            Download PDF
                        </a>
                        <form method="POST" action="{{ route('assessments.share', $assessment) }}">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">
                                Create share link
                            </button>
                        </form>
                    </div>
                </div>

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
            <div class="rounded-2xl border border-slate-200 bg-white px-5 py-12 text-center dark:border-slate-700 dark:bg-slate-800">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No final reports yet</p>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Complete an assessment and its final report will appear here.</p>
            </div>
        @endforelse
    </div>

    @if ($assessments->hasPages())
        <div class="mt-6">{{ $assessments->links() }}</div>
    @endif
</x-app-layout>
