@props([
    'risks' => [], // [['subject' => 'Governance', 'likelihood' => 'HIGH', 'impact' => 'HIGH', 'level' => 'HIGH'], ...]
])

@php
    $order = ['LOW' => 0, 'MEDIUM' => 1, 'HIGH' => 2];
    // Grid cell colour by combined risk level (impact row, likelihood col).
    $cellLevel = function ($impactIdx, $likeIdx) {
        $sum = $impactIdx + $likeIdx;
        return $sum >= 3 ? 'HIGH' : ($sum >= 2 ? 'MEDIUM' : 'LOW');
    };
    $bg = ['HIGH' => 'bg-red-100 dark:bg-red-900/30', 'MEDIUM' => 'bg-amber-100 dark:bg-amber-900/25', 'LOW' => 'bg-green-100 dark:bg-green-900/25'];
    // Bucket risks into their cell.
    $grid = [];
    foreach ($risks as $risk) {
        $ii = $order[$risk['impact'] ?? 'MEDIUM'] ?? 1;
        $li = $order[$risk['likelihood'] ?? 'MEDIUM'] ?? 1;
        $grid[$ii][$li][] = $risk['subject'] ?? '';
    }
@endphp

<div class="inline-block">
    <div class="flex">
        <div class="flex flex-col justify-center pr-1">
            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500" style="writing-mode: vertical-rl; transform: rotate(180deg);">Impact →</span>
        </div>
        <div>
            <div class="grid grid-cols-3 gap-1">
                @foreach ([2, 1, 0] as $impactIdx)
                    @foreach ([0, 1, 2] as $likeIdx)
                        @php $lvl = $cellLevel($impactIdx, $likeIdx); $items = $grid[$impactIdx][$likeIdx] ?? []; @endphp
                        <div class="w-24 h-16 rounded-lg {{ $bg[$lvl] }} border border-slate-200/50 dark:border-slate-700/50 p-1 overflow-hidden">
                            @foreach (array_slice($items, 0, 3) as $subject)
                                <p class="text-[9px] leading-tight text-slate-700 dark:text-slate-200 truncate">{{ $subject }}</p>
                            @endforeach
                        </div>
                    @endforeach
                @endforeach
            </div>
            <div class="text-center mt-1">
                <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Likelihood →</span>
            </div>
        </div>
    </div>
</div>
