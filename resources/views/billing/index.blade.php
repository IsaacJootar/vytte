<x-app-layout title="Plans">

    <div class="max-w-5xl">

        <h1 class="text-xl font-bold text-slate-900 dark:text-white mb-0.5 tracking-tight">Plans</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">
            Public beta access is temporarily unlocked for all plans. Payments and billing will be connected later.
        </p>

        @if (session('limit_error'))
            <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl text-sm text-amber-800 dark:text-amber-300">
                {{ session('limit_error') }}
            </div>
        @endif

        <div class="mb-8 p-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex-1">
                    <div class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-1">Current beta plan</div>
                    <div class="text-xl font-bold text-slate-900 dark:text-white">
                        {{ \App\Services\PlanService::planLabel($currentPlan) }}
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">All beta plans currently receive the same unlocked access through plan configuration.</p>
                </div>
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-vytte-100 text-vytte-700 dark:bg-vytte-900/40 dark:text-vytte-300">
                    Beta unlocked
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach ($plans as $plan)
                @php
                    $isCurrent = $currentPlan === $plan->plan_code;
                    $limits = $plan->limits ?? [];
                @endphp
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 flex flex-col {{ $isCurrent ? 'ring-2 ring-vytte-500' : '' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-vytte-700 dark:text-vytte-300">{{ $plan->public_label }}</div>
                            <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Beta access</div>
                        </div>
                        @if ($plan->is_beta_unlocked)
                            <span class="rounded-full bg-green-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-green-700 dark:bg-green-900/30 dark:text-green-300">Unlocked</span>
                        @endif
                    </div>
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ $plan->description }}</p>
                    <ul class="text-sm text-slate-600 dark:text-slate-300 space-y-2 my-6 flex-1">
                        <li class="flex items-center gap-2"><span class="text-green-600 dark:text-green-400">✓</span>{{ $limits['projects'] ?? null ? $limits['projects'].' projects' : 'Unlimited beta projects' }}</li>
                        <li class="flex items-center gap-2"><span class="text-green-600 dark:text-green-400">✓</span>{{ $limits['assessments_per_project'] ?? null ? $limits['assessments_per_project'].' assessments per project' : 'Unlimited beta assessments' }}</li>
                        <li class="flex items-center gap-2"><span class="text-green-600 dark:text-green-400">✓</span>Reports, exports, sharing, respondents, and team features</li>
                    </ul>
                    <div class="rounded-xl bg-slate-50 p-3 text-xs text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                        Billing is intentionally disabled for beta. This plan can later receive pricing, limits, and feature differences from Platform Admin configuration.
                    </div>
                    @if ($isCurrent)
                        <div class="mt-4 text-center text-xs text-slate-400 dark:text-slate-500 font-medium">Your current plan</div>
                    @endif
                </div>
            @endforeach
        </div>

    </div>

</x-app-layout>
