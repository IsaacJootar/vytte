<x-admin-layout title="Platform Settings">

    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900">Platform Settings</h1>
        <p class="text-sm text-slate-500 mt-0.5">Toggle platform-wide features. Changes take effect immediately.</p>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 p-5 max-w-xl">
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            @method('PUT')

            {{-- Email notifications toggle --}}
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-slate-900">Email Notifications</p>
                    <p class="text-xs text-slate-400 mt-0.5 max-w-xs">
                        When ON, emails are sent for assessment completions and member invitations. When OFF, no emails are sent — this is the default for development.
                    </p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-0.5" x-data>
                    <input type="checkbox" name="email_notifications_enabled" value="1"
                           @checked($emailEnabled)
                           class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-vytte-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-vytte-700"></div>
                </label>
            </div>

            <div class="mt-5 pt-5 border-t border-slate-100 flex items-center gap-3">
                <button type="submit"
                        class="px-4 py-2 text-sm font-semibold bg-vytte-700 text-white rounded-lg hover:bg-vytte-800 transition-colors">
                    Save settings
                </button>
                <p class="text-xs text-slate-400">
                    Current: Email is <strong>{{ $emailEnabled ? 'ON' : 'OFF' }}</strong>
                </p>
            </div>
        </form>
    </div>

</x-admin-layout>
