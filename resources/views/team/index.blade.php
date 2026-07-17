<x-app-layout title="Team">

    @php $isOwner = $currentMember?->role === 'OWNER'; @endphp
    @php $isAdmin = in_array($currentMember?->role, ['OWNER', 'ADMIN']); @endphp
    @php $canInvite = $isAdmin && \App\Services\PlanService::workspaceCanAccess(app('current.workspace'), 'team_members'); @endphp

    {{-- Header --}}
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Team</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $workspace->name }} · {{ $members->count() }} {{ $members->count() === 1 ? 'member' : 'members' }}</p>
        </div>
        @if ($canInvite)
            <button x-data @click="$dispatch('open-invite')"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-vytte-700 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-vytte-800 transition-colors duration-150">
                <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/>
                </svg>
                Invite Member
            </button>
        @endif
    </div>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="mb-5 flex items-center gap-3 px-4 py-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-sm text-green-800 dark:text-green-300 font-medium">
            <svg class="w-4 h-4 text-green-600 dark:text-green-400 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-5 flex items-center gap-3 px-4 py-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-800 dark:text-red-300 font-medium">
            <svg class="w-4 h-4 text-red-500 dark:text-red-400 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
            </svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Invite link (shown after invite created) --}}
    @if (session('invite_link'))
        <div class="mb-5 p-4 bg-vytte-50 dark:bg-vytte-900/20 border border-vytte-200 dark:border-vytte-800 rounded-xl" x-data>
            <p class="text-sm font-semibold text-vytte-900 dark:text-vytte-300 mb-2">Share this invite link:</p>
            <div class="flex items-center gap-2">
                <input type="text" value="{{ session('invite_link') }}" readonly
                       class="flex-1 text-xs font-mono bg-white dark:bg-slate-800 border border-vytte-200 dark:border-vytte-700 rounded-lg px-3 py-2 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-vytte-500"
                       x-ref="inviteUrl"
                       @click="$refs.inviteUrl.select()">
                <button @click="navigator.clipboard.writeText($refs.inviteUrl.value); $el.textContent = 'Copied!'"
                        class="text-xs font-semibold px-3 py-2 bg-vytte-700 text-white rounded-lg hover:bg-vytte-800 transition-colors flex-shrink-0">
                    Copy
                </button>
            </div>
            <p class="text-xs text-vytte-700 dark:text-vytte-400 mt-1.5">Link expires in 7 days. Email delivery is currently disabled — share this link directly.</p>
        </div>
    @endif

    {{-- Invite form (toggled by button) — gated to team_members feature --}}
    @if ($isAdmin)
        <div class="mb-5">
        <x-plan-gate feature="team_members">
        <div x-data="{ open: false }"
             x-on:open-invite.window="open = true"
             class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden"
             x-show="open"
             x-transition
             style="display: none">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Invite a team member</h2>
                <button @click="open = false" class="text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 transition-colors">
                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                    </svg>
                </button>
            </div>
            <form method="POST" action="{{ route('team.invite') }}" class="px-5 py-4">
                @csrf
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1">
                        <label for="invite_email" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email address</label>
                        <input type="email" id="invite_email" name="email"
                               placeholder="colleague@example.com"
                               value="{{ old('email') }}"
                               class="w-full text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-vytte-500 focus:border-transparent"
                               required>
                        @error('email')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="sm:w-40">
                        <label for="invite_role" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Role</label>
                        <select id="invite_role" name="role"
                                class="w-full text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-vytte-500">
                            <option value="MEMBER" {{ old('role') !== 'ADMIN' ? 'selected' : '' }}>Member</option>
                            <option value="ADMIN" {{ old('role') === 'ADMIN' ? 'selected' : '' }}>Admin</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit"
                                class="w-full sm:w-auto px-5 py-2 bg-vytte-700 text-white text-sm font-semibold rounded-lg hover:bg-vytte-800 transition-colors">
                            Send Invite
                        </button>
                    </div>
                </div>
            </form>
        </div>
        </x-plan-gate>
        </div>
    @endif

    {{-- Members list --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden mb-5">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Members</h2>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-slate-700">
            @foreach ($members as $member)
                @php
                    $isSelf    = $member->user_id === auth()->id();
                    $isTargetOwner = $member->role === 'OWNER';
                    $canChangeRole = $isOwner && ! $isSelf && ! $isTargetOwner;
                    $canRemove    = ! $isSelf && $isAdmin && ($isOwner || ! in_array($member->role, ['OWNER', 'ADMIN']));
                    $roleBadge = match ($member->role) {
                        'OWNER' => 'bg-vytte-100 text-vytte-800 dark:bg-vytte-900/40 dark:text-vytte-300',
                        'ADMIN' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                        default => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                    };
                @endphp
                <div class="flex items-center gap-3 px-5 py-3.5">
                    {{-- Avatar --}}
                    <div class="w-8 h-8 rounded-full bg-vytte-700 flex items-center justify-center text-xs font-bold text-white flex-shrink-0 uppercase">
                        {{ substr($member->user?->name ?? '?', 0, 1) }}
                    </div>
                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $member->user?->name ?? '—' }}</span>
                            @if ($isSelf)
                                <span class="text-[10px] text-slate-400 dark:text-slate-500 font-medium">(you)</span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-400 dark:text-slate-500 truncate">{{ $member->user?->email ?? '—' }}</p>
                    </div>
                    {{-- Role badge --}}
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $roleBadge }} flex-shrink-0">
                        {{ ucfirst(strtolower($member->role)) }}
                    </span>
                    {{-- Actions --}}
                    <div class="flex items-center gap-2 flex-shrink-0 ml-1" x-data>
                        @if ($canChangeRole)
                            <form method="POST" action="{{ route('team.role', $member->user_id) }}" class="flex items-center gap-1">
                                @csrf
                                @method('PATCH')
                                <select name="role" onchange="this.form.submit()"
                                        class="text-xs border border-slate-200 dark:border-slate-600 rounded-lg px-2 py-1 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-vytte-500">
                                    <option value="ADMIN" {{ $member->role === 'ADMIN' ? 'selected' : '' }}>Admin</option>
                                    <option value="MEMBER" {{ $member->role === 'MEMBER' ? 'selected' : '' }}>Member</option>
                                </select>
                            </form>
                        @endif
                        @if ($canRemove)
                            <form method="POST" action="{{ route('team.destroy', $member->user_id) }}"
                                  @submit.prevent="if (confirm('Remove {{ addslashes($member->user?->name ?? 'this member') }} from the workspace?')) $el.submit()">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-500 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 font-medium transition-colors">
                                    Remove
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Pending invites --}}
    @if ($pendingInvites->isNotEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Pending Invites</h2>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach ($pendingInvites as $invite)
                    <div class="flex items-center gap-3 px-5 py-3.5">
                        <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-400 dark:text-slate-500 flex-shrink-0 uppercase">
                            {{ substr($invite->email, 0, 1) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 truncate">{{ $invite->email }}</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">
                                Invited by {{ $invite->invitedBy?->name ?? 'someone' }}
                                · Expires {{ $invite->expires_at?->diffForHumans() ?? 'never' }}
                            </p>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex-shrink-0">
                            {{ ucfirst(strtolower($invite->role)) }}
                        </span>
                        @if ($isAdmin)
                            <form method="POST" action="{{ route('team.invite.cancel', $invite->id) }}"
                                  @submit.prevent="if (confirm('Cancel this invite?')) $el.submit()" x-data>
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-slate-400 dark:text-slate-500 hover:text-red-600 dark:hover:text-red-400 font-medium transition-colors flex-shrink-0">
                                    Cancel
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</x-app-layout>
