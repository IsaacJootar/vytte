<x-admin-layout title="Quality and governance review">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.assessments.preview', $assessment) }}" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Test</a>
            <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Quality and governance review</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Independent checks stay separate. Publisher identity never implies subject, scoring, licence, or field-test approval.</p>
        </div>
        <x-assessment-status-badge :status="$assessment->status" />
    </div>

    <x-assessment-wizard-steps :steps="$steps" :current-step="$currentStep" />

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
                <form method="POST" action="{{ route('admin.assessments.quality.claim', [$assessment, $claimType]) }}" class="section-card space-y-3 p-4 dark:border-slate-700 dark:bg-slate-800">
                    @csrf
                    @method('PUT')
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white">{{ str($claimType)->replace('_', ' ')->title() }}</h3>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold dark:bg-slate-700">{{ str($claim?->status ?? 'NOT_REVIEWED')->replace('_', ' ')->title() }}</span>
                    </div>
                    <select name="status" @disabled(! $isEditable) class="w-full rounded-lg border-slate-300 text-xs dark:border-slate-700 dark:bg-slate-900">
                        @foreach (\App\Models\ContentGovernanceClaim::STATUSES as $status)
                            <option value="{{ $status }}" @selected(($claim?->status ?? 'NOT_REVIEWED') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                    <textarea name="evidence_summary" rows="2" maxlength="5000" @disabled(! $isEditable) placeholder="Evidence and reviewer reasoning" class="w-full rounded-lg border-slate-300 text-xs dark:border-slate-700 dark:bg-slate-900">{{ $claim?->evidence_summary }}</textarea>
                    <input name="expires_at" type="date" value="{{ $claim?->expires_at?->format('Y-m-d') }}" @disabled(! $isEditable) class="w-full rounded-lg border-slate-300 text-xs dark:border-slate-700 dark:bg-slate-900">
                    @if ($isEditable)
                        <button class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold dark:border-slate-600">Save review</button>
                    @endif
                </form>
            @endforeach
        </div>
    </div>

    <div class="mt-5 flex justify-between gap-3">
        <a href="{{ route('admin.assessments.preview', $assessment) }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold dark:border-slate-600">&larr; Test</a>
        <a href="{{ route('admin.assessments.review', $assessment) }}" class="rounded-xl bg-vytte-600 px-5 py-2.5 text-sm font-semibold text-white">Continue to publish review &rarr;</a>
    </div>
</x-admin-layout>
