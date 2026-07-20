<x-admin-layout :title="$user->name">
    <div class="mb-5">
        <a href="{{ route('admin.platform-users.index') }}" class="link-nav text-sm">&larr; All people</a>
        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ $user->name }}</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ $user->email }} · Joined {{ $user->created_at?->format('j M Y') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($user->isPlatformAdmin())
                    <span class="inline-flex rounded-full bg-vytte-100 px-3 py-1.5 text-sm font-semibold text-vytte-800 dark:bg-vytte-900/40 dark:text-vytte-200">Platform admin</span>
                @endif
                @if ($user->isSuspended())
                    <span class="inline-flex rounded-full bg-amber-100 px-3 py-1.5 text-sm font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Suspended</span>
                @else
                    <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1.5 text-sm font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">Active</span>
                @endif
            </div>
        </div>
    </div>

    @if ($user->isSuspended())
        <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950">
            <p class="text-sm font-bold text-amber-900 dark:text-amber-100">
                This account was suspended {{ $user->suspended_at?->diffForHumans() }}
            </p>
            <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">
                Reason given: {{ $user->suspension_reason ?: 'None recorded.' }}
            </p>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <section class="section-card p-5" aria-labelledby="workspaces-heading">
                <h2 id="workspaces-heading" class="text-sm font-bold text-slate-900 dark:text-white">Workspaces</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Where this person works, and what they can do there.</p>
                <ul class="mt-3 space-y-2">
                    @forelse ($user->workspaceMemberships as $membership)
                        <li>
                            <a href="{{ route('admin.workspaces.show', $membership->workspace_id) }}"
                               class="nav-card group flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-900">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">
                                        {{ $membership->workspace?->name ?? 'Unknown workspace' }}
                                    </span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                                        {{ ucfirst(strtolower($membership->role)) }}
                                        @if ($membership->workspace)
                                            · {{ ucfirst(strtolower($membership->workspace->status)) }}
                                        @endif
                                    </span>
                                </span>
                                <span class="text-vytte-700 transition-transform group-hover:translate-x-0.5 dark:text-vytte-300" aria-hidden="true">&rarr;</span>
                            </a>
                        </li>
                    @empty
                        <li class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                            This person is not a member of any workspace.
                        </li>
                    @endforelse
                </ul>
            </section>

            <section class="section-card p-5" aria-labelledby="history-heading">
                <h2 id="history-heading" class="text-sm font-bold text-slate-900 dark:text-white">History</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">What this person did, and what was done to their account.</p>
                <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse ($history as $entry)
                        <li class="py-2.5 text-sm">
                            <p class="text-slate-800 dark:text-slate-100">{{ \App\Support\AuditEventLabel::for($entry->event) }}</p>
                            <p class="mt-0.5 text-xs text-slate-400">
                                {{ $entry->created_at?->format('j M Y, H:i') }}
                                @if ($entry->ip_address)
                                    · from {{ $entry->ip_address }}
                                @endif
                            </p>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">Nothing recorded for this account yet.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <div class="space-y-4">
            <section class="section-card p-5" aria-labelledby="access-heading">
                <h2 id="access-heading" class="text-sm font-bold text-slate-900 dark:text-white">Access</h2>

                <form method="POST" action="{{ route('admin.platform-users.role', $user) }}" class="mt-3">
                    @csrf @method('PATCH')
                    <input type="hidden" name="platform_role" value="{{ $user->isPlatformAdmin() ? '' : 'PLATFORM_ADMIN' }}">
                    <button class="btn-secondary w-full" data-loading-label="Updating…">
                        {{ $user->isPlatformAdmin() ? 'Remove platform admin access' : 'Make platform admin' }}
                    </button>
                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                        Platform admins govern Vytte itself — the question library, publishing, and every workspace.
                        They are not customers and do not run assessments.
                    </p>
                </form>
            </section>

            <section class="section-card p-5" aria-labelledby="signin-heading">
                <h2 id="signin-heading" class="text-sm font-bold text-slate-900 dark:text-white">Sign-in</h2>

                @if ($user->isSuspended())
                    <form method="POST" action="{{ route('admin.platform-users.reactivate', $user) }}" class="mt-3">
                        @csrf @method('PATCH')
                        <button class="btn-primary w-full" data-loading-label="Restoring…">Restore access</button>
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">This person will be able to sign in again immediately.</p>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.platform-users.suspend', $user) }}" class="mt-3 space-y-3"
                          onsubmit="return confirm('Suspend this account? They will not be able to sign in. Nothing will be deleted.')">
                        @csrf @method('PATCH')
                        <x-form-field label="Why are you suspending this account?" name="suspension_reason"
                                      hint="Shown to the person when they try to sign in, and kept on the record.">
                            <input id="suspension_reason" name="suspension_reason" value="{{ old('suspension_reason') }}"
                                   maxlength="255" required
                                   class="w-full rounded-xl text-sm dark:bg-slate-900 dark:text-white">
                        </x-form-field>
                        <button class="btn-danger w-full" data-loading-label="Suspending…">Suspend account</button>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Blocks sign-in only. Their workspaces, assessments and history all stay exactly as they are.
                        </p>
                    </form>
                @endif
            </section>

            <section class="section-card p-5" aria-labelledby="invites-heading">
                <h2 id="invites-heading" class="text-sm font-bold text-slate-900 dark:text-white">Invitations</h2>
                <ul class="mt-3 space-y-2.5">
                    @forelse ($invitations as $invitation)
                        <li class="text-xs">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ $invitation->workspace?->name ?? 'Unknown workspace' }}</p>
                            <p class="text-slate-400">
                                @if ($invitation->isAccepted())
                                    Accepted {{ $invitation->accepted_at?->diffForHumans() }}
                                @elseif ($invitation->isExpired())
                                    Expired {{ $invitation->expires_at?->diffForHumans() }}
                                @else
                                    Waiting · expires {{ $invitation->expires_at?->diffForHumans() }}
                                @endif
                            </p>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500 dark:text-slate-400">No invitations for this email address.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-admin-layout>
