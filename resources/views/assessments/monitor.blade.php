<x-app-layout :title="'Monitoring · '.($assessment->target?->name ?? 'Assessment')">
    <div class="mb-5">
        <a href="{{ route('projects.show', $assessment->project) }}" class="link-nav text-sm">
            <span aria-hidden="true">&larr;</span> Back to {{ \Illuminate\Support\Str::limit($assessment->project?->name, 40) }}
        </a>
        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Live response monitoring</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ $assessment->target?->name ?? 'Assessment' }} ·
                    {{ $assessment->isClosed() ? 'Collection closed' : ($assessment->isComplete() ? 'Finalised' : 'Collecting responses') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($assessment->isCollecting())
                    <a href="{{ route('assessments.respondent-collection', $assessment) }}" class="btn-secondary">Manage links</a>
                    <form method="POST" action="{{ route('assessments.close', $assessment) }}"
                          onsubmit="return confirm('Close collection? No new responses will be accepted. You can reopen it later.')">
                        @csrf
                        <button class="btn-secondary">Close collection</button>
                    </form>
                @elseif ($assessment->isClosed() && ! $assessment->isComplete())
                    <form method="POST" action="{{ route('assessments.reopen', $assessment) }}">
                        @csrf
                        <button class="btn-secondary">Reopen collection</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card tone="blue" label="Responses started" :value="$stats['started']"
                     :sub="$stats['in_progress'].' still in progress'" />
        <x-stat-card tone="strong" label="Completed" :value="$stats['submitted']"
                     :sub="$stats['completion_rate'].'% of those started'" />
        <x-stat-card :tone="$stats['eligible'] >= $stats['minimum'] ? 'strong' : 'moderate'"
                     label="Eligible" :value="$stats['eligible']"
                     :sub="$stats['minimum'].' needed to finalise'" />
        <x-stat-card tone="slate" label="Excluded" :value="$stats['excluded']" sub="Marked not eligible" />
    </div>

    <section class="section-card" aria-labelledby="sessions-heading">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-700">
            <h2 id="sessions-heading" class="text-sm font-bold text-slate-900 dark:text-white">Responses</h2>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                Each person who opens a share link appears here. Mark any as excluded from the collection review before finalising.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">Started</th>
                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">Last activity</th>
                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">Progress</th>
                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">Eligibility</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse ($sessions as $session)
                        <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $session->started_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-5 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $session->last_activity_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @if ($session->submitted_at)
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">Completed</span>
                                @else
                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">In progress</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs">
                                @php
                                    [$label, $classes] = match ($session->eligibility_status) {
                                        'ELIGIBLE' => ['Eligible', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'],
                                        'EXCLUDED' => ['Excluded', 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200'],
                                        'TEST' => ['Test', 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400'],
                                        default => ['Not reviewed', 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400'],
                                    };
                                @endphp
                                <span class="inline-flex rounded-full px-2.5 py-1 font-semibold {{ $classes }}">{{ $label }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center">
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No responses yet</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    Share a link from the collection page and responses will appear here as they arrive.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-app-layout>
