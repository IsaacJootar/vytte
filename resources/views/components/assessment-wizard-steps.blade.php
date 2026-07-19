@props(['steps', 'currentStep'])

@php
    $currentIndex = collect($steps)->search(fn ($step) => $step['key'] === $currentStep);
@endphp

<ol class="mb-6 flex flex-wrap items-center gap-2" aria-label="Assessment progress">
    @foreach ($steps as $index => $step)
        @php
            $isCurrent = $step['key'] === $currentStep;
            $isDone = $currentIndex !== false && $index < $currentIndex;
        @endphp
        <li class="flex items-center gap-2">
            <span @class([
                'flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold',
                'bg-vytte-600 text-white' => $isCurrent,
                'bg-vytte-50 text-vytte-700 dark:bg-vytte-900/40 dark:text-vytte-200' => $isDone,
                'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => ! $isCurrent && ! $isDone,
            ])
                @if ($isCurrent) aria-current="step" @endif>
                <span class="flex h-4 w-4 items-center justify-center rounded-full border border-current text-[9px] font-bold">{{ $index + 1 }}</span>
                {{ $step['label'] }}
                @unless ($step['available'])
                    <span class="text-[9px] font-medium uppercase tracking-wide opacity-70">Coming next</span>
                @endunless
            </span>
            @if (! $loop->last)
                <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">›</span>
            @endif
        </li>
    @endforeach
</ol>
