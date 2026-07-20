<x-app-layout title="Respondent collection">
    @php
        $canFinalize = auth()->user()->can('finalizeMultiRespondent', $assessment);
        $isComplete = $assessment->isComplete();
        $eligible = $preview['eligible_respondent_count'];
        $minimum = $preview['minimum_completed_respondents'];
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('assessments.index') }}" class="text-sm font-medium text-vytte-700 hover:underline">Assessments</a>
            <h1 class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">Respondent collection</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $assessment->project?->name }} · {{ $assessment->target?->name }}
            </p>
        </div>
        @if ($isComplete)
            <a href="{{ route('assessments.results', $assessment) }}"
               class="rounded-lg bg-vytte-700 px-4 py-2 text-sm font-semibold text-white hover:bg-vytte-800">
                View final report
            </a>
        @else
            <form method="POST" action="{{ route('assessments.respondent-link', $assessment) }}">
                @csrf
                <button class="rounded-lg bg-vytte-700 px-4 py-2 text-sm font-semibold text-white hover:bg-vytte-800">
                    Create respondent link
                </button>
            </form>
        @endif
    </div>

    @if (session('respondent_link'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-sm font-semibold text-emerald-900">Respondent link created</p>
            <input class="mt-2 w-full rounded-lg border-emerald-200 bg-white text-sm" readonly value="{{ session('respondent_link') }}">
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Eligible completed</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ $eligible }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Required minimum</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ $minimum }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Provisional score</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">
                {{ $preview['result']['overall_score'] === null ? '—' : number_format($preview['result']['overall_score'], 2) }}
            </p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Excluded</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ $preview['excluded_session_count'] }}</p>
        </div>
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

    <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <h2 class="font-semibold text-slate-900 dark:text-white">Respondent session audit</h2>
            <p class="mt-1 text-xs text-slate-500">Individual answers are not displayed here or in shared reports.</p>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse ($assessment->publicResponseSessions->sortByDesc('started_at') as $session)
                @php
                    $exclusion = collect($preview['excluded_sessions'])->firstWhere('session_id', $session->session_id);
                @endphp
                <div class="px-5 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-mono text-xs text-slate-700 dark:text-slate-300">{{ $session->session_id }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $session->submitted_at ? 'Submitted '.$session->submitted_at->format('d M Y H:i') : 'Incomplete' }}
                                · {{ $session->eligibility_status }}
                                @if ($session->scoreResult?->overall_score !== null)
                                    · Score {{ number_format((float) $session->scoreResult->overall_score, 2) }}
                                @endif
                            </p>
                            @if ($exclusion)
                                <p class="mt-1 text-xs font-medium text-amber-700">{{ $exclusion['reason'] }}</p>
                            @endif
                        </div>
                        @if ($canFinalize && ! $isComplete && $session->submitted_at)
                            <form method="POST" action="{{ route('assessments.respondent-sessions.classify', [$assessment, $session]) }}"
                                  class="flex flex-wrap items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <select name="classification" class="rounded-lg border-slate-300 text-xs">
                                    <option value="ELIGIBLE" @selected($session->eligibility_status === 'ELIGIBLE' && ! $session->is_test)>Eligible</option>
                                    <option value="EXCLUDED" @selected($session->eligibility_status === 'EXCLUDED' && ! $session->is_test)>Exclude</option>
                                    <option value="TEST" @selected($session->is_test)>Test</option>
                                </select>
                                <input name="reason" value="{{ $session->eligibility_reason }}" placeholder="Reason when excluded"
                                       class="rounded-lg border-slate-300 text-xs">
                                <button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold">Save</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="px-5 py-10 text-center text-sm text-slate-500">No respondent sessions yet.</p>
            @endforelse
        </div>
    </div>

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
