<x-app-layout title="{{ $subject }}">
    <div class="max-w-3xl mx-auto">

        <a href="{{ route('projects.show', $assessment->project_id) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 mb-3">
            ← {{ $assessment->project?->name }}
        </a>

        @php
            $badge = match ($status) {
                'complete'   => ['Complete', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'],
                'collecting' => ['Collecting responses', 'bg-vytte-50 text-vytte-700 dark:bg-vytte-900/30 dark:text-vytte-300'],
                'closed'     => ['Collection closed', 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'],
                'answering'  => ['Answering', 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'],
                default      => ['Draft', 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'],
            };
        @endphp

        {{-- Header --}}
        <div class="mb-5">
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $badge[1] }}">{{ $badge[0] }}</span>
            <h1 class="mt-1.5 text-2xl font-bold text-slate-900 dark:text-white">{{ $subject }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $assessment->target?->name }}@if ($assessment->project?->name) · {{ $assessment->project->name }}@endif
                @if ($assessment->completed_at) · Completed {{ $assessment->completed_at->format('d M Y') }}
                @elseif ($assessment->started_at) · Started {{ $assessment->started_at->format('d M Y') }}@endif
            </p>
        </div>

        {{-- Lifecycle strip --}}
        @php
            $stage = in_array($status, ['complete']) ? 3 : ($status === 'draft' ? 1 : 2);
            $answerLabel = $allowsMultiRespondent && in_array($status, ['collecting', 'closed']) ? 'Collect' : 'Answer';
            $stages = ['Set up', $answerLabel, 'Report'];
        @endphp
        <ol class="mb-6 flex items-center gap-2 text-xs font-semibold">
            @foreach ($stages as $i => $label)
                @php $n = $i + 1; $st = $n < $stage ? 'done' : ($n === $stage ? 'current' : 'todo'); @endphp
                <li class="flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $st === 'current' ? 'bg-vytte-700 text-white' : ($st === 'done' ? 'bg-vytte-100 text-vytte-700 dark:bg-vytte-900/40 dark:text-vytte-300' : 'bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-500') }}">
                        @if ($st === 'done')<svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>@else{{ $n }}@endif
                    </span>
                    <span class="{{ $st === 'current' ? 'text-slate-900 dark:text-white' : 'hidden sm:inline text-slate-400 dark:text-slate-500' }}">{{ $label }}</span>
                </li>
                @if (! $loop->last)<li class="h-px flex-1 bg-slate-200 dark:bg-slate-700" aria-hidden="true"></li>@endif
            @endforeach
        </ol>

        {{-- Primary action --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            @if ($status === 'complete')
                <p class="text-sm font-semibold text-slate-900 dark:text-white">This assessment is complete.</p>
                @if ($assessment->score?->overall_score !== null)
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        Overall score {{ number_format((float) $assessment->score->overall_score, 1) }} / 100
                        @if ($assessment->score->maturityLevel)
                            · {{ $assessment->score->maturityLevel->level_name }}
                        @endif
                    </p>
                @endif
                <a href="{{ route('assessments.results', $assessment) }}" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-vytte-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-800 transition-colors">View report →</a>
            @elseif ($status === 'collecting')
                <p class="text-sm font-semibold text-slate-900 dark:text-white">Collecting responses by shared link.</p>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $respondentDone }} of {{ $respondentTotal }} respondents have finished.</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('assessments.respondent-collection', $assessment) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-vytte-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-800 transition-colors">Collect & review responses →</a>
                </div>
            @elseif ($status === 'closed')
                <p class="text-sm font-semibold text-slate-900 dark:text-white">Collection is closed.</p>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Review the responses and finalize the result.</p>
                <a href="{{ route('assessments.respondent-collection', $assessment) }}" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-vytte-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-800 transition-colors">Review & finalize →</a>
            @elseif ($status === 'answering')
                <p class="text-sm font-semibold text-slate-900 dark:text-white">You've started answering this yourself.</p>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Pick up where you left off — your answers are saved.</p>
                <a href="{{ route('assessments.run', $assessment) }}" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-vytte-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-800 transition-colors">Continue answering →</a>
            @else
                <p class="text-sm font-semibold text-slate-900 dark:text-white">This assessment is a draft.</p>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Finish setting it up — add your own questions and choose how it's answered.</p>
                <a href="{{ route('assessments.setup', $assessment) }}" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-vytte-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-800 transition-colors">Continue setup →</a>
            @endif
        </div>

        {{-- Everything about this assessment --}}
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            {{-- Questions --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Questions</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ $questionCount }} Vytte question{{ $questionCount === 1 ? '' : 's' }}
                    @if ($customCount > 0)
                        · {{ $customCount }} local
                    @endif
                </p>
                @if ($areas->isNotEmpty())<p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ $areas->join(' · ') }}</p>@endif
                @unless ($status === 'complete' || $status === 'collecting' || $status === 'closed')
                    <a href="{{ route('assessments.custom.edit', $assessment) }}" class="mt-3 inline-block text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:underline">{{ $customCount > 0 ? 'Edit local questions' : 'Add local questions' }} →</a>
                @endunless
            </div>

            {{-- Respondents & links --}}
            @if ($allowsMultiRespondent || $assessment->isPublished())
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Respondents & links</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        @if ($assessment->isPublished()){{ $respondentDone }} of {{ $respondentTotal }} finished · {{ $activeLinks }} active link{{ $activeLinks === 1 ? '' : 's' }}
                        @else Share a link so others can answer independently.@endif
                    </p>
                    <a href="{{ route('assessments.respondent-collection', $assessment) }}" class="mt-3 inline-block text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:underline">{{ $assessment->isPublished() ? 'Collect & review responses' : 'Set up collection' }} →</a>
                </div>
            @endif

            {{-- Report & exports --}}
            @if ($status === 'complete')
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Report & exports</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">View the full report, export, or share it.</p>
                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-xs font-semibold">
                        <a href="{{ route('assessments.results', $assessment) }}" class="text-vytte-700 dark:text-vytte-400 hover:underline">Report</a>
                        <a href="{{ route('assessments.export.pdf', $assessment) }}" class="text-vytte-700 dark:text-vytte-400 hover:underline">PDF</a>
                        <a href="{{ route('assessments.export.word', $assessment) }}" class="text-vytte-700 dark:text-vytte-400 hover:underline">Word</a>
                        <a href="{{ route('assessments.export.excel', $assessment) }}" class="text-vytte-700 dark:text-vytte-400 hover:underline">Excel</a>
                        <a href="{{ route('projects.progress', $assessment->project_id) }}" class="text-vytte-700 dark:text-vytte-400 hover:underline">Progress</a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
