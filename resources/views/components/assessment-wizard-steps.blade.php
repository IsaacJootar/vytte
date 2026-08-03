@props(['steps', 'currentStep', 'assessment' => null])

@php
    $currentIndex = collect($steps)->search(fn ($step) => $step['key'] === $currentStep);
    $assessmentRoutes = $assessment ? [
        'purpose' => $assessment->status === 'DRAFT'
            ? route('admin.assessments.edit', $assessment)
            : route('admin.assessments.show', $assessment),
        'publisher' => route('admin.assessments.governance', $assessment),
        'structure' => route('admin.assessments.build', $assessment),
        'questions' => route('admin.assessments.questions', $assessment),
        'logic' => route('admin.assessments.logic', $assessment),
        'scoring' => route('admin.assessments.scoring', $assessment),
        'review' => route('admin.assessments.quality', $assessment),
        'test' => route('admin.assessments.preview', $assessment),
        'publish' => route('admin.assessments.publish-page', $assessment),
    ] : [];
@endphp

<nav class="mb-6 overflow-x-auto pb-2" aria-label="Assessment authoring steps">
<ol class="flex min-w-max items-center gap-2">
    @foreach ($steps as $index => $step)
        @php
            $isCurrent = $step['key'] === $currentStep;
            $isDone = $currentIndex !== false && $index < $currentIndex;
        @endphp
        <li class="flex items-center gap-2">
            @php($classes = [
                'flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold',
                'bg-vytte-600 text-white' => $isCurrent,
                'bg-vytte-50 text-vytte-700 hover:bg-vytte-100 dark:bg-vytte-900/40 dark:text-vytte-200' => $isDone,
                'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' => ! $isCurrent && ! $isDone,
            ])
            @if (isset($assessmentRoutes[$step['key']]) && $step['available'])
                <a href="{{ $assessmentRoutes[$step['key']] }}" @class($classes) @if ($isCurrent) aria-current="step" @endif>
                    <span class="flex h-4 w-4 items-center justify-center rounded-full border border-current text-[9px] font-bold">{{ $index + 1 }}</span>
                    {{ $step['label'] }}
                </a>
            @else
                <span @class($classes) @if ($isCurrent) aria-current="step" @endif>
                    <span class="flex h-4 w-4 items-center justify-center rounded-full border border-current text-[9px] font-bold">{{ $index + 1 }}</span>
                    {{ $step['label'] }}
                    @unless ($step['available'])
                        <span class="text-[9px] font-medium uppercase tracking-wide opacity-70">Coming next</span>
                    @endunless
                </span>
            @endif
            @if (! $loop->last)
                <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">›</span>
            @endif
        </li>
    @endforeach
</ol>
</nav>
