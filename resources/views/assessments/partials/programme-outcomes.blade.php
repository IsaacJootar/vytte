@php
    $tone = fn ($row) => match (true) {
        ($row['improved'] ?? null) === true => 'text-emerald-700 dark:text-emerald-300',
        ($row['improved'] ?? null) === false => 'text-red-700 dark:text-red-300',
        default => 'text-slate-500 dark:text-slate-400',
    };
    $card = 'rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800';

    // Outcomes in the programme's own order: the main ones, then the Dignity Bank outcome (measured at the
    // follow-up), then the secondary one.
    $all = collect($outcomes['outcomes']);
    $isSecondary = fn ($outcome) => str_contains($outcome['name'], 'secondary');
    $cards = $all->reject($isSecondary)->map(fn ($outcome) => ['kind' => 'outcome', 'outcome' => $outcome])->values();
    if ($outcomes['endline_indicators']) {
        $cards->push(['kind' => 'endline']);
    }
    $cards = $cards->concat($all->filter($isSecondary)->map(fn ($outcome) => ['kind' => 'outcome', 'outcome' => $outcome]))->values();
@endphp
<div class="mb-3 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h2 class="text-base font-bold text-slate-900 dark:text-white">What girls and women told us</h2>
        <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">
            <span class="font-bold tabular-nums text-slate-900 dark:text-white">{{ $outcomes['respondents'] }}</span>
            {{ $outcomes['respondents'] === 1 ? 'person' : 'people' }} answered.
            @if ($outcomes['previous'])
                Compared with the earlier assessment of {{ $outcomes['previous']['respondents'] }}
                ({{ \Illuminate\Support\Carbon::parse($outcomes['previous']['completed_at'])->format('d M Y') }}).
            @else
                This is the first assessment here, so it is the starting point. The next one will show what changed.
            @endif
        </p>
    </div>
    @if ($outcomes['assistance'] && $outcomes['assistance']['answered'] > 0)
        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
            {{ $outcomes['assistance']['helped'] }} of {{ $outcomes['assistance']['answered'] }} said someone helped them answer
            (read the questions to them or answered for them).
        </p>
    @endif
    @unless ($outcomes['enough'])
        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Fewer than {{ $outcomes['minimum'] }} answered: a rough guide only</span>
    @endunless
</div>

<div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
    @foreach ($cards as $position => $item)
        @if ($item['kind'] === 'endline')
            <div class="{{ $card }}">
                <div class="flex items-start gap-3">
                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg bg-slate-300 text-sm font-bold text-slate-700 dark:bg-slate-600 dark:text-slate-100">{{ $position + 1 }}</span>
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold leading-snug text-slate-900 dark:text-white">Dignity Banks that keep working</h3>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Does the support carry on without the programme running everything? This is measured at the follow-up, about 12 months after a Dignity Bank opens.</p>
                    </div>
                </div>
                <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($outcomes['endline_indicators'] as $line)
                        <li class="flex items-center justify-between gap-3 py-2.5 text-sm text-slate-700 dark:text-slate-200">
                            <span>{{ $line }}</span>
                            <span class="flex-shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-700 dark:text-slate-300">Measured at follow-up</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            @php $outcome = $item['outcome']; @endphp
            <div class="{{ $card }}">
                <div class="flex items-start gap-3">
                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg bg-vytte-700 text-sm font-bold text-white">{{ $position + 1 }}</span>
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold leading-snug text-slate-900 dark:text-white">{{ $outcome['name'] }}</h3>
                        @if ($outcome['note'])
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $outcome['note'] }}</p>
                        @endif
                    </div>
                </div>
                <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-700">
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
                            <div class="relative mt-2 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                                <div class="h-2 rounded-full {{ $row['direction'] === 'DOWN' ? 'bg-amber-400' : 'bg-vytte-600' }}" style="width: {{ $row['value'] !== null ? round($row['value']) : 0 }}%"></div>
                                @if (($row['before'] ?? null) !== null)
                                    <span class="absolute top-0 h-2 w-0.5 bg-slate-700 dark:bg-slate-200" style="left: {{ round($row['before']) }}%" title="Before: {{ round($row['before']) }}%"></span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach
</div>
