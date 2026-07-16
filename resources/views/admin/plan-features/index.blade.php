<x-admin-layout title="Plan Features">

    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Plan Features</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Control which features are available on each plan. Changes take effect immediately — no deploy required.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.plan-features.update') }}">
        @csrf
        @method('PUT')

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">

            {{-- Header row --}}
            <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-700 grid gap-4" style="grid-template-columns: 1fr repeat({{ count($plans) }}, 90px);">
                <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Feature</span>
                @foreach ($plans as $plan)
                    <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide text-center">{{ ucfirst(strtolower($plan)) }}</span>
                @endforeach
            </div>

            {{-- Feature rows --}}
            @foreach ($features as $featureKey => $featureLabel)
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 last:border-0 grid gap-4 items-center" style="grid-template-columns: 1fr repeat({{ count($plans) }}, 90px);">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $featureLabel }}</span>
                    @foreach ($plans as $plan)
                        <div class="flex justify-center">
                            <input
                                type="checkbox"
                                name="features[{{ $plan }}][{{ $featureKey }}]"
                                value="1"
                                {{ $matrix[$featureKey][$plan] ? 'checked' : '' }}
                                class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-vytte-600 focus:ring-vytte-500 focus:ring-offset-0 cursor-pointer"
                            >
                        </div>
                    @endforeach
                </div>
            @endforeach

        </div>

        <div class="mt-5 flex items-center justify-between">
            <p class="text-xs text-slate-400 dark:text-slate-500">
                Unchecked = blocked for that plan. The Free plan should typically have everything unchecked.
            </p>
            <button type="submit"
                class="px-5 py-2 bg-vytte-700 hover:bg-vytte-800 text-white text-sm font-semibold rounded-xl transition-colors">
                Save changes
            </button>
        </div>
    </form>

</x-admin-layout>
