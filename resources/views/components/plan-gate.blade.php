@props(['feature'])
@php
    use App\Services\PlanService;
    $workspace    = app('current.workspace');
    $allowed      = $workspace && PlanService::workspaceCanAccess($workspace, $feature);
    $currentPlan  = PlanService::normalizePlan($workspace?->plan ?? 'STARTER');

    if (! $allowed) {
        $requiredPlan = PlanService::requiredPlanForFeature($feature) ?? 'PROFESSIONAL';
        $featureLabel  = PlanService::FEATURES[$feature] ?? ucwords(str_replace('_', ' ', $feature));
        $requiredLabel = PlanService::planLabel($requiredPlan);
        $currentLabel  = PlanService::planLabel($currentPlan);
    }
@endphp

@if($allowed)
    {{ $slot }}
@else
    <div class="rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-700 p-6 flex flex-col items-center text-center gap-3">
        <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
            <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/>
            </svg>
        </div>
        <div>
            <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $featureLabel }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Available on the {{ $requiredLabel }} plan and above.
            </p>
        </div>
        <a href="{{ route('billing.index') }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 bg-vytte-700 text-white text-sm font-semibold rounded-lg hover:bg-vytte-800 transition-colors">
            Upgrade to {{ $requiredLabel }}
        </a>
        <p class="text-[11px] text-slate-400 dark:text-slate-500">You're on the {{ $currentLabel }} plan.</p>
    </div>
@endif
