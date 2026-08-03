@props(['views', 'showIssues' => false])

@php
    $primary = $views['primary'] ?? [];
    $core = $views['common_core'] ?? [];
    $context = $views['context'] ?? [];
    $qualitative = $views['qualitative'] ?? [];
    $coverage = $views['response_coverage'] ?? [];
@endphp

<section class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800 print-break-avoid">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">What this report measures</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Different result types stay together without pretending they mean the same thing.</p>
        </div>
        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">One report · explicit purposes</span>
    </div>

    <div class="mt-4 grid gap-3 {{ ($core['calibration_state'] ?? 'NOT_DEFINED') !== 'NOT_DEFINED' ? 'sm:grid-cols-2' : '' }}">
        <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <summary class="cursor-pointer text-xs font-bold text-slate-800 dark:text-slate-100">How the primary result was calculated</summary>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $primary['construct'] ?? 'Readiness' }} · {{ $primary['source_items'] ?? 0 }} scored items · {{ $primary['formula'] ?? '' }}</p>
            <p class="mt-2 text-xs {{ ($primary['comparison_eligibility']['eligible'] ?? false) ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}">{{ $primary['comparison_eligibility']['reason'] ?? 'Comparison eligibility is not defined.' }}</p>
        </details>
        @if (($core['calibration_state'] ?? 'NOT_DEFINED') !== 'NOT_DEFINED')
            <div class="rounded-xl border border-vytte-200 bg-vytte-50 p-4 dark:border-vytte-800 dark:bg-vytte-950/30">
                <div class="flex items-end justify-between gap-3">
                    <div><p class="text-[10px] font-bold uppercase tracking-wide text-vytte-700 dark:text-vytte-400">Common-core result</p><p class="mt-1 text-2xl font-black text-slate-900 dark:text-white">{{ $core['value'] !== null ? number_format((float) $core['value'], 1) : '—' }}</p></div>
                    <span class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">{{ str($core['calibration_state'])->replace('_', ' ')->title() }}</span>
                </div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">A separate benchmark view, shown only because this instrument declares governed shared anchors.</p>
            </div>
        @endif
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        @foreach ([[$context, 'Context and needs'], [$qualitative, 'Qualitative findings']] as [$group, $fallback])
            <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-900/40">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-xs font-bold text-slate-900 dark:text-white">{{ $group['label'] ?? $fallback }}</h3>
                    <span class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">Not scored</span>
                </div>
                @forelse ($group['items'] ?? [] as $item)
                    <div class="mt-3 border-t border-slate-200 pt-3 text-xs dark:border-slate-700">
                        <p class="font-semibold text-slate-700 dark:text-slate-200">{{ $item['question_text'] }}</p>
                        <p class="mt-1 whitespace-pre-line text-slate-600 dark:text-slate-300">{{ $item['answer'] ?: str($item['response_state'])->replace('_', ' ')->title() }}</p>
                        @if (! empty($item['evidence_note']))<p class="mt-1 text-slate-500 dark:text-slate-400">Evidence note: {{ $item['evidence_note'] }}</p>@endif
                    </div>
                @empty
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ ($coverage['aggregate_privacy_protected'] ?? false) ? 'Individual answers are protected in this aggregate report.' : 'No recorded answers in this view.' }}</p>
                @endforelse
            </div>
        @endforeach
    </div>

    <div class="mt-4 rounded-xl border border-slate-200 px-4 py-3 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
        <p class="font-bold text-slate-800 dark:text-slate-100">Coverage and limitations</p>
        <p class="mt-1">Expected items: {{ $coverage['expected_items'] ?? '—' }}@if (($coverage['recorded_items'] ?? null) !== null) · Recorded: {{ $coverage['recorded_items'] }} · Missing: {{ $coverage['missing_items'] }}@endif</p>
        @if (($coverage['recorded_items'] ?? null) !== null && ($coverage['expected_items'] ?? 0) > 0)
            @php($coveragePercent = min(100, round(($coverage['recorded_items'] / $coverage['expected_items']) * 100)))
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700" role="img" aria-label="{{ $coveragePercent }} percent response coverage">
                <div class="h-full rounded-full bg-vytte-600" style="width: {{ $coveragePercent }}%"></div>
            </div>
        @endif
        @foreach ($views['limitations'] ?? [] as $limitation)
            <p class="mt-1">• {{ $limitation }}</p>
        @endforeach
    </div>

    @if ($showIssues && ! empty($views['issue_register']))
        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30">
            <h3 class="text-xs font-bold text-red-900 dark:text-red-100">Exact issues recorded</h3>
            <p class="mt-1 text-xs text-red-700 dark:text-red-300">These are the specific assessed items behind the pain points, retained with stable keys for future progress checks.</p>
            @foreach ($views['issue_register'] as $issue)
                <div class="mt-3 border-t border-red-200 pt-3 text-xs dark:border-red-900">
                    <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $issue['question_text'] }}</p>
                    <p class="mt-1 text-slate-600 dark:text-slate-300">@if ($issue['recorded_answer'])Recorded answer: {{ $issue['recorded_answer'] }} · @endif Score: {{ number_format((float) $issue['item_score'], 0) }}/100</p>
                    @if (! empty($issue['evidence_note']))<p class="mt-1 text-slate-500 dark:text-slate-400">Evidence note: {{ $issue['evidence_note'] }}</p>@endif
                </div>
            @endforeach
        </div>
    @endif
</section>
