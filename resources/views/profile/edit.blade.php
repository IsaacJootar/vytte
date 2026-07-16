<x-app-layout title="Settings">

    @php
        $isOwner = $currentMember?->role === 'OWNER';
        $isAdmin = in_array($currentMember?->role, ['OWNER', 'ADMIN']);
        $workspaceTimezone = $workspace?->settings['timezone'] ?? '';
    @endphp

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Settings</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Manage your profile and workspace.</p>
    </div>

    {{-- ===== PROFILE ===== --}}
    <div id="profile" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden mb-5">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Profile</h2>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Your name and email address.</p>
        </div>

        @if (session('status') === 'profile-updated')
            <div class="mx-5 mt-4 flex items-center gap-2 px-3 py-2.5 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-xs text-green-800 dark:text-green-300 font-medium">
                <svg class="w-3.5 h-3.5 flex-shrink-0 text-green-600 dark:text-green-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                </svg>
                Profile updated.
            </div>
        @endif

        <form method="POST" action="{{ route('profile.update') }}" class="px-5 py-4 space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label for="name" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Full name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}"
                       class="w-full sm:max-w-sm text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-vytte-500"
                       required>
                @error('name')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email address</label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}"
                       class="w-full sm:max-w-sm text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-vytte-500"
                       required>
                @error('email')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <button type="submit"
                        class="px-5 py-2 bg-vytte-700 text-white text-sm font-semibold rounded-lg hover:bg-vytte-800 transition-colors">
                    Save profile
                </button>
            </div>
        </form>
    </div>

    {{-- ===== PASSWORD ===== --}}
    <div id="password" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden mb-5">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Password</h2>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Use a strong password you don't use anywhere else.</p>
        </div>

        @if (session('status') === 'password-updated')
            <div class="mx-5 mt-4 flex items-center gap-2 px-3 py-2.5 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-xs text-green-800 dark:text-green-300 font-medium">
                <svg class="w-3.5 h-3.5 flex-shrink-0 text-green-600 dark:text-green-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                </svg>
                Password changed.
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" class="px-5 py-4 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="current_password" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Current password</label>
                <input type="password" id="current_password" name="current_password"
                       class="w-full sm:max-w-sm text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-vytte-500"
                       autocomplete="current-password">
                @error('current_password', 'updatePassword')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">New password</label>
                <input type="password" id="password" name="password"
                       class="w-full sm:max-w-sm text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-vytte-500"
                       autocomplete="new-password">
                @error('password', 'updatePassword')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Confirm new password</label>
                <input type="password" id="password_confirmation" name="password_confirmation"
                       class="w-full sm:max-w-sm text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-vytte-500"
                       autocomplete="new-password">
            </div>

            <div>
                <button type="submit"
                        class="px-5 py-2 bg-vytte-700 text-white text-sm font-semibold rounded-lg hover:bg-vytte-800 transition-colors">
                    Change password
                </button>
            </div>
        </form>
    </div>

    {{-- ===== WORKSPACE SETTINGS (OWNER / ADMIN only) ===== --}}
    @if ($workspace && $isAdmin)
        <div id="workspace" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden mb-5">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Workspace</h2>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Update your workspace name and time zone.</p>
            </div>

            @if (session('status') === 'workspace-updated')
                <div class="mx-5 mt-4 flex items-center gap-2 px-3 py-2.5 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-xs text-green-800 dark:text-green-300 font-medium">
                    <svg class="w-3.5 h-3.5 flex-shrink-0 text-green-600 dark:text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                    </svg>
                    Workspace updated.
                </div>
            @endif

            <form method="POST" action="{{ route('settings.workspace.update') }}" class="px-5 py-4 space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label for="workspace_name" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Workspace name</label>
                    <input type="text" id="workspace_name" name="name"
                           value="{{ old('name', $workspace->name) }}"
                           class="w-full sm:max-w-sm text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-vytte-500"
                           required>
                    @error('name')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="timezone" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Time zone</label>
                    <select id="timezone" name="timezone"
                            class="w-full sm:max-w-sm text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-vytte-500">
                        <option value="">— Select time zone —</option>
                        @foreach ($timezones as $tz)
                            <option value="{{ $tz }}" {{ $workspaceTimezone === $tz ? 'selected' : '' }}>
                                {{ $tz }}
                            </option>
                        @endforeach
                    </select>
                    @error('timezone')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <button type="submit"
                            class="px-5 py-2 bg-vytte-700 text-white text-sm font-semibold rounded-lg hover:bg-vytte-800 transition-colors">
                        Save workspace
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- ===== DANGER ZONE ===== --}}
    <div id="danger" class="bg-white dark:bg-slate-800 rounded-2xl border border-red-200 dark:border-red-900 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-red-100 dark:border-red-900">
            <h2 class="text-sm font-bold text-red-700 dark:text-red-400">Danger zone</h2>
            <p class="text-xs text-red-500 dark:text-red-500 mt-0.5">These actions cannot be undone.</p>
        </div>

        <div class="px-5 py-5 space-y-6">

            {{-- Delete workspace (OWNER only) --}}
            @if ($workspace && $isOwner)
                <div x-data="{ open: {{ $errors->workspaceDeletion->isNotEmpty() ? 'true' : 'false' }} }">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">Delete workspace</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Permanently deletes <strong>{{ $workspace->name }}</strong> and all its projects, assessments, and data.
                            </p>
                        </div>
                        <button @click="open = true"
                                class="flex-shrink-0 px-3 py-1.5 text-xs font-bold text-red-600 border border-red-300 dark:border-red-700 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                            Delete workspace
                        </button>
                    </div>

                    <div x-show="open" x-transition
                         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 dark:bg-black/60"
                         style="display: none">
                        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-md p-6" @click.stop>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-1">Delete {{ $workspace->name }}?</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
                                This will permanently delete all projects, assessments, scores, and team members.
                                Type the workspace name to confirm.
                            </p>

                            <form method="POST" action="{{ route('settings.workspace.destroy') }}">
                                @csrf
                                @method('DELETE')

                                <div class="mb-3">
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Type <strong>{{ $workspace->name }}</strong> to confirm
                                    </label>
                                    <input type="text" name="confirm_name"
                                           class="w-full text-sm border rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-red-500 {{ $errors->workspaceDeletion->has('confirm_name') ? 'border-red-400' : 'border-slate-200 dark:border-slate-600' }}"
                                           placeholder="{{ $workspace->name }}"
                                           autocomplete="off">
                                    @error('confirm_name', 'workspaceDeletion')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="mb-4">
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Your password</label>
                                    <input type="password" name="password"
                                           class="w-full text-sm border rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-red-500 {{ $errors->workspaceDeletion->has('password') ? 'border-red-400' : 'border-slate-200 dark:border-slate-600' }}"
                                           placeholder="Enter your password">
                                    @error('password', 'workspaceDeletion')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="flex gap-3 justify-end">
                                    <button type="button" @click="open = false"
                                            class="px-4 py-2 text-sm font-semibold text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            class="px-4 py-2 text-sm font-bold text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                                        Delete workspace
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Delete account --}}
            <div x-data="{ open: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }} }">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">Delete account</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Permanently deletes your Vytte account and personal data.</p>
                    </div>
                    <button @click="open = true"
                            class="flex-shrink-0 px-3 py-1.5 text-xs font-bold text-red-600 border border-red-300 dark:border-red-700 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                        Delete account
                    </button>
                </div>

                <div x-show="open" x-transition
                     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 dark:bg-black/60"
                     style="display: none">
                    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-md p-6" @click.stop>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-1">Delete your account?</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
                            This permanently deletes your Vytte account. Enter your password to confirm.
                        </p>

                        <form method="POST" action="{{ route('profile.destroy') }}">
                            @csrf
                            @method('DELETE')

                            <div class="mb-4">
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Your password</label>
                                <input type="password" name="password"
                                       class="w-full text-sm border rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-red-500 {{ $errors->userDeletion->has('password') ? 'border-red-400' : 'border-slate-200 dark:border-slate-600' }}"
                                       placeholder="Enter your password">
                                @error('password', 'userDeletion')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex gap-3 justify-end">
                                <button type="button" @click="open = false"
                                        class="px-4 py-2 text-sm font-semibold text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit"
                                        class="px-4 py-2 text-sm font-bold text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                                    Delete account
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

</x-app-layout>
