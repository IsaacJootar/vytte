<x-admin-layout title="Plan Features">

    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Plan Management</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Control beta licensing, plan metadata, limits, and feature access. Payments are intentionally not connected.</p>
        </div>
    </div>

    {{-- What the plans are carrying. The screen could set limits but never showed usage
         against them, so there was no way to see who was near a ceiling. --}}
    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card tone="blue" label="Workspaces" :value="$totals['workspaces']" sub="On a plan" />
        <x-stat-card tone="slate" label="People" :value="$totals['people']" sub="Across all workspaces" />
        <x-stat-card tone="strong" label="Projects" :value="$totals['projects']" sub="Created by customers" />
        <x-stat-card tone="blue" label="Assessments" :value="$totals['assessments']" sub="Run by customers" />
    </div>

    @if (count($nearLimit) > 0)
        <section class="mb-4" aria-labelledby="near-limit-heading">
            <h2 id="near-limit-heading" class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Close to a limit
            </h2>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($nearLimit as $flag)
                    <div class="rounded-2xl border p-4 {{ $flag['over']
                        ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950'
                        : 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950' }}">
                        <a href="{{ route('admin.workspaces.show', $flag['workspace']) }}" class="text-sm font-bold text-slate-900 hover:underline dark:text-white">
                            {{ $flag['workspace']->name }}
                        </a>
                        <p class="mt-1 text-sm text-slate-700 dark:text-slate-200">
                            {{ $flag['over'] ? 'Over the' : 'Close to the' }} {{ strtolower($flag['limit']) }} limit —
                            using {{ $flag['used'] }} of {{ $flag['allowed'] }}.
                        </p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mb-5 section-card" aria-labelledby="allocation-heading">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-700">
            <h2 id="allocation-heading" class="text-sm font-bold text-slate-900 dark:text-white">Who is on each plan</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">Plan</th>
                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">Workspaces</th>
                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">People</th>
                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">Projects</th>
                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">Limits</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($usage as $row)
                        <tr>
                            <td class="px-5 py-3">
                                <p class="font-semibold text-slate-900 dark:text-white">{{ $row['plan']->public_label ?: $row['plan']->plan_name }}</p>
                                @unless ($row['plan']->is_active)
                                    <p class="mt-0.5 text-xs text-slate-400">Not offered</p>
                                @endunless
                            </td>
                            <td class="px-5 py-3 tabular-nums text-slate-600 dark:text-slate-300">
                                {{ $row['workspace_count'] }}
                                <p class="text-xs text-slate-400">{{ $row['active_count'] }} active</p>
                            </td>
                            <td class="px-5 py-3 tabular-nums text-slate-600 dark:text-slate-300">{{ $row['people_count'] }}</td>
                            <td class="px-5 py-3 tabular-nums text-slate-600 dark:text-slate-300">{{ $row['project_count'] }}</td>
                            <td class="px-5 py-3">
                                <ul class="space-y-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    @foreach ($row['limits'] as $limit)
                                        <li>{{ $limit['label'] }}: <span class="font-medium text-slate-700 dark:text-slate-200">{{ $limit['value'] }}</span></li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <form method="POST" action="{{ route('admin.plan-features.update') }}">
        @csrf
        @method('PUT')

        <div class="mb-5 grid gap-4 lg:grid-cols-3">
            @foreach ($plans as $plan)
                <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ $plan->plan_name }}</h2>
                    <input name="plans[{{ $plan->plan_code }}][public_label]" value="{{ $plan->public_label }}" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                    <textarea name="plans[{{ $plan->plan_code }}][description]" rows="2" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ $plan->description }}</textarea>
                    <input name="plans[{{ $plan->plan_code }}][display_order]" type="number" value="{{ $plan->display_order }}" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                    <textarea name="plans[{{ $plan->plan_code }}][limits_json]" rows="6" class="mt-3 w-full rounded-xl border-slate-300 font-mono text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ json_encode($plan->limits, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</textarea>
                    <label class="mt-3 flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300"><input type="checkbox" name="plans[{{ $plan->plan_code }}][is_active]" value="1" @checked($plan->is_active)> Active</label>
                    <label class="mt-2 flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300"><input type="checkbox" name="plans[{{ $plan->plan_code }}][is_beta_unlocked]" value="1" @checked($plan->is_beta_unlocked)> Beta unlocked</label>
                </div>
            @endforeach
        </div>

        <div class="section-card">
            <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-700 grid gap-4" style="grid-template-columns: 1fr repeat({{ $plans->count() }}, 120px);">
                <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Feature</span>
                @foreach ($plans as $plan)
                    <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide text-center">{{ $plan->public_label }}</span>
                @endforeach
            </div>

            @foreach ($features as $featureKey => $featureLabel)
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 last:border-0 grid gap-4 items-center" style="grid-template-columns: 1fr repeat({{ $plans->count() }}, 120px);">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $featureLabel }}</span>
                    @foreach ($plans as $plan)
                        <div class="flex justify-center">
                            <input
                                type="checkbox"
                                name="features[{{ $plan->plan_code }}][{{ $featureKey }}]"
                                value="1"
                                {{ $matrix[$featureKey][$plan->plan_code] ? 'checked' : '' }}
                                class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-vytte-600 focus:ring-vytte-500 focus:ring-offset-0 cursor-pointer"
                            >
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="mt-5 flex items-center justify-between">
            <p class="text-xs text-slate-400 dark:text-slate-500">
                Beta launch seeds all plans with identical access; future limits can be changed here without adding payment code.
            </p>
            <button type="submit" class="px-5 py-2 bg-vytte-700 hover:bg-vytte-800 text-white text-sm font-semibold rounded-xl transition-colors">
                Save plan configuration
            </button>
        </div>
    </form>

</x-admin-layout>
