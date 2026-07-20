<x-admin-layout :title="$workspace->name">
    <div class="mb-5">
        <a href="{{ route('admin.workspaces.index') }}" class="link-nav text-sm">&larr; All workspaces</a>
        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ $workspace->name }}</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ ucfirst(strtolower($workspace->plan ?? 'free')) }} plan ·
                    Customer since {{ $workspace->created_at?->format('j M Y') }}
                </p>
            </div>
            <x-workspace-status-badge :status="$workspace->status" class="px-3 py-1.5 text-sm" />
        </div>
    </div>

    {{-- Health first: an administrator opens this page to answer "are they OK?" --}}
    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :tone="$health['summary']['tone']" label="Health" :value="$health['summary']['label']" :sub="$health['summary']['detail']" />
        <x-stat-card tone="blue" label="People" :value="$health['stats']['members']" sub="Members of this workspace" />
        <x-stat-card tone="slate" label="Projects" :value="$health['stats']['projects']" sub="Set up in this workspace" />
        <x-stat-card tone="strong" label="Assessments" :value="$health['stats']['assessments']"
                     :sub="$health['stats']['completed'].' completed, '.$health['stats']['in_progress'].' in progress'" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <section class="section-card p-5" aria-labelledby="signals-heading">
                <h2 id="signals-heading" class="text-sm font-bold text-slate-900 dark:text-white">What we noticed</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    Plain observations about how this customer is doing. Nothing here happens automatically — you decide what to act on.
                </p>
                <ul class="mt-3 space-y-2">
                    @forelse ($health['signals'] as $signal)
                        <li class="flex items-start gap-3 rounded-xl border p-3 text-sm
                            {{ $signal['tone'] === 'warning'
                                ? 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950'
                                : 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950' }}">
                            <span aria-hidden="true" class="mt-0.5">{{ $signal['tone'] === 'warning' ? '!' : '✓' }}</span>
                            <span>
                                <span class="block font-semibold text-slate-900 dark:text-white">{{ $signal['label'] }}</span>
                                <span class="block text-slate-600 dark:text-slate-300">{{ $signal['detail'] }}</span>
                            </span>
                        </li>
                    @empty
                        <li class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                            Nothing stands out about this workspace.
                        </li>
                    @endforelse
                </ul>
            </section>

            <section class="section-card" aria-labelledby="activity-heading">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-700">
                    <h2 id="activity-heading" class="text-sm font-bold text-slate-900 dark:text-white">Assessment activity</h2>
                    <span class="text-xs text-slate-400">
                        {{ $health['stats']['last_activity'] ? 'Last started '.$health['stats']['last_activity'] : 'None yet' }}
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">What was assessed</th>
                                <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">Project</th>
                                <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">Progress</th>
                                <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">Started</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @forelse ($recentAssessments as $assessment)
                                <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                                    <td class="px-5 py-3 font-medium text-slate-900 dark:text-white">{{ $assessment->target?->name ?? 'Unnamed' }}</td>
                                    <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $assessment->project?->name ?? '—' }}</td>
                                    <td class="px-5 py-3">
                                        @if ($assessment->completed_at)
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">Completed</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">In progress</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $assessment->created_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-10 text-center">
                                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No assessments yet</p>
                                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">This customer has not started measuring anything.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="space-y-4">
            <section class="section-card p-5" aria-labelledby="status-heading">
                <h2 id="status-heading" class="text-sm font-bold text-slate-900 dark:text-white">Access</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    This controls whether the customer's team can use Vytte. Nothing is ever deleted.
                </p>

                @if ($workspace->status !== 'ACTIVE')
                    <form method="POST" action="{{ route('admin.workspaces.status', $workspace) }}" class="mt-3">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="ACTIVE">
                        <button class="btn-primary w-full" data-loading-label="Reactivating…">Reactivate workspace</button>
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Members get access back immediately.</p>
                    </form>
                @endif

                @if ($workspace->status === 'ACTIVE')
                    <form method="POST" action="{{ route('admin.workspaces.status', $workspace) }}" class="mt-3"
                          onsubmit="return confirm('Put this workspace on hold? Their team will not be able to use Vytte until you reactivate it. Nothing will be deleted.')">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="SUSPENDED">
                        <button class="btn-secondary w-full" data-loading-label="Putting on hold…">Put on hold</button>
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Temporarily blocks access. Reversible at any time.</p>
                    </form>
                @endif

                @if ($workspace->status !== 'ARCHIVED')
                    <form method="POST" action="{{ route('admin.workspaces.status', $workspace) }}" class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-700"
                          onsubmit="return confirm('Close this workspace? Their data is kept, but the workspace can no longer be used and reopening it needs support.')">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="ARCHIVED">
                        <button class="btn-danger w-full" data-loading-label="Closing…">Close workspace</button>
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Ends the relationship. Data is kept for the record.</p>
                    </form>
                @endif
            </section>

            <section class="section-card p-5" aria-labelledby="people-heading">
                <h2 id="people-heading" class="text-sm font-bold text-slate-900 dark:text-white">People</h2>
                <ul class="mt-3 space-y-2.5">
                    @forelse ($workspace->members as $member)
                        <li class="flex items-start justify-between gap-3 text-sm">
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-slate-900 dark:text-white">{{ $member->user?->name ?? 'Unknown' }}</span>
                                <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ $member->user?->email }}</span>
                            </span>
                            <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                {{ ucfirst(strtolower($member->role)) }}
                            </span>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500 dark:text-slate-400">Nobody has joined yet.</li>
                    @endforelse
                </ul>
            </section>

            <section class="section-card p-5" aria-labelledby="events-heading">
                <h2 id="events-heading" class="text-sm font-bold text-slate-900 dark:text-white">Recent events</h2>
                <ul class="mt-3 space-y-2.5">
                    @forelse ($recentActivity as $entry)
                        <li class="text-xs">
                            <p class="text-slate-700 dark:text-slate-200">{{ \App\Support\AuditEventLabel::for($entry->event) }}</p>
                            <p class="text-slate-400">{{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at?->diffForHumans() }}</p>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500 dark:text-slate-400">Nothing recorded yet.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-admin-layout>
