<x-app-layout :title="'Compare · ' . $project->name">

    {{-- Back + header --}}
    <div class="mb-6">
        <a href="{{ route('projects.progress', $project) }}"
           class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors mb-2">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 010 1.06L8.06 10l3.72 3.72a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd"/>
            </svg>
            Progress
        </a>
        <p class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide">Assessment Comparison</p>
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight mt-0.5">{{ $project->name }}</h1>
    </div>

    {{-- Side-by-side header cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
        @foreach ([['A', $assessmentA, $domainsA], ['B', $assessmentB, $domainsB]] as [$label, $assessment, $domains])
            @php
                $overallScore = $assessment->score?->overall_score !== null ? (float) $assessment->score->overall_score : null;
                $maturity = $assessment->score?->maturityLevel;
                $module = $assessment->moduleScope->first()?->module;
                $band = match (true) {
                    $overallScore === null => 'uncalibrated',
                    $overallScore >= 70.0  => 'strong',
                    $overallScore >= 45.0  => 'moderate',
                    default                => 'weak',
                };
                $bandClass = match ($band) {
                    'strong'   => 'text-emerald-700 dark:text-emerald-400',
                    'moderate' => 'text-amber-700 dark:text-amber-400',
                    'weak'     => 'text-red-700 dark:text-red-400',
                    default    => 'text-slate-400',
                };
            @endphp
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-vytte-100 dark:bg-vytte-900/40 text-[11px] font-bold text-vytte-700 dark:text-vytte-400">{{ $label }}</span>
                    <a href="{{ route('assessments.results', $assessment) }}"
                       class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:text-vytte-900 dark:hover:text-vytte-200 transition-colors">
                        View results →
                    </a>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">{{ $assessment->completed_at?->format('d M Y') }}</p>
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">{{ $module?->module_name ?? '—' }}</p>
                <div class="flex items-end gap-3">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-0.5">Score</p>
                        <p class="text-2xl font-bold text-slate-900 dark:text-white tabular-nums">
                            {{ $overallScore !== null ? number_format($overallScore, 1) : '—' }}
                        </p>
                    </div>
                    @if ($maturity)
                        <div class="mb-1">
                            <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-0.5">Maturity</p>
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
                                <span class="text-vytte-700 dark:text-vytte-400 font-bold">L{{ $maturity->level_number }}</span>
                                {{ $maturity->level_name }}
                            </p>
                        </div>
                    @endif
                    @if ($overallScore !== null)
                        <div class="mb-1">
                            <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-0.5">Band</p>
                            <p class="text-sm font-semibold capitalize {{ $bandClass }}">{{ $band }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Domain profile overlay: A (dashed) vs B (solid) --}}
    @php
        $radarA = collect($allDomains)->filter(fn ($d) => isset($domainsA[$d->domain_id]))
            ->map(fn ($d) => ['label' => $d->domain_code, 'value' => (float) $domainsA[$d->domain_id]])->values()->all();
        $radarB = collect($allDomains)->filter(fn ($d) => isset($domainsB[$d->domain_id]))
            ->map(fn ($d) => ['label' => $d->domain_code, 'value' => (float) $domainsB[$d->domain_id]])->values()->all();
    @endphp
    @if (count($radarB) >= 3)
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 mb-5 flex flex-col items-center">
            <div class="self-start flex items-center gap-3 mb-2">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Domain profile</h2>
                <span class="text-[11px] text-slate-400 dark:text-slate-500">B solid · A dashed</span>
            </div>
            <x-viz.radar :series="$radarB" :compare="$radarA" />
        </div>
    @endif

    {{-- Domain delta table --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Domain Comparison</h2>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Change from A to B per domain. Positive = improvement.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-700">
                        <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Domain</th>
                        <th class="px-5 py-2.5 text-center text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">A</th>
                        <th class="px-5 py-2.5 text-center text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Change</th>
                        <th class="px-5 py-2.5 text-center text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">B</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($allDomains as $domain)
                        @php
                            $scoreA = isset($domainsA[$domain->domain_id]) ? (float) $domainsA[$domain->domain_id] : null;
                            $scoreB = isset($domainsB[$domain->domain_id]) ? (float) $domainsB[$domain->domain_id] : null;
                            $delta = ($scoreA !== null && $scoreB !== null) ? ($scoreB - $scoreA) : null;

                            $bandClass = fn($s) => match (true) {
                                $s === null    => 'bg-slate-50 dark:bg-slate-700/50 text-slate-400 dark:text-slate-500',
                                $s >= 70       => 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-800 dark:text-emerald-300',
                                $s >= 45       => 'bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-300',
                                default        => 'bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-300',
                            };
                        @endphp
                        <tr>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded flex-shrink-0">{{ $domain->domain_code }}</span>
                                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $domain->domain_name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="inline-flex items-center justify-center min-w-[2.5rem] px-2 py-0.5 rounded text-xs font-bold tabular-nums {{ $bandClass($scoreA) }}">
                                    {{ $scoreA !== null ? number_format($scoreA, 0) : '—' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                @if ($delta !== null)
                                    @php
                                        $absD = abs($delta);
                                        $rounded = round($delta, 1);
                                    @endphp
                                    @if ($delta > 0)
                                        <span class="inline-flex items-center gap-0.5 text-xs font-bold text-emerald-700 dark:text-emerald-400 tabular-nums">
                                            <svg class="w-3 h-3 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M10 17a.75.75 0 01-.75-.75V5.612L5.29 9.77a.75.75 0 01-1.08-1.04l5.25-5.5a.75.75 0 011.08 0l5.25 5.5a.75.75 0 11-1.08 1.04L10.75 5.612V16.25A.75.75 0 0110 17z" clip-rule="evenodd"/>
                                            </svg>
                                            +{{ number_format($rounded, 1) }}
                                        </span>
                                    @elseif ($delta < 0)
                                        <span class="inline-flex items-center gap-0.5 text-xs font-bold text-red-700 dark:text-red-400 tabular-nums">
                                            <svg class="w-3 h-3 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M10 3a.75.75 0 01.75.75v10.638l3.96-4.158a.75.75 0 111.08 1.04l-5.25 5.5a.75.75 0 01-1.08 0l-5.25-5.5a.75.75 0 111.08-1.04l3.96 4.158V3.75A.75.75 0 0110 3z" clip-rule="evenodd"/>
                                            </svg>
                                            {{ number_format($rounded, 1) }}
                                        </span>
                                    @else
                                        <span class="text-xs font-semibold text-slate-400 dark:text-slate-500 tabular-nums">0.0</span>
                                    @endif
                                @else
                                    <span class="text-xs text-slate-300 dark:text-slate-600">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="inline-flex items-center justify-center min-w-[2.5rem] px-2 py-0.5 rounded text-xs font-bold tabular-nums {{ $bandClass($scoreB) }}">
                                    {{ $scoreB !== null ? number_format($scoreB, 0) : '—' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Run a new comparison link --}}
    <div class="mt-4 text-center">
        <a href="{{ route('projects.progress', $project) }}"
           class="text-sm font-semibold text-vytte-700 dark:text-vytte-400 hover:text-vytte-900 dark:hover:text-vytte-200 transition-colors">
            ← Change selection
        </a>
    </div>

</x-app-layout>
