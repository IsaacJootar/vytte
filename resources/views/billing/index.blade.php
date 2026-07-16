<x-app-layout title="Billing & Plan">

    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto">

        <h1 class="text-2xl font-bold text-slate-900 mb-1">Billing & Plan</h1>
        <p class="text-sm text-slate-500 mb-6">Manage your workspace subscription.</p>

        @if (session('limit_error'))
            <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-800">
                {{ session('limit_error') }}
            </div>
        @endif

        @if (session('success'))
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        {{-- Current plan badge --}}
        <div class="mb-8 p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex-1">
                    <div class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Current plan</div>
                    <div class="text-xl font-bold text-slate-900">
                        {{ match($currentPlan) {
                            'PRO' => 'Pro',
                            'AGENCY' => 'Agency',
                            default => 'Free',
                        } }}
                    </div>
                </div>
                <span class="px-3 py-1 text-xs font-semibold rounded-full
                    {{ $currentPlan === 'AGENCY' ? 'bg-violet-100 text-violet-700' :
                       ($currentPlan === 'PRO' ? 'bg-vytte-100 text-vytte-700' : 'bg-slate-100 text-slate-600') }}">
                    {{ $currentPlan }}
                </span>
            </div>
        </div>

        {{-- Plan cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

            {{-- FREE --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5 flex flex-col
                {{ $currentPlan === 'FREE' ? 'ring-2 ring-vytte-500' : '' }}">
                <div class="text-sm font-semibold text-slate-600 mb-1">Free</div>
                <div class="text-2xl font-bold text-slate-900 mb-4">₦0 <span class="text-sm font-normal text-slate-400">/month</span></div>
                <ul class="text-sm text-slate-600 space-y-2 mb-6 flex-1">
                    <li class="flex items-center gap-2">
                        <span class="text-green-600">✓</span>
                        1 project
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-green-600">✓</span>
                        3 assessments per project
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-green-600">✓</span>
                        PDF & CSV export
                    </li>
                </ul>
                @if ($currentPlan === 'FREE')
                    <div class="text-center text-xs text-slate-400 font-medium py-2">Your current plan</div>
                @endif
            </div>

            {{-- PRO --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5 flex flex-col
                {{ $currentPlan === 'PRO' ? 'ring-2 ring-vytte-500' : '' }}">
                <div class="text-sm font-semibold text-vytte-600 mb-1">Pro</div>
                <div class="text-2xl font-bold text-slate-900 mb-4">₦5,000 <span class="text-sm font-normal text-slate-400">/month</span></div>
                <ul class="text-sm text-slate-600 space-y-2 mb-6 flex-1">
                    <li class="flex items-center gap-2">
                        <span class="text-green-600">✓</span>
                        10 projects
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-green-600">✓</span>
                        Unlimited assessments
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-green-600">✓</span>
                        PDF & CSV export
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-green-600">✓</span>
                        Shareable report links
                    </li>
                </ul>
                @if ($currentPlan === 'PRO')
                    <div class="text-center text-xs text-slate-400 font-medium py-2">Your current plan</div>
                @else
                    <button
                        onclick="payWithPaystack('PRO', 500000)"
                        class="w-full py-2 px-4 bg-vytte-600 hover:bg-vytte-700 text-white text-sm font-semibold rounded-xl transition">
                        Upgrade to Pro
                    </button>
                @endif
            </div>

            {{-- AGENCY --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5 flex flex-col
                {{ $currentPlan === 'AGENCY' ? 'ring-2 ring-vytte-500' : '' }}">
                <div class="text-sm font-semibold text-violet-600 mb-1">Agency</div>
                <div class="text-2xl font-bold text-slate-900 mb-4">₦15,000 <span class="text-sm font-normal text-slate-400">/month</span></div>
                <ul class="text-sm text-slate-600 space-y-2 mb-6 flex-1">
                    <li class="flex items-center gap-2">
                        <span class="text-green-600">✓</span>
                        Unlimited projects
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-green-600">✓</span>
                        Unlimited assessments
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-green-600">✓</span>
                        Team members
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-green-600">✓</span>
                        All export formats
                    </li>
                </ul>
                @if ($currentPlan === 'AGENCY')
                    <div class="text-center text-xs text-slate-400 font-medium py-2">Your current plan</div>
                @else
                    <button
                        onclick="payWithPaystack('AGENCY', 1500000)"
                        class="w-full py-2 px-4 bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-xl transition">
                        Upgrade to Agency
                    </button>
                @endif
            </div>

        </div>

        <p class="mt-6 text-xs text-slate-400 text-center">
            Payments processed securely by Paystack. NGN billing only.
        </p>

    </div>

    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
        function payWithPaystack(plan, amountKobo) {
            const handler = PaystackPop.setup({
                key: '{{ $paystackPublicKey }}',
                email: '{{ auth()->user()->email }}',
                amount: amountKobo,
                currency: 'NGN',
                metadata: {
                    workspace_id: '{{ $workspace->workspace_id }}',
                    plan: plan,
                },
                callback: function (response) {
                    window.location.href = '{{ route("billing.index") }}?paid=1';
                },
                onClose: function () {},
            });
            handler.openIframe();
        }
    </script>

</x-app-layout>
