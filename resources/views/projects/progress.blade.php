<x-app-layout :title="'Progress · ' . $project->name">

    {{-- Back + header --}}
    <div class="mb-6">
        <a href="{{ route('projects.show', $project) }}"
           class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors mb-2">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 010 1.06L8.06 10l3.72 3.72a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd"/>
            </svg>
            {{ $project->name }}
        </a>
        <p class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide">Progress</p>
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight mt-0.5">{{ $project->name }}</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-2xl">
            Whether this {{ $project->targets->first()?->targetType?->target_type_name ? strtolower($project->targets->first()->targetType->target_type_name) : 'target' }} is getting better over time. Each time you re-run the same assessment, its
            score, the issues that were fixed or slipped, and the actions your team followed through on are tracked here.
        </p>
    </div>

    @unless ($assessments->isEmpty())
        {{-- A short "how to read this" strip, so the page explains itself. Dismissible once learned. --}}
        <x-help-callout id="progress" title="How to use this page">
            <ol class="text-xs text-slate-600 dark:text-slate-300 space-y-1 list-decimal list-inside">
                <li><span class="font-semibold">Set a target score</span> below so you have a goal to move toward.</li>
                <li><span class="font-semibold">Re-run the same assessment</span> periodically — comparisons appear from the second run.</li>
                <li><span class="font-semibold">Work the action plan</span> and mark items done — follow-through is tracked here.</li>
                <li>Optionally <span class="font-semibold">schedule the report by email</span> so stakeholders stay updated automatically.</li>
            </ol>
        </x-help-callout>
    @endunless

    @if ($assessments->isEmpty())
        <x-empty-state
            icon="chart-bar-square"
            title="No completed assessments yet"
            message="Run and submit an assessment on this project. Progress, trends, and targets start tracking from the first one, and comparisons appear from the second."
            :action="route('assessments.create', $project)"
            action-label="Start an assessment" />
    @else

        {{-- Trend at a glance + action follow-through --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
            {{-- Trend --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Trend</h2>
                @if ($trend['comparable'])
                    @php
                        $delta = $trend['overall_delta'];
                        $dirColor = $trend['direction'] === 'UP' ? 'text-green-600 dark:text-green-400' : ($trend['direction'] === 'DOWN' ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400');
                        $arrow = $trend['direction'] === 'UP' ? '▲' : ($trend['direction'] === 'DOWN' ? '▼' : '—');
                    @endphp
                    <div class="mt-3 flex items-end gap-3">
                        <span class="text-3xl font-black text-slate-900 dark:text-white tabular-nums">{{ $trend['latest_score'] !== null ? number_format($trend['latest_score'], 1) : '—' }}</span>
                        <span class="text-sm font-bold {{ $dirColor }} mb-1">
                            {{ $arrow }} {{ $delta !== null ? ($delta > 0 ? '+' : '').number_format($delta, 1) : '—' }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Latest score vs the previous run.
                        @if ($trend['since_first_delta'] !== null)
                            {{ $trend['since_first_delta'] >= 0 ? 'Up' : 'Down' }} {{ number_format(abs($trend['since_first_delta']), 1) }} since the first run.
                        @endif
                    </p>
                    @php
                        $trendPoints = $series->filter(fn ($a) => $a->score?->overall_score !== null)
                            ->map(fn ($a) => ['label' => $a->completed_at?->format('d M') ?? '', 'value' => (float) $a->score->overall_score])
                            ->values()->all();
                    @endphp
                    @if (count($trendPoints) >= 2)
                        <div class="mt-3">
                            <x-viz.trend-line :points="$trendPoints" />
                        </div>
                    @endif
                    @if (! empty($trend['domain_movements']))
                        <div class="mt-3 flex flex-col gap-1.5">
                            @foreach ($trend['domain_movements'] as $dm)
                                @php
                                    $dc = $dm['direction'] === 'UP' ? 'text-green-600 dark:text-green-400' : ($dm['direction'] === 'DOWN' ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500');
                                @endphp
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-slate-600 dark:text-slate-300">{{ $dm['domain_name'] }}</span>
                                    <span class="font-semibold tabular-nums {{ $dc }}">
                                        {{ $dm['delta'] !== null ? ($dm['delta'] > 0 ? '+' : '').number_format($dm['delta'], 1) : '—' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="mt-2 flex items-start gap-2">
                        <span class="mt-0.5 inline-flex flex-shrink-0 items-center rounded-full bg-vytte-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-vytte-700 dark:bg-vytte-900/30 dark:text-vytte-400">Baseline recorded</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        This is the first result on record for the current assessment method.
                        Run the same assessment again to see whether it's improving — Vytte will compare it automatically,
                        only when the questions and scoring are still the same.
                    </p>
                @endif
            </div>

            {{-- Action follow-through --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Action follow-through</h2>
                    <a href="{{ route('actions.index', $project) }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:text-vytte-900 dark:hover:text-vytte-200">Open plan</a>
                </div>
                @if ($followThrough['total'] > 0)
                    <div class="mt-3 flex items-end gap-3">
                        <span class="text-3xl font-black text-slate-900 dark:text-white tabular-nums">{{ $followThrough['completion_rate'] !== null ? $followThrough['completion_rate'].'%' : '—' }}</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400 mb-1">{{ $followThrough['completed'] }} of {{ $followThrough['total'] }} done</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Did the agreed actions get done?</p>
                    <div class="mt-3 grid grid-cols-4 gap-2 text-center">
                        <div><p class="text-lg font-bold text-slate-700 dark:text-slate-200 tabular-nums">{{ $followThrough['open'] }}</p><p class="text-[10px] uppercase tracking-wide text-slate-400 dark:text-slate-500">Open</p></div>
                        <div><p class="text-lg font-bold text-blue-600 dark:text-blue-400 tabular-nums">{{ $followThrough['in_progress'] }}</p><p class="text-[10px] uppercase tracking-wide text-slate-400 dark:text-slate-500">Doing</p></div>
                        <div><p class="text-lg font-bold text-green-600 dark:text-green-400 tabular-nums">{{ $followThrough['done'] + $followThrough['verified'] }}</p><p class="text-[10px] uppercase tracking-wide text-slate-400 dark:text-slate-500">Done</p></div>
                        <div><p class="text-lg font-bold {{ $followThrough['overdue'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-700 dark:text-slate-200' }} tabular-nums">{{ $followThrough['overdue'] }}</p><p class="text-[10px] uppercase tracking-wide text-slate-400 dark:text-slate-500">Overdue</p></div>
                    </div>
                @else
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        No actions yet. Add recommendations from an assessment's results to start tracking follow-through.
                    </p>
                @endif
            </div>
        </div>

        {{-- Progress: exact assessed issues, tracked by stable identity — not domain averages. --}}
        @if ($issues['comparable'])
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 mb-5">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Since the last comparable assessment</h2>
                @if (! empty($issues['not_comparable']))
                    <p class="text-xs text-amber-600 dark:text-amber-400 mb-3">
                        An earlier assessment exists but used a different methodology, so Vytte cannot say whether these issues are new or ongoing — only what is currently open.
                    </p>
                @else
                    <p class="text-xs text-slate-400 dark:text-slate-500 mb-3">What changed, issue by issue.</p>
                @endif
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    @php
                        $buckets = [
                            'resolved' => ['Resolved', 'strong'],
                            'improving' => ['Improving', 'blue'],
                            'persistent' => ['Still open', 'moderate'],
                            'new' => ['New issues', 'weak'],
                        ];
                    @endphp
                    @foreach ($buckets as $key => [$label, $tone])
                        <x-stat-card :tone="count($issues[$key]) > 0 ? $tone : 'slate'" :label="$label" :value="count($issues[$key])"
                                     :sub="count($issues[$key]) > 0 ? collect($issues[$key])->pluck('domain_name')->unique()->take(3)->join(', ') : null" />
                    @endforeach
                </div>
                @if (! empty($issues['not_comparable']))
                    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                        {{ count($issues['not_comparable']) }} currently open {{ Str::plural('issue', count($issues['not_comparable'])) }} not shown above (an earlier run used different questions, so progress can't be tracked for {{ count($issues['not_comparable']) === 1 ? 'it' : 'them' }}).
                    </p>
                @endif
                @if (! empty($trendInsights))
                    <ul class="mt-4 flex flex-col gap-1.5 border-t border-slate-100 dark:border-slate-700 pt-3">
                        @foreach ($trendInsights as $ti)
                            <li class="text-xs text-slate-600 dark:text-slate-300 flex items-start gap-2">
                                <span class="text-[10px] font-bold uppercase tracking-wide {{ $ti['polarity'] === 'NEGATIVE' ? 'text-red-500 dark:text-red-400' : 'text-amber-500 dark:text-amber-400' }} flex-shrink-0 mt-0.5">{{ $ti['category_name'] }}</span>
                                <span>{{ $ti['statement'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        {{-- Targets: current performance against the goals set for this project. Collapsed by
             default when there is nothing to show yet, so it does not compete with the progress
             story above for attention — expanded automatically once a target exists. --}}
        <details class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 mb-5" @if (! empty($targetProgress)) open @endif>
            <summary class="text-sm font-bold text-slate-900 dark:text-white mb-3 cursor-pointer select-none">Targets</summary>
            @if (! empty($targetProgress))
                <div class="flex flex-col gap-2 mb-4">
                    @foreach ($targetProgress as $tp)
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2">
                            <span class="text-sm text-slate-700 dark:text-slate-200">{{ $tp['scope'] }}</span>
                            <div class="flex items-center gap-3 text-xs">
                                <span class="text-slate-500 dark:text-slate-400">Target {{ number_format($tp['target'], 0) }}</span>
                                <span class="font-bold tabular-nums {{ $tp['met'] ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">
                                    {{ $tp['current'] !== null ? number_format($tp['current'], 1) : '—' }}
                                    @if ($tp['gap'] !== null)
                                        ({{ $tp['gap'] >= 0 ? '+' : '' }}{{ number_format($tp['gap'], 1) }})
                                    @endif
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">No targets set yet. Set a goal score to track progress against it.</p>
            @endif

            <form method="POST" action="{{ route('projects.targets.set', $project) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Area</span>
                    <select name="domain_code" class="rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <option value="">Overall</option>
                        @foreach ($allDomains as $d)
                            <option value="{{ $d->domain_code }}">{{ $d->domain_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Target score</span>
                    <input type="number" name="target_score" min="0" max="100" step="1" required
                           class="w-28 rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                </label>
                <button type="submit" class="rounded-xl bg-vytte-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-vytte-700 transition-colors">Set target</button>
            </form>
            @if ($targets->isNotEmpty())
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($targets as $t)
                        <form method="POST" action="{{ route('projects.targets.delete', [$project, $t]) }}" class="inline">
                            @csrf @method('DELETE')
                            <button class="text-[11px] text-slate-400 hover:text-red-600 dark:hover:text-red-400">
                                Remove {{ $t->domain_code ?? 'Overall' }} target ✕
                            </button>
                        </form>
                    @endforeach
                </div>
            @endif
        </details>

        {{-- Scheduled reports: recurring email of the latest report to a recipient. --}}
        <details class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 mb-5" @if ($schedules->isNotEmpty()) open @endif>
            <summary class="text-sm font-bold text-slate-900 dark:text-white mb-1 cursor-pointer select-none">Scheduled reports</summary>
            <p class="text-xs text-slate-400 dark:text-slate-500 mb-3">Email the latest report for this project to someone on a regular schedule.</p>
            @if ($schedules->isNotEmpty())
                <div class="flex flex-col gap-2 mb-4">
                    @foreach ($schedules as $s)
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2">
                            <div class="text-sm text-slate-700 dark:text-slate-200">
                                {{ $s->recipient_email }}
                                <span class="text-xs text-slate-400 dark:text-slate-500">· {{ ucfirst(strtolower($s->frequency)) }} · next {{ $s->next_run_at->format('d M Y') }}</span>
                            </div>
                            <form method="POST" action="{{ route('report-schedules.destroy', [$project, $s]) }}">
                                @csrf @method('DELETE')
                                <button class="text-[11px] text-slate-400 hover:text-red-600 dark:hover:text-red-400">Remove</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
            <form method="POST" action="{{ route('report-schedules.store', $project) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Recipient email</span>
                    <input type="email" name="recipient_email" required placeholder="name@example.com"
                           class="rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Frequency</span>
                    <select name="frequency" class="rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        @foreach (\App\Models\ReportSchedule::FREQUENCIES as $freq)
                            <option value="{{ $freq }}">{{ ucfirst(strtolower($freq)) }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="rounded-xl bg-vytte-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-vytte-700 transition-colors">Schedule</button>
            </form>
        </details>

        {{-- Assessment runs table --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Assessment Runs</h2>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">{{ $assessments->count() }} completed {{ Str::plural('assessment', $assessments->count()) }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-700">
                            <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">#</th>
                            <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Date</th>
                            <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Type</th>
                            <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Assessment</th>
                            <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Performance Stage</th>
                            <th class="px-5 py-2.5 text-right text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Score</th>
                            <th class="px-5 py-2.5 text-right text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Band</th>
                            <th class="px-5 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @php $latestAssessmentId = $assessments->last()?->assessment_id; @endphp
                        @foreach ($assessments as $i => $a)
                            @php
                                $aScore = $a->score?->overall_score !== null ? (float) $a->score->overall_score : null;
                                $aMaturity = $a->score?->maturityLevel;
                                // The subject taken, so a target's repeated assessments are told apart.
                                $aSubject = $a->catalogueRelease?->release_name ?? $a->moduleScope->first()?->module?->module_name ?? '—';
                            @endphp
                            <tr>
                                <td class="px-5 py-3 text-xs text-slate-400 dark:text-slate-500 tabular-nums">{{ $i + 1 }}</td>
                                <td class="px-5 py-3 text-slate-700 dark:text-slate-200 whitespace-nowrap">
                                    {{ $a->completed_at?->format('d M Y') ?? '—' }}
                                </td>
                                <td class="px-5 py-3">
                                    <form method="POST" action="{{ route('assessments.type', $a) }}">
                                        @csrf @method('PATCH')
                                        <select name="assessment_type" onchange="this.form.submit()"
                                                class="text-xs rounded-lg border border-slate-200 bg-white text-slate-700 px-2 py-1 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">
                                            <option value="" @selected(! $a->assessment_type)>—</option>
                                            @foreach (\App\Models\Assessment::TYPES as $type)
                                                <option value="{{ $type }}" @selected($a->assessment_type === $type)>{{ ucfirst(strtolower($type)) }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td class="px-5 py-3 text-slate-700 dark:text-slate-200">
                                    {{ $aSubject }}
                                </td>
                                <td class="px-5 py-3">
                                    @if ($aMaturity)
                                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                            {{ $aMaturity->level_name }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400 dark:text-slate-500">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right font-bold tabular-nums text-slate-900 dark:text-white">
                                    {{ $aScore !== null ? number_format($aScore, 1) : '—' }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <x-score-pill :score="$aScore" />
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('assessments.results', $a) }}"
                                       class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:text-vytte-900 dark:hover:text-vytte-200 transition-colors">
                                        View →
                                    </a>
                                    @if ($assessments->count() >= 2 && $a->assessment_id !== $latestAssessmentId)
                                        <a href="{{ route('projects.compare', [$project, 'a' => $a->assessment_id, 'b' => $latestAssessmentId]) }}"
                                           class="ml-3 text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">
                                            Compare to current →
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Domain scores matrix (when ≥ 2 compatible-series assessments with domain scores exist) --}}
        @if ($series->count() >= 2 && $domainScoresByAssessment->isNotEmpty())
            <div class="mt-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Domain Score History</h2>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Score per domain across the current compatible series</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-700">
                                <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide sticky left-0 bg-white dark:bg-slate-800">Domain</th>
                                @foreach ($series as $i => $a)
                                    <th class="px-4 py-2.5 text-center text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide whitespace-nowrap">
                                        Run {{ $i + 1 }}<br>
                                        <span class="font-normal normal-case text-slate-400 dark:text-slate-500">{{ $a->completed_at?->format('d M Y') ?? '—' }}</span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach ($allDomains as $domain)
                                <tr>
                                    <td class="px-5 py-3 sticky left-0 bg-white dark:bg-slate-800">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded flex-shrink-0">{{ $domain->domain_code }}</span>
                                            <span class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $domain->domain_name }}</span>
                                        </div>
                                    </td>
                                    @foreach ($series as $a)
                                        @php
                                            $assessmentDomains = $domainScoresByAssessment->get($a->assessment_id, collect());
                                            $domainRow = $assessmentDomains->firstWhere('domain_id', $domain->domain_id);
                                            $ds = $domainRow && $domainRow->score !== null ? (float) $domainRow->score : null;
                                        @endphp
                                        <td class="px-4 py-3 text-center">
                                            @if ($ds !== null)
                                                @php
                                                    $dsColor = $ds >= 70 ? '#15803D' : ($ds >= 45 ? '#B45309' : '#B91C1C');
                                                    $dsBg = $ds >= 70 ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-800 dark:text-emerald-300' : ($ds >= 45 ? 'bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-300' : 'bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-300');
                                                @endphp
                                                <span class="inline-flex items-center justify-center min-w-[2.5rem] px-2 py-0.5 rounded text-xs font-bold tabular-nums {{ $dsBg }}">
                                                    {{ number_format($ds, 0) }}
                                                </span>
                                            @else
                                                <span class="text-xs text-slate-300 dark:text-slate-600">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    @endif

</x-app-layout>
