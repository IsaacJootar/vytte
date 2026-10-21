@php
    $bandBar = ['strong' => 'bg-emerald-500', 'fair' => 'bg-amber-400', 'weak' => 'bg-red-500', 'none' => 'bg-slate-300'];
    $bandText = ['strong' => 'text-emerald-700 dark:text-emerald-300', 'fair' => 'text-amber-700 dark:text-amber-300', 'weak' => 'text-red-700 dark:text-red-300', 'none' => 'text-slate-500'];
    $bandRing = ['strong' => 'stroke-emerald-500', 'fair' => 'stroke-amber-400', 'weak' => 'stroke-red-500', 'none' => 'stroke-slate-300'];
    $labelTone = [
        'HIGH_NEED_READY' => 'bg-emerald-400 text-emerald-950',
        'HIGH_NEED_NOT_READY' => 'bg-amber-300 text-amber-950',
        'LOWER_NEED_READY' => 'bg-sky-300 text-sky-950',
        'LOWER_NEED_NOT_READY' => 'bg-slate-300 text-slate-900',
    ];
    $site = $programme['site'];
    $classification = $programme['classification'];
    $outcomes = $programme['outcomes'];
    $circumference = 2 * M_PI * 34;
    $card = 'rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800';
    $cardTitle = 'text-sm font-bold text-slate-900 dark:text-white';
@endphp
<x-app-layout :title="'Report · ' . $site['name']">

    {{-- Cover --}}
    <div class="mb-5 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-vytte-800 p-6 text-white shadow-sm sm:p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-white/60">{{ $programme['programme'] }}</p>
                <h1 class="mt-2 text-2xl font-bold leading-tight tracking-tight sm:text-3xl">{{ $site['name'] }}</h1>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    @foreach (array_filter([
                        $site['area_council'] ? $site['area_council'].' area council' : null,
                        $site['state'],
                        $site['type'],
                        $site['assessed_on'] ? \Illuminate\Support\Carbon::parse($site['assessed_on'])->format('d M Y') : null,
                        $outcomes ? $outcomes['respondents'].' '.($outcomes['respondents'] === 1 ? 'person answered' : 'people answered') : null,
                    ]) as $chip)
                        <span class="rounded-full bg-white/10 px-3 py-1 font-medium text-white/90 ring-1 ring-white/15">{{ $chip }}</span>
                    @endforeach
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('assessments.export.pdf', $assessment) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3.5 py-2 text-sm font-semibold text-slate-900 transition-colors hover:bg-slate-100">
                    Download PDF
                </a>
                <a href="{{ route('programmes.show', $programme['programme_slug']) }}"
                   class="inline-flex items-center rounded-lg bg-white/10 px-3.5 py-2 text-sm font-semibold text-white ring-1 ring-white/20 transition-colors hover:bg-white/20">
                    Programme overview
                </a>
            </div>
        </div>

        @if ($classification)
            <div class="mt-6 flex flex-wrap items-center gap-3 rounded-xl bg-white/10 p-4 ring-1 ring-white/15">
                <span class="rounded-lg px-3.5 py-1.5 text-sm font-bold {{ $labelTone[$classification['key']] ?? $labelTone['LOWER_NEED_NOT_READY'] }}">{{ $classification['label'] }}</span>
                <p class="min-w-0 flex-1 text-sm text-white/90">{{ $classification['detail'] }}</p>
            </div>
        @endif
    </div>

    {{-- In short + the two scores --}}
    <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="{{ $card }} lg:col-span-1">
            <h2 class="{{ $cardTitle }}">In short</h2>
            <ul class="mt-3 space-y-2.5 text-sm text-slate-700 dark:text-slate-200">
                @foreach ($programme['summary'] as $line)
                    <li class="flex gap-2.5"><span class="mt-1.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-vytte-600" aria-hidden="true"></span><span>{{ $line }}</span></li>
                @endforeach
            </ul>
        </div>
        @foreach (['provision', 'readiness'] as $role)
            @php $block = $programme[$role]; $pct = $block['score'] !== null ? round($block['score']) : 0; @endphp
            <div class="{{ $card }} flex items-center gap-5">
                <div class="relative h-28 w-28 flex-shrink-0">
                    <svg viewBox="0 0 80 80" class="h-28 w-28 -rotate-90" aria-hidden="true">
                        <circle cx="40" cy="40" r="34" fill="none" stroke-width="8" class="stroke-slate-100 dark:stroke-slate-700"/>
                        <circle cx="40" cy="40" r="34" fill="none" stroke-width="8" stroke-linecap="round" stroke-dasharray="{{ round($circumference * $pct / 100, 1) }} {{ round($circumference, 1) }}" class="{{ $bandRing[$block['band']] }}"/>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-3xl font-bold tabular-nums {{ $bandText[$block['band']] }}">{{ $block['score'] !== null ? $pct : '—' }}</span>
                        <span class="text-[10px] font-medium text-slate-400">out of 100</span>
                    </div>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $block['label'] }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        @if ($role === 'provision')
                            Higher means girls' needs are better met today. Below {{ round($programme['thresholds']['provision_good_at_or_above']) }} counts as a high need.
                        @else
                            Needs {{ round($programme['thresholds']['readiness_ready_at_or_above']) }} or more, and every must-have in place.
                        @endif
                    </p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Must-haves and what to do next --}}
    <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="{{ $card }}">
            <h2 class="{{ $cardTitle }}">Must-haves for a Dignity Bank</h2>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">A Dignity Bank cannot start until each of these is in place.</p>
            <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-700">
                @foreach ($programme['must_haves'] as $item)
                    <li class="flex items-start gap-3 py-2.5 text-sm">
                        @if ($item['missing'])
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-red-100 text-xs font-bold text-red-700 dark:bg-red-900/40 dark:text-red-300" aria-hidden="true">✕</span>
                            <span class="text-slate-900 dark:text-white"><span class="font-semibold text-red-700 dark:text-red-300">Missing.</span> {{ $item['label'] }}</span>
                        @else
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300" aria-hidden="true">✓</span>
                            <span class="text-slate-600 dark:text-slate-300"><span class="font-semibold text-emerald-700 dark:text-emerald-300">In place.</span> {{ $item['ok'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
        @if ($programme['next_steps'])
        <div class="{{ $card }}">
            <h2 class="{{ $cardTitle }}">What to do next</h2>
            <ol class="mt-3 space-y-3 text-sm text-slate-700 dark:text-slate-200">
                @foreach ($programme['next_steps'] as $i => $step)
                    <li class="flex gap-3"><span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-vytte-100 text-xs font-bold text-vytte-800 dark:bg-vytte-900/40 dark:text-vytte-200">{{ $i + 1 }}</span><span class="pt-0.5">{{ $step }}</span></li>
                @endforeach
            </ol>
        </div>
        @endif
    </div>

    {{-- Section scores --}}
    <div class="{{ $card }} mb-5">
        <h2 class="{{ $cardTitle }}">How each area scores</h2>
        <div class="mt-4 grid grid-cols-1 gap-x-8 gap-y-4 md:grid-cols-2">
            @foreach ($programme['sections'] as $section)
                <div>
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="text-slate-800 dark:text-slate-200">{{ $section['name'] }}
                            <span class="ml-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $section['role'] === 'READINESS' ? 'Readiness' : 'Needs met' }}</span>
                        </span>
                        <span class="font-bold tabular-nums {{ $bandText[$section['band']] }}">{{ $section['score'] !== null ? round($section['score']) : '—' }}</span>
                    </div>
                    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                        <div class="h-2 rounded-full {{ $bandBar[$section['band']] }}" style="width: {{ $section['score'] !== null ? round($section['score']) : 0 }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Findings --}}
    <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="{{ $card }}">
            <h2 class="{{ $cardTitle }}">Biggest gaps</h2>
            @forelse ($programme['gaps'] as $gap)
                <p class="mt-3 border-t border-slate-100 pt-3 text-sm text-slate-800 first:mt-2 first:border-0 first:pt-0 dark:border-slate-700 dark:text-slate-200">{{ $gap['gap'] }}</p>
            @empty
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">No major gaps were found.</p>
            @endforelse
        </div>
        <div class="{{ $card }}">
            <h2 class="{{ $cardTitle }}">To get ready to host a Dignity Bank</h2>
            @forelse ($programme['readiness_gaps'] as $gap)
                <p class="mt-3 border-t border-slate-100 pt-3 text-sm text-slate-800 first:mt-2 first:border-0 first:pt-0 dark:border-slate-700 dark:text-slate-200">{{ $gap['gap'] }}</p>
            @empty
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Nothing further is needed to get ready.</p>
            @endforelse
        </div>
        <div class="{{ $card }}">
            <h2 class="{{ $cardTitle }}">Working well</h2>
            @forelse ($programme['strengths'] as $strength)
                <p class="mt-3 flex gap-2 border-t border-slate-100 pt-3 text-sm text-slate-700 first:mt-2 first:border-0 first:pt-0 dark:border-slate-700 dark:text-slate-200"><span class="text-emerald-600" aria-hidden="true">✓</span><span>{{ $strength['good'] }}</span></p>
            @empty
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Nothing scored at the top yet.</p>
            @endforelse
        </div>
    </div>

    {{-- What girls and women reported, by programme outcome --}}
    @if ($outcomes)
        @include('assessments.partials.programme-outcomes', ['outcomes' => $outcomes])
    @endif

    @if ($programme['profile'])
        <div class="{{ $card }} mb-5">
            <h2 class="{{ $cardTitle }}">About this {{ $programme['noun'] }}</h2>
            <ul class="mt-2 grid grid-cols-1 gap-x-8 gap-y-1 text-sm text-slate-700 sm:grid-cols-2 dark:text-slate-200">
                @foreach ($programme['profile'] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-8 rounded-2xl border border-dashed border-slate-300 p-5 text-xs leading-relaxed text-slate-500 dark:border-slate-600 dark:text-slate-400">
        <p class="font-semibold text-slate-700 dark:text-slate-200">How to read this report</p>
        <p class="mt-1">
            Each area is scored from 0 to 100, and the two headline scores are the plain average of their areas. A {{ $programme['noun'] }}
            is labelled ready only when its readiness score is {{ round($programme['thresholds']['readiness_ready_at_or_above']) }} or more and no must-have is missing.
            Girls' needs count as high when the needs-met score is below {{ round($programme['thresholds']['provision_good_at_or_above']) }}.
            When several people answered, scores are averaged, each finding reflects the most common answer, and a must-have counts as missing when at least half of them reported it.
            @if ($outcomes)
                The figures about girls and women are the share of people who gave each answer. Anyone who chose not to answer a question is left out of that figure. Answers are anonymous.
            @endif
        </p>
    </div>

</x-app-layout>
