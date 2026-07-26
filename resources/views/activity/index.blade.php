<x-app-layout title="Activity">

    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Activity</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Everything that has happened in this workspace, newest first — who did what, and when.</p>
    </div>

    @if ($activities->isEmpty())
        <x-empty-state
            icon="clock"
            title="Nothing has happened yet"
            message="As you create projects, run assessments, and work the action plan, every step is recorded here." />
    @else
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach ($activities as $log)
                    @php
                        // Prefer a specific, human sentence for the events a workspace member
                        // recognises; fall back to the shared label for everything else.
                        $who = $log->user?->name ?? 'Someone';
                        $new = is_array($log->new_values) ? $log->new_values : [];
                        $statusTo = isset($new['status_to']) ? strtolower(str_replace('_', ' ', $new['status_to'])) : null;
                        $sentence = match ($log->event) {
                            'assessment.created' => $who.' started an assessment',
                            'assessment.completed' => $who.' completed an assessment',
                            'assessment.report.finalized' => $who.' finalised a report',
                            'assessment.action.created' => $who.' added an action to a plan',
                            'assessment.action.updated' => $statusTo ? $who.' moved an action to '.$statusTo : $who.' updated an action',
                            'assessment.report_link.created' => $who.' created a shared report link',
                            'assessment.report_link.viewed' => 'A shared report link was opened',
                            default => $who.' — '.\App\Support\AuditEventLabel::for($log->event),
                        };
                        $tone = match (true) {
                            str_contains($log->event, 'completed') || str_contains($log->event, 'finalized') => 'text-green-600 dark:text-green-400',
                            str_contains($log->event, 'created') => 'text-vytte-600 dark:text-vytte-400',
                            default => 'text-slate-400 dark:text-slate-500',
                        };
                    @endphp
                    <li class="flex items-start gap-3 px-5 py-3.5">
                        <span class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-current {{ $tone }}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-slate-800 dark:text-slate-200">{{ $sentence }}</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">{{ \App\Support\AuditEventLabel::categoryFor($log->event) }}</p>
                        </div>
                        <div class="flex-shrink-0 text-right">
                            <p class="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $log->created_at?->diffForHumans() }}</p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 whitespace-nowrap">{{ $log->created_at?->format('j M Y, H:i') }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="mt-5">{{ $activities->links() }}</div>
    @endif

</x-app-layout>
