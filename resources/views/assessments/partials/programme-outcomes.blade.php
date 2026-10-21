@php
    $tone = fn ($row) => match (true) {
        ($row['improved'] ?? null) === true => 'text-emerald-700 dark:text-emerald-300',
        ($row['improved'] ?? null) === false => 'text-red-700 dark:text-red-300',
        default => 'text-slate-500 dark:text-slate-400',
    };
@endphp
<div class="mb-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
    <h2 class="text-sm font-bold text-slate-900 dark:text-white">What girls and women told us</h2>
    <p class="mt-1 text-sm text-slate-700 dark:text-slate-200">
        <span class="text-xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $outcomes['respondents'] }}</span>
        {{ $outcomes['respondents'] === 1 ? 'person' : 'people' }} answered.
        @if ($outcomes['previous'])
            Compared with the earlier assessment of {{ $outcomes['previous']['respondents'] }}
            ({{ \Illuminate\Support\Carbon::parse($outcomes['previous']['completed_at'])->format('d M Y') }}).
        @else
            This is the first assessment here, so it is the starting point.
        @endif
    </p>
    @unless ($outcomes['enough'])
        <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Fewer than {{ $outcomes['minimum'] }} answered. Treat these figures as a rough guide only.</p>
    @endunless

    @foreach ($outcomes['outcomes'] as $outcome)
        <h3 class="mt-5 text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $outcome['name'] }}</h3>
        <ul class="mt-1 divide-y divide-slate-100 dark:divide-slate-700">
            @foreach ($outcome['indicators'] as $row)
                <li class="py-3">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm text-slate-800 dark:text-slate-100">{{ $row['label'] }}</p>
                            <p class="mt-0.5 text-[11px] text-slate-400 dark:text-slate-500">
                                {{ $row['direction'] === 'DOWN' ? 'Lower is better' : 'Higher is better' }}
                                @if ($row['kind'] === 'KNOWLEDGE') · average marks @endif
                                · {{ $row['n'] }} {{ $row['n'] === 1 ? 'answer' : 'answers' }}
                            </p>
                        </div>
                        <div class="flex-shrink-0 text-right">
                            <p class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $row['value'] !== null ? round($row['value']).'%' : '—' }}</p>
                            @if (($row['change'] ?? null) !== null)
                                <p class="text-xs font-semibold tabular-nums {{ $tone($row) }}">
                                    {{ $row['change'] > 0 ? '▲' : ($row['change'] < 0 ? '▼' : '■') }}
                                    {{ abs($row['change']) }} points from {{ round($row['before']) }}%
                                </p>
                            @endif
                        </div>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                        <div class="h-2 rounded-full {{ $row['direction'] === 'DOWN' ? 'bg-amber-400' : 'bg-vytte-600' }}" style="width: {{ $row['value'] !== null ? round($row['value']) : 0 }}%"></div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endforeach
    <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">Each figure is the share of people who gave that answer. Anyone who chose not to answer a question is left out of that figure. Answers are anonymous.</p>
</div>
