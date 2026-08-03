<x-admin-layout title="Quality and governance review">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.assessments.scoring', $assessment) }}" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Scoring</a>
            <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Quality and governance review</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Independent checks stay separate. Publisher identity never implies subject, scoring, licence, or field-test approval.</p>
        </div>
        <x-assessment-status-badge :status="$assessment->status" />
    </div>

    <x-assessment-wizard-steps :steps="$steps" :current-step="$currentStep" :assessment="$assessment" />

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Automated quality checks</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">These checks identify risks; they do not approve content.</p>
                    </div>
                    @if ($isEditable)
                        <form method="POST" action="{{ route('admin.assessments.quality.lint', $assessment) }}">
                            @csrf
                            <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold dark:border-slate-600">Run checks</button>
                        </form>
                    @endif
                </div>
                <ul class="mt-4 space-y-2">
                    @foreach ($baselineFindings as $finding)
                        <li class="rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-900/50">
                            <span class="mr-2 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $finding['severity'] === 'WARNING' ? 'bg-amber-100 text-amber-800' : ($finding['severity'] === 'PASS' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700') }}">{{ $finding['severity'] }}</span>
                            <span class="text-slate-700 dark:text-slate-200">{{ $finding['message'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">AI advisory review</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">AI can flag wording and burden concerns. It cannot edit, approve, score, or publish.</p>
                    </div>
                    @if ($isEditable)
                        <form method="POST" action="{{ route('admin.assessments.quality.ai', $assessment) }}">
                            @csrf
                            <button @disabled(! $aiAvailable) class="rounded-lg bg-vytte-600 px-3 py-1.5 text-xs font-semibold text-white disabled:bg-slate-300">Run AI review</button>
                        </form>
                    @endif
                </div>
                @unless ($aiAvailable)
                    <p class="mt-3 text-xs text-slate-500">AI is not configured. This does not block human review or publishing.</p>
                @endunless
                @foreach ($assistanceRuns->where('run_type', 'AI_LINT') as $run)
                    <div class="mt-3 rounded-xl bg-vytte-50 p-4 dark:bg-vytte-950/30">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-vytte-600">Advisory only &middot; {{ $run->model }} &middot; {{ $run->created_at->format('j M Y H:i') }}</p>
                        @foreach ($run->findings as $finding)
                            <p class="mt-2 whitespace-pre-line text-sm text-vytte-900 dark:text-vytte-100">{{ $finding['message'] }}</p>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        <div class="space-y-3">
            <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Trust signals</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Each claim needs its own evidence and reviewer.</p>
            </div>
            @foreach ($claimTypes as $claimType)
                @php($claim = $claims->get($claimType))
                @php($assignment = $assignments->get($claimType))
                <div class="section-card space-y-3 p-4 dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white">{{ str($claimType)->replace('_', ' ')->title() }}</h3>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold dark:bg-slate-700">{{ str($claim?->status ?? 'NOT_REVIEWED')->replace('_', ' ')->title() }}</span>
                    </div>
                    @if ($assignment)
                        <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-900 dark:text-slate-300">
                            <p><span class="font-semibold">Reviewer:</span> {{ $assignment->reviewer?->name }}</p>
                            <p class="mt-1"><span class="font-semibold">Workflow:</span> {{ str($assignment->status)->replace('_', ' ')->title() }}</p>
                            @if ($assignment->evidence_summary)<p class="mt-2 whitespace-pre-line"><span class="font-semibold">Evidence:</span> {{ $assignment->evidence_summary }}</p>@endif
                            @if ($assignment->decision_notes)<p class="mt-2 whitespace-pre-line"><span class="font-semibold">Decision:</span> {{ $assignment->decision_notes }}</p>@endif
                        </div>

                        @if ($isEditable && $assignment->reviewer_id === auth()->id() && in_array($assignment->status, ['ASSIGNED', 'CHANGES_REQUESTED'], true))
                            <form method="POST" action="{{ route('admin.assessments.quality.submit', [$assessment, $assignment]) }}" class="space-y-2">
                                @csrf @method('PUT')
                                <select name="recommendation" required class="w-full rounded-lg border-slate-300 text-xs dark:border-slate-700 dark:bg-slate-900">
                                    <option value="">Choose recommendation</option>
                                    <option value="PASSED">Evidence supports passing this check</option>
                                    <option value="FAILED">Evidence shows this check failed</option>
                                </select>
                                <textarea name="evidence_summary" required rows="3" maxlength="5000" placeholder="Evidence reviewed and what it shows" class="w-full rounded-lg border-slate-300 text-xs dark:border-slate-700 dark:bg-slate-900"></textarea>
                                <textarea name="reviewer_notes" rows="2" maxlength="5000" placeholder="Notes for the decision maker (optional)" class="w-full rounded-lg border-slate-300 text-xs dark:border-slate-700 dark:bg-slate-900"></textarea>
                                <button class="w-full rounded-lg bg-vytte-600 px-3 py-1.5 text-xs font-semibold text-white">Submit independent review</button>
                            </form>
                        @elseif ($isEditable && $assignment->status === 'SUBMITTED' && $assignment->reviewer_id !== auth()->id())
                            <form method="POST" action="{{ route('admin.assessments.quality.decide', [$assessment, $assignment]) }}" class="space-y-2">
                                @csrf @method('PUT')
                                <select name="decision" required class="w-full rounded-lg border-slate-300 text-xs dark:border-slate-700 dark:bg-slate-900">
                                    <option value="">Choose decision</option>
                                    <option value="APPROVE">Approve the review evidence</option>
                                    <option value="REQUEST_CHANGES">Request changes</option>
                                </select>
                                <textarea name="decision_notes" rows="2" maxlength="5000" placeholder="Decision reasoning or required changes" class="w-full rounded-lg border-slate-300 text-xs dark:border-slate-700 dark:bg-slate-900"></textarea>
                                <input name="expires_at" type="date" class="w-full rounded-lg border-slate-300 text-xs dark:border-slate-700 dark:bg-slate-900" aria-label="Review expiry date">
                                <button class="w-full rounded-lg border border-vytte-300 px-3 py-1.5 text-xs font-semibold text-vytte-700 dark:border-vytte-700 dark:text-vytte-200">Record decision</button>
                            </form>
                        @endif
                    @elseif ($isEditable)
                        <form method="POST" action="{{ route('admin.assessments.quality.assign', [$assessment, $claimType]) }}" class="space-y-2">
                            @csrf
                            <select name="reviewer_id" required class="w-full rounded-lg border-slate-300 text-xs dark:border-slate-700 dark:bg-slate-900">
                                <option value="">Assign an independent reviewer</option>
                                @foreach ($reviewers as $reviewer)
                                    <option value="{{ $reviewer->user_id }}">{{ $reviewer->name }} · {{ $reviewer->email }}</option>
                                @endforeach
                            </select>
                            @if ($reviewers->isEmpty())
                                <p class="text-xs text-amber-700 dark:text-amber-300">Add another Platform Admin before claiming an independent review.</p>
                            @else
                                <button class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold dark:border-slate-600">Assign review</button>
                            @endif
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-5 flex justify-between gap-3">
        <a href="{{ route('admin.assessments.scoring', $assessment) }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold dark:border-slate-600">&larr; Scoring</a>
        <a href="{{ route('admin.assessments.preview', $assessment) }}" class="rounded-xl bg-vytte-600 px-5 py-2.5 text-sm font-semibold text-white">Continue to test &rarr;</a>
    </div>
</x-admin-layout>
