@php
    $bandBar = ['strong' => 'bg-emerald-500', 'fair' => 'bg-amber-400', 'weak' => 'bg-red-500', 'none' => 'bg-slate-300'];
    $bandText = ['strong' => 'text-emerald-700 dark:text-emerald-300', 'fair' => 'text-amber-700 dark:text-amber-300', 'weak' => 'text-red-700 dark:text-red-300', 'none' => 'text-slate-500'];
    $labelTone = [
        'HIGH_NEED_READY' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
        'HIGH_NEED_NOT_READY' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        'LOWER_NEED_READY' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
        'LOWER_NEED_NOT_READY' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
    ];
    $site = $programme['site'];
    $classification = $programme['classification'];
@endphp
<x-app-layout :title="'Report · ' . $site['name']">

    <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $programme['programme'] }}</p>
            <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $site['name'] }}</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                {{ collect([$site['area_council'] ? $site['area_council'].' area council' : null, $site['state'], $site['type'], $site['assessed_on'] ? 'Assessed '.\Illuminate\Support\Carbon::parse($site['assessed_on'])->format('d M Y') : null])->filter()->implode(' · ') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('assessments.export.pdf', $assessment) }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-vytte-700 px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-vytte-800">
                Download PDF
            </a>
            <a href="{{ route('programmes.show', $programme['programme_slug']) }}"
               class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                Programme overview
            </a>
        </div>
    </div>

    @if ($classification)
        <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <div class="flex flex-wrap items-center gap-3">
                <span class="rounded-lg px-3 py-1.5 text-sm font-bold {{ $labelTone[$classification['key']] ?? $labelTone['LOWER_NEED_NOT_READY'] }}">{{ $classification['label'] }}</span>
                <p class="min-w-0 flex-1 text-sm text-slate-700 dark:text-slate-200">{{ $classification['detail'] }}</p>
            </div>
        </div>
    @endif

    <div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach (['provision', 'readiness'] as $role)
            @php $block = $programme[$role]; @endphp
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $block['label'] }}</p>
                <p class="mt-1 text-4xl font-bold tabular-nums {{ $bandText[$block['band']] }}">
                    {{ $block['score'] !== null ? round($block['score']) : '—' }}<span class="ml-1 text-base font-medium text-slate-400">/ 100</span>
                </p>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                    <div class="h-2 rounded-full {{ $bandBar[$block['band']] }}" style="width: {{ $block['score'] !== null ? round($block['score']) : 0 }}%"></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mb-5 rounded-2xl border {{ $programme['flags'] ? 'border-red-200 bg-red-50 dark:border-red-900/50 dark:bg-red-950/20' : 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/50 dark:bg-emerald-950/20' }} p-5">
        @if ($programme['flags'])
            <h2 class="text-sm font-bold text-red-800 dark:text-red-200">Must fix before a Dignity Bank can start</h2>
            <ul class="mt-2 space-y-1.5 text-sm text-red-900 dark:text-red-100">
                @foreach ($programme['flags'] as $flag)
                    <li class="flex gap-2"><span aria-hidden="true">•</span><span>{{ $flag }}</span></li>
                @endforeach
            </ul>
        @else
            <h2 class="text-sm font-bold text-emerald-800 dark:text-emerald-200">No must-have problems found</h2>
            <p class="mt-1 text-sm text-emerald-900 dark:text-emerald-100">Every condition needed to host a Dignity Bank is in place or could be put in place.</p>
        @endif
    </div>

    <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Section scores</h2>
        <div class="mt-3 space-y-3">
            @foreach ($programme['sections'] as $section)
                <div>
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="text-slate-800 dark:text-slate-200">{{ $section['name'] }}
                            <span class="ml-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $section['role'] === 'READINESS' ? 'Readiness' : 'Provision' }}</span>
                        </span>
                        <span class="font-bold tabular-nums {{ $bandText[$section['band']] }}">{{ $section['score'] !== null ? round($section['score']) : '—' }}</span>
                    </div>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                        <div class="h-1.5 rounded-full {{ $bandBar[$section['band']] }}" style="width: {{ $section['score'] !== null ? round($section['score']) : 0 }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Biggest gaps found</h2>
            @forelse ($programme['gaps'] as $gap)
                <div class="mt-3 border-t border-slate-100 pt-3 first:mt-2 first:border-0 first:pt-0 dark:border-slate-700">
                    <p class="text-sm text-slate-800 dark:text-slate-200">{{ $gap['question'] }}</p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Answer: <span class="font-semibold text-red-700 dark:text-red-300">{{ $gap['answer'] }}</span></p>
                </div>
            @empty
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">No major gaps were found.</p>
            @endforelse
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">To get ready to host a Dignity Bank</h2>
            @forelse ($programme['readiness_gaps'] as $gap)
                <div class="mt-3 border-t border-slate-100 pt-3 first:mt-2 first:border-0 first:pt-0 dark:border-slate-700">
                    <p class="text-sm text-slate-800 dark:text-slate-200">{{ $gap['question'] }}</p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Answer: <span class="font-semibold text-amber-700 dark:text-amber-300">{{ $gap['answer'] }}</span></p>
                </div>
            @empty
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Nothing further is needed to get ready.</p>
            @endforelse
        </div>
    </div>

    @if ($programme['strengths'])
        <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Working well</h2>
            <ul class="mt-2 space-y-1.5 text-sm text-slate-700 dark:text-slate-200">
                @foreach ($programme['strengths'] as $strength)
                    <li class="flex gap-2"><span class="text-emerald-600" aria-hidden="true">✓</span><span>{{ $strength['question'] }} <span class="text-slate-500 dark:text-slate-400">— {{ $strength['answer'] }}</span></span></li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($programme['context'])
        <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">About this {{ $programme['noun'] }}</h2>
            @foreach ($programme['context'] as $group)
                <h3 class="mt-4 text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $group['section'] }}</h3>
                <dl class="mt-1 divide-y divide-slate-100 text-sm dark:divide-slate-700">
                    @foreach ($group['items'] as $item)
                        <div class="flex flex-col gap-0.5 py-2 sm:flex-row sm:justify-between sm:gap-6">
                            <dt class="text-slate-600 dark:text-slate-300">{{ $item['question'] }}</dt>
                            <dd class="font-medium text-slate-900 sm:text-right dark:text-white">{{ $item['answer'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endforeach
        </div>
    @endif

    <p class="mb-8 text-xs text-slate-400 dark:text-slate-500">
        Each section is scored from 0 to 100 and the two headline scores are the plain average of their sections. A {{ $programme['noun'] }}
        is labelled ready only when its readiness score is {{ round($programme['thresholds']['readiness_ready_at_or_above']) }} or more and no must-have problem is found.
        Girls' needs count as high when the provision score is below {{ round($programme['thresholds']['provision_good_at_or_above']) }}.
    </p>

</x-app-layout>
