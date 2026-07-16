<x-admin-layout title="Platform Settings">

    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Platform Settings</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Changes take effect immediately for all workspaces.</p>
    </div>

    @if (session('success'))
        <div class="mb-5 px-4 py-3 rounded-xl text-sm font-medium bg-green-50 border border-green-200 text-green-800 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="space-y-5 max-w-2xl">

            {{-- Email --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-4">Email</h2>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Email Notifications</p>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5 max-w-sm">
                            When ON, emails are sent for assessment completions and member invitations. Default is OFF for development.
                        </p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-0.5">
                        <input type="checkbox" name="email_notifications_enabled" value="1"
                               @checked($emailEnabled)
                               class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 dark:bg-slate-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-vytte-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-vytte-700"></div>
                    </label>
                </div>
            </div>

            {{-- Sharing --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-4">Shared Reports</h2>
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Share link expiry (days)</p>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">How long a shared assessment link stays valid. Default is 30 days.</p>
                    </div>
                    <input type="number" name="link_expiry_days" value="{{ $linkExpiryDays }}" min="1" max="365"
                           class="w-24 px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-vytte-500 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 text-center">
                </div>
            </div>

            {{-- Payments --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Payment Gateways</h2>
                <p class="text-xs text-slate-400 dark:text-slate-500 mb-4">Enable or disable which gateways workspace owners can pay with.</p>
                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Paystack</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">NGN, GHS, KES, ZAR — primary gateway</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                            <input type="checkbox" name="paystack_enabled" value="1"
                                   @checked($paystackEnabled)
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 dark:bg-slate-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-vytte-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-vytte-700"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between gap-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                        <div>
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Flutterwave</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Pan-African — 30+ currencies</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                            <input type="checkbox" name="flutterwave_enabled" value="1"
                                   @checked($flutterwaveEnabled)
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 dark:bg-slate-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-vytte-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-vytte-700"></div>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Plan Limits --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Plan Limits</h2>
                <p class="text-xs text-slate-400 dark:text-slate-500 mb-4">Change limits without a code deploy. Agency plan is always unlimited.</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Free — max projects</label>
                        <input type="number" name="free_plan_projects" value="{{ $freePlanProjects }}" min="1" max="100"
                               class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-vytte-500 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Free — assessments per project</label>
                        <input type="number" name="free_plan_assessments" value="{{ $freePlanAssessments }}" min="1" max="100"
                               class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-vytte-500 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Pro — max projects</label>
                        <input type="number" name="pro_plan_projects" value="{{ $proPlanProjects }}" min="1" max="1000"
                               class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-vytte-500 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100">
                    </div>
                </div>
            </div>

            <div>
                <button type="submit"
                        class="px-4 py-2 text-sm font-semibold bg-vytte-700 text-white rounded-lg hover:bg-vytte-800 transition-colors">
                    Save all settings
                </button>
            </div>

        </div>
    </form>

</x-admin-layout>
