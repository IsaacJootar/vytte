<x-app-layout :title="'Action plan · ' . $project->name">

    {{-- Back + header --}}
    <div class="mb-6">
        <a href="{{ route('projects.show', $project) }}"
           class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors mb-2">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 010 1.06L8.06 10l3.72 3.72a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd"/>
            </svg>
            {{ $project->name }}
        </a>
        <p class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide">Action plan</p>
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight mt-0.5">{{ $project->name }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            What the assessments said to do, and where each item stands. Every action traces back to a finding.
        </p>
    </div>

    @php
        $openCount = $actions->whereIn('status', ['OPEN', 'IN_PROGRESS'])->count();
        $doneCount = $actions->whereIn('status', ['DONE', 'VERIFIED'])->count();
        $overdue = $actions->filter->isOverdue()->count();
    @endphp

    @if ($actions->isEmpty())
        <x-empty-state
            icon="check-circle"
            title="No actions yet"
            message="Open a completed assessment's report, go to 'What to do', and add a recommendation to the plan. It appears here to own, schedule, and verify."
            :action="route('projects.show', $project)"
            action-label="Open this project" />
    @else
        {{-- Summary --}}
        <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                <p class="text-2xl font-black text-slate-900 dark:text-white tabular-nums">{{ $openCount }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Still open</p>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                <p class="text-2xl font-black text-green-600 dark:text-green-400 tabular-nums">{{ $doneCount }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Done or verified</p>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                <p class="text-2xl font-black {{ $overdue > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }} tabular-nums">{{ $overdue }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Overdue</p>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            @foreach ($actions as $action)
                @php
                    $statusStyle = match ($action->status) {
                        'OPEN' => ['bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300', 'Open'],
                        'IN_PROGRESS' => ['bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300', 'In progress'],
                        'DONE' => ['bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300', 'Done'],
                        'VERIFIED' => ['bg-vytte-100 text-vytte-700 dark:bg-vytte-900/30 dark:text-vytte-300', 'Verified'],
                        default => ['bg-slate-100 text-slate-600', $action->status],
                    };
                    $priorityStyle = match ($action->priority) {
                        'HIGH' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                        'MEDIUM' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                        default => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                    };
                @endphp
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5"
                     x-data="{ open: false }">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full {{ $statusStyle[0] }}">{{ $statusStyle[1] }}</span>
                                <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full {{ $priorityStyle }}">{{ ucfirst(strtolower($action->priority)) }}</span>
                                @if ($action->isOverdue())
                                    <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">Overdue</span>
                                @endif
                            </div>
                            <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">{{ $action->title }}</p>
                            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                                Because: {{ $action->source_finding_statement }}
                            </p>
                            <div class="mt-2 flex items-center gap-3 flex-wrap text-xs text-slate-500 dark:text-slate-400">
                                <span>Owner: <span class="font-medium text-slate-700 dark:text-slate-300">{{ $action->owner?->name ?? 'Unassigned' }}</span></span>
                                @if ($action->due_date)
                                    <span>Due {{ $action->due_date->format('d M Y') }}</span>
                                @endif
                                @if ($action->isVerified() && $action->verifier)
                                    <span>Verified by {{ $action->verifier->name }} {{ $action->verified_at?->format('d M Y') }}</span>
                                @endif
                            </div>
                        </div>
                        <button type="button" @click="open = !open"
                                class="flex-shrink-0 text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:text-vytte-900 dark:hover:text-vytte-200">
                            <span x-show="!open">Update</span>
                            <span x-show="open" x-cloak>Close</span>
                        </button>
                    </div>

                    {{-- History --}}
                    @if ($action->updates->isNotEmpty())
                        <ul class="mt-3 border-t border-slate-100 dark:border-slate-700 pt-3 flex flex-col gap-2">
                            @foreach ($action->updates->take(4) as $update)
                                <li class="text-xs text-slate-500 dark:text-slate-400">
                                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ $update->author?->name ?? 'Someone' }}</span>
                                    @if ($update->isStatusChange())
                                        moved it to {{ str_replace('_', ' ', strtolower($update->status_to)) }}
                                    @endif
                                    @if ($update->note) — “{{ $update->note }}” @endif
                                    @if ($update->evidence_note) <span class="italic">(evidence: {{ $update->evidence_note }})</span> @endif
                                    <span class="text-slate-400 dark:text-slate-500">· {{ $update->created_at?->diffForHumans() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- Update form --}}
                    <div x-show="open" x-cloak class="mt-4 border-t border-slate-100 dark:border-slate-700 pt-4">
                        <form method="POST" action="{{ route('actions.update', $action) }}" class="flex flex-col gap-3">
                            @csrf @method('PATCH')
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                                    Status
                                    <select name="status" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-600 dark:bg-slate-700 text-sm">
                                        @foreach (['OPEN' => 'Open', 'IN_PROGRESS' => 'In progress', 'DONE' => 'Done', 'VERIFIED' => 'Verified'] as $value => $label)
                                            <option value="{{ $value }}" @selected($action->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                                    Owner
                                    <select name="owner_user_id" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-600 dark:bg-slate-700 text-sm">
                                        <option value="">Unassigned</option>
                                        @foreach ($members as $member)
                                            <option value="{{ $member->user_id }}" @selected($action->owner_user_id === $member->user_id)>{{ $member->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                                    Priority
                                    <select name="priority" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-600 dark:bg-slate-700 text-sm">
                                        @foreach (['HIGH' => 'High', 'MEDIUM' => 'Medium', 'LOW' => 'Low'] as $value => $label)
                                            <option value="{{ $value }}" @selected($action->priority === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                                    Due date
                                    <input type="date" name="due_date" value="{{ $action->due_date?->format('Y-m-d') }}"
                                           class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-600 dark:bg-slate-700 text-sm">
                                </label>
                            </div>
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                                Progress note
                                <textarea name="note" rows="2" placeholder="What changed?"
                                          class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-600 dark:bg-slate-700 text-sm"></textarea>
                            </label>
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                                Evidence (optional)
                                <input type="text" name="evidence_note" placeholder="Link or reference to proof"
                                       class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-600 dark:bg-slate-700 text-sm">
                            </label>
                            <div class="flex items-center justify-between">
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white bg-vytte-600 rounded-lg hover:bg-vytte-700 transition-colors">
                                    Save update
                                </button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('actions.destroy', $action) }}" class="mt-2 text-right"
                              onsubmit="return confirm('Remove this action from the plan? Its history will be deleted.')">
                            @csrf @method('DELETE')
                            <button class="text-xs font-medium text-slate-400 hover:text-red-600 dark:text-slate-500 dark:hover:text-red-400 transition-colors">
                                Remove action
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</x-app-layout>
