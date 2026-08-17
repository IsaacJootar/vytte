<x-app-layout title="Collect & review responses">
    @php
        $canFinalize = auth()->user()->can('finalizeMultiRespondent', $assessment);
        $isComplete = $assessment->isComplete();
        $eligible = $preview['eligible_respondent_count'];
        $minimum = $preview['minimum_completed_respondents'];
        $sessions = $assessment->publicResponseSessions->sortByDesc('last_activity_at');
        $submitted = $sessions->whereNotNull('submitted_at');
        $inProgress = $sessions->whereNull('submitted_at')->count();
        $completionRate = $sessions->isNotEmpty() ? (int) round($submitted->count() / $sessions->count() * 100) : 0;

        // Where the collection sits in its four-step workflow.
        $step = $isComplete ? 4 : ($assessment->isDraft() ? 1 : ($eligible > 0 || $assessment->isClosed() ? 3 : 2));
        $steps = [
            1 => ['Open & share', 'Lock setup and create the first link'],
            2 => ['Collect', 'People answer independently'],
            3 => ['Review', 'Check who counts'],
            4 => ['Finalise', 'Combine into one result'],
        ];
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('assessments.show', $assessment) }}" class="text-sm font-medium text-vytte-700 hover:underline">← Back to assessment</a>
            <h1 class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">Collect & review responses</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $assessment->project?->name }} · {{ $assessment->target?->name }}
            </p>
        </div>
        @if ($isComplete)
            <a href="{{ route('assessments.results', $assessment) }}"
               class="rounded-lg bg-vytte-700 px-4 py-2 text-sm font-semibold text-white hover:bg-vytte-800">
                View final report
            </a>
        @elseif ($assessment->isDraft())
            <form method="POST" action="{{ route('assessments.open-and-create-link', $assessment) }}"
                  onsubmit="return confirm('Open this assessment for responses? Its questions will be locked, but you can close or reopen collection later.')">
                @csrf
                <button class="rounded-lg bg-vytte-700 px-4 py-2 text-sm font-semibold text-white hover:bg-vytte-800">
                    Open for responses & create link
                </button>
            </form>
        @elseif ($assessment->isClosed())
            <form method="POST" action="{{ route('assessments.reopen', $assessment) }}">
                @csrf
                <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200">
                    Reopen collection
                </button>
            </form>
        @elseif ($respondentTokens->isEmpty())
            <form method="POST" action="{{ route('assessments.respondent-link', $assessment) }}">
                @csrf
                <button class="rounded-lg bg-vytte-700 px-4 py-2 text-sm font-semibold text-white hover:bg-vytte-800">
                    Create replacement link
                </button>
            </form>
        @endif
    </div>

    {{-- The four-step harmonisation workflow, so nobody is unsure what comes next. --}}
    <div class="mb-6 grid grid-cols-4 gap-2">
        @foreach ($steps as $n => [$label, $hint])
            @php $done = $n < $step; $current = $n === $step; @endphp
            <div class="rounded-xl border p-3 {{ $current ? 'border-vytte-300 bg-vytte-50 dark:border-vytte-700 dark:bg-vytte-900/20' : ($done ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/10' : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800') }}">
                <div class="flex items-center gap-2">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold {{ $current ? 'bg-vytte-600 text-white' : ($done ? 'bg-green-600 text-white' : 'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-400') }}">
                        {{ $done ? '✓' : $n }}
                    </span>
                    <span class="text-xs font-bold {{ $current ? 'text-vytte-800 dark:text-vytte-300' : ($done ? 'text-green-800 dark:text-green-300' : 'text-slate-500 dark:text-slate-400') }}">{{ $label }}</span>
                </div>
                <p class="mt-1 text-[10px] leading-tight text-slate-400 dark:text-slate-500">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

    @if ($assessment->isDraft())
        <div class="mb-5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-4 py-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-slate-500 dark:text-slate-400">Need more setting-specific context? Add local questions before sharing the link.</p>
            <a href="{{ route('assessments.custom.edit', $assessment) }}" class="flex-shrink-0 text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:text-vytte-900 dark:hover:text-vytte-200">Add local questions →</a>
        </div>
    @endif

    @if ($respondentTokens->isNotEmpty())
        <div class="mb-5 section-card p-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ $respondentTokens->count() === 1 ? 'Respondent link' : 'Active respondent links' }}</h2>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                Send this link to everyone who should answer. One link can collect responses from many people, and they do not need a Vytte account.
                Email is switched off during beta, so share these yourself.
            </p>

            @if ($respondentTokens->count() > 1)
                <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                    This assessment has {{ $respondentTokens->count() }} active links from the earlier workflow. Keep the one you use and deactivate the extras. Completed responses remain available.
                </p>
            @endif

            <div class="mt-3 space-y-3">
                @foreach ($respondentTokens as $respondentToken)
                    <div>
                        <x-share-link
                            :url="route('respondent.show', $respondentToken->token)"
                            :message="'Please complete this assessment for '.($assessment->target?->name ?? 'our facility').'. It takes a few minutes and you do not need an account:'"
                            :label="$respondentTokens->count() === 1 ? 'Respondent link' : 'Link '.($loop->iteration)"
                            :hint="'Created '.$respondentToken->created_at?->diffForHumans()" />

                        <form method="POST" action="{{ route('assessments.respondent-link.destroy', [$assessment, $respondentToken]) }}"
                              class="mt-1 text-right"
                              onsubmit="return confirm('Deactivate this link? It will stop future access. Completed responses remain available for review and can still count unless you exclude them.')">
                            @csrf @method('DELETE')
                            <button class="text-xs font-medium text-slate-400 transition-colors hover:text-red-600 dark:text-slate-500 dark:hover:text-red-400"
>
                                Deactivate this link
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <x-stat-card tone="blue" label="Responses started" :value="$sessions->count()"
                     :sub="$inProgress.' still in progress'" />
        <x-stat-card tone="strong" label="Completed" :value="$submitted->count()"
                     :sub="$completionRate.'% of those started'" />
        <x-stat-card :tone="$eligible >= $minimum ? 'strong' : 'moderate'" label="Eligible" :value="$eligible"
                     :sub="$minimum.' needed to finalise'" />
        <x-stat-card tone="slate" label="Required minimum" :value="$minimum" sub="Eligible completed responses" />
        <x-stat-card tone="blue" label="Provisional score"
                     :value="$preview['result']['overall_score'] === null ? '—' : number_format($preview['result']['overall_score'], 2)"
                     sub="Updates until finalisation" />
        <x-stat-card tone="slate" label="Excluded" :value="$preview['excluded_session_count']" sub="Not included in the result" />
    </div>

    <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
        <h2 class="font-semibold text-slate-900 dark:text-white">Finalization contract</h2>
        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-slate-500">Method</dt><dd class="font-medium">Arithmetic mean</dd></div>
            <div><dt class="text-slate-500">Scoring profile</dt><dd class="font-medium">{{ $preview['scoring_version'] }}</dd></div>
            <div><dt class="text-slate-500">Catalogue release</dt><dd class="font-mono text-xs">{{ $preview['catalogue_release_id'] }}</dd></div>
            <div><dt class="text-slate-500">State</dt><dd class="font-medium">{{ $isComplete ? 'Final and immutable' : 'Provisional' }}</dd></div>
        </dl>
        @if ($preview['respondent_eligibility_rules'])
            <p class="mt-4 text-xs text-slate-500">
                Eligibility rules are frozen with this assessment and require an authorized review before included sessions are finalized.
            </p>
        @endif
    </div>

    <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800" aria-labelledby="responses-heading">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <h2 id="responses-heading" class="font-semibold text-slate-900 dark:text-white">Response progress and review</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">See who has started, review completed responses, and decide which responses count before finalising.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">Started</th>
                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">Last activity</th>
                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">Progress</th>
                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">Score</th>
                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">Eligibility review</th>
                        <th scope="col" class="px-5 py-2.5 text-right text-xs font-semibold text-slate-500 dark:text-slate-400"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse ($sessions as $session)
                        @php
                            $exclusion = collect($preview['excluded_sessions'])->firstWhere('session_id', $session->session_id);
                            [$eligibilityLabel, $eligibilityClasses] = match (true) {
                                $session->is_test => ['Test', 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'],
                                $session->eligibility_status === 'ELIGIBLE' => ['Eligible', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'],
                                $session->eligibility_status === 'EXCLUDED' => ['Excluded', 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200'],
                                default => ['Not reviewed', 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400'],
                            };
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300">{{ $session->started_at?->diffForHumans() ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $session->last_activity_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $session->submitted_at ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' }}">
                                    {{ $session->submitted_at ? 'Completed' : 'In progress' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 font-semibold text-slate-700 dark:text-slate-200">
                                {{ $session->scoreResult?->overall_score === null ? '—' : number_format((float) $session->scoreResult->overall_score, 2) }}
                            </td>
                            <td class="min-w-72 px-5 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $eligibilityClasses }}">{{ $eligibilityLabel }}</span>
                                @if ($exclusion)
                                    <p class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-300">{{ $exclusion['reason'] }}</p>
                                @endif
                                @if ($canFinalize && ! $isComplete && $session->submitted_at)
                                    <form method="POST" action="{{ route('assessments.respondent-sessions.classify', [$assessment, $session]) }}" class="mt-2 flex flex-wrap items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="classification" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                                            <option value="ELIGIBLE" @selected($session->eligibility_status === 'ELIGIBLE' && ! $session->is_test)>Eligible</option>
                                            <option value="EXCLUDED" @selected($session->eligibility_status === 'EXCLUDED' && ! $session->is_test)>Exclude</option>
                                            <option value="TEST" @selected($session->is_test)>Test</option>
                                        </select>
                                        <input name="reason" value="{{ $session->eligibility_reason }}" placeholder="Reason when excluded"
                                               class="min-w-44 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                                        <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold dark:border-slate-600">Save</button>
                                    </form>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                @if ($session->submitted_at)
                                    <a href="{{ route('assessments.respondent-sessions.show', [$assessment, $session]) }}" class="text-xs font-semibold text-vytte-700 hover:underline dark:text-vytte-400">View response →</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center">
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No responses yet</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Share the respondent link above. Responses will appear here as they arrive.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($canFinalize && ! $isComplete)
        <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="font-semibold text-slate-900 dark:text-white">Manual finalization</h2>
            <p class="mt-1 text-sm text-slate-500">
                Finalization freezes the current eligible session set and creates the ordinary immutable Vytte report.
                Later submissions cannot change it.
            </p>
            <form method="POST" action="{{ route('assessments.respondent-collection.finalize', $assessment) }}" class="mt-4">
                @csrf
                <button @disabled($eligible < $minimum)
                        class="rounded-lg bg-vytte-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40">
                    Finalize collection
                </button>
            </form>
        </div>
    @endif
</x-app-layout>
