@php
    $site = $survey['site'];
    $pct = fn ($v) => $v === null ? '—' : round($v).'%';
    // Which way is better decides the colour: a fall in "ran out of materials" is good news.
    $tone = fn ($row) => match (true) {
        ($row['improved'] ?? null) === true => 'text-emerald-700 dark:text-emerald-300',
        ($row['improved'] ?? null) === false => 'text-red-700 dark:text-red-300',
        default => 'text-slate-500 dark:text-slate-400',
    };
@endphp
<x-app-layout :title="'Survey report · ' . $site['name']">

    <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $survey['programme'] }} · Girls and women survey</p>
            <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $site['name'] }}</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                {{ collect([$site['area_council'] ? $site['area_council'].' area council' : null, $site['state'], $site['surveyed_on'] ? 'Surveyed '.\Illuminate\Support\Carbon::parse($site['surveyed_on'])->format('d M Y') : null])->filter()->implode(' · ') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('assessments.export.pdf', $assessment) }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-vytte-700 px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-vytte-800">
                Download PDF
            </a>
            <a href="{{ route('programmes.show', $survey['programme_slug']) }}"
               class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                Programme overview
            </a>
        </div>
    </div>

    <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
        <p class="text-sm text-slate-700 dark:text-slate-200">
            <span class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $survey['respondents'] }}</span>
            {{ $survey['respondents'] === 1 ? 'girl or woman' : 'girls and women' }} answered.
            @if ($survey['previous'])
                Compared with the earlier survey of {{ $survey['previous']['respondents'] }}
                ({{ \Illuminate\Support\Carbon::parse($survey['previous']['completed_at'])->format('d M Y') }}).
            @else
                This is the first survey here, so it is the starting point.
            @endif
        </p>
        @unless ($survey['enough'])
            <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                Fewer than {{ $survey['minimum'] }} answered. Treat these figures as a rough guide only.
            </p>
        @endunless
    </div>

    @foreach ($survey['outcomes'] as $outcome)
        <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ $outcome['name'] }}</h2>
            <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-700">
                @foreach ($outcome['indicators'] as $row)
                    <li class="py-3">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-sm text-slate-800 dark:text-slate-100">{{ $row['label'] }}</p>
                                <p class="mt-0.5 text-[11px] text-slate-400 dark:text-slate-500">
                                    {{ $row['direction'] === 'DOWN' ? 'Lower is better' : 'Higher is better' }}
                                    @if ($row['kind'] === 'KNOWLEDGE') · average marks @endif
                                    · {{ $row['n'] }} {{ \Illuminate\Support\Str::plural('answer', $row['n']) }}
                                </p>
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <p class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $pct($row['value']) }}</p>
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
        </div>
    @endforeach

    <p class="text-xs text-slate-400 dark:text-slate-500">
        Each figure is the share of girls and women who gave that answer. Anyone who chose not to answer a question is left out of that figure.
        Answers are anonymous.
    </p>

</x-app-layout>
