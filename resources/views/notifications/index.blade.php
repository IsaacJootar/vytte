<x-app-layout title="Notifications">

    <div class="mb-5 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Notifications</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                @if (auth()->user()->unreadNotifications()->count() > 0)
                    {{ auth()->user()->unreadNotifications()->count() }} unread
                @else
                    All caught up
                @endif
            </p>
        </div>
        @if (auth()->user()->unreadNotifications()->count() > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit"
                        class="text-xs font-semibold text-vytte-700 hover:text-vytte-900 underline underline-offset-2 transition-colors">
                    Mark all as read
                </button>
            </form>
        @endif
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        @if ($notifications->isEmpty())
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto w-8 h-8 text-slate-300 dark:text-slate-600 mb-3" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zm0 16a2 2 0 002-2H8a2 2 0 002 2z"/>
                </svg>
                <p class="text-sm font-semibold text-slate-400 dark:text-slate-500">No notifications yet</p>
                <p class="text-xs text-slate-300 dark:text-slate-600 mt-1">You'll be notified when assessments complete or team members join.</p>
            </div>
        @else
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach ($notifications as $notification)
                    @php
                        $data = $notification->data;
                        $isUnread = is_null($notification->read_at);
                        $typeIcon = match ($data['type'] ?? '') {
                            'assessment_complete' => '<svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>',
                            'member_joined' => '<svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/></svg>',
                            default => '<svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zm0 16a2 2 0 002-2H8a2 2 0 002 2z"/></svg>',
                        };
                        $iconBg = match ($data['type'] ?? '') {
                            'assessment_complete' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
                            'member_joined' => 'bg-vytte-100 text-vytte-700 dark:bg-vytte-900/40 dark:text-vytte-400',
                            default => 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400',
                        };
                    @endphp
                    <div class="flex items-start gap-3.5 px-5 py-4 {{ $isUnread ? 'bg-vytte-50/40 dark:bg-vytte-900/10' : '' }}">
                        {{-- Type icon --}}
                        <div class="flex-shrink-0 w-8 h-8 rounded-full {{ $iconBg }} flex items-center justify-center mt-0.5">
                            {!! $typeIcon !!}
                        </div>
                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-semibold {{ $isUnread ? 'text-slate-900 dark:text-white' : 'text-slate-600 dark:text-slate-300' }}">
                                    {{ $data['title'] ?? 'Notification' }}
                                    @if ($isUnread)
                                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-vytte-600 align-middle ml-1 mb-0.5"></span>
                                    @endif
                                </p>
                                <span class="flex-shrink-0 text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">
                                    {{ $notification->created_at->diffForHumans() }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $data['body'] ?? '' }}</p>
                            @if (! empty($data['url']))
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="mt-1.5 text-xs font-semibold text-vytte-700 hover:text-vytte-900 transition-colors">
                                        {{ $isUnread ? 'View →' : 'View' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($notifications->hasPages())
                <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-700">
                    {{ $notifications->links() }}
                </div>
            @endif
        @endif
    </div>

</x-app-layout>
