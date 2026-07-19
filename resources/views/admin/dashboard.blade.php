<x-admin-layout title="Dashboard">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Vytte Platform Admin Control Center</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Build, review and publish the official Vytte assessment library.</p>
        </div>
        <a href="{{ route('admin.assessments.create') }}"
           class="inline-flex items-center gap-1.5 rounded-xl bg-vytte-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-vytte-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-500">
            + New Assessment
        </a>
    </div>

    {{-- ===== NEEDS YOUR ATTENTION ===== --}}
    @if (count($attention) > 0)
        <section class="mb-4" aria-labelledby="attention-heading">
            <h2 id="attention-heading" class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Needs your attention</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($attention as $item)
                    @php
                        $tone = match ($item['tone']) {
                            'warning' => 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950',
                            'success' => 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950',
                            default => 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800',
                        };
                    @endphp
                    <div class="flex flex-col rounded-2xl border p-5 {{ $tone }}">
                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $item['title'] }}</p>
                        <p class="mt-1 flex-1 text-sm text-slate-600 dark:text-slate-300">{{ $item['detail'] }}</p>
                        <a href="{{ $item['href'] }}" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-vytte-700 hover:underline dark:text-vytte-300">
                            {{ $item['action'] }} <span aria-hidden="true">→</span>
                        </a>
                    </div>
                @endforeach
            </div>
        </section>
    @else
        <div class="mb-4 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-900 dark:bg-emerald-950">
            <span class="text-lg" aria-hidden="true">✓</span>
            <div>
                <p class="text-sm font-bold text-emerald-900 dark:text-emerald-100">Nothing needs your attention</p>
                <p class="text-sm text-emerald-800 dark:text-emerald-200">No approvals pending, no drafts stalled, nothing waiting to publish.</p>
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- ===== YOUR WORK ===== --}}
        <section class="lg:col-span-2" aria-labelledby="work-heading">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-700">
                    <h2 id="work-heading" class="text-sm font-bold text-slate-900 dark:text-white">Your work</h2>
                    <a href="{{ route('admin.assessments.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-vytte-700 hover:underline dark:text-vytte-300">
                        All assessments <span aria-hidden="true">→</span>
                    </a>
                </div>

                <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse ($recentDrafts as $draft)
                        @php $questionCount = $draft->sections->sum(fn ($s) => $s->questionPlacements->count()); @endphp
                        <li>
                            <a href="{{ route('admin.assessments.build', $draft) }}"
                               class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:bg-slate-50 dark:hover:bg-slate-700/40 dark:focus-visible:bg-slate-700/40">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $draft->display_name }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                        {{ $draft->module?->module_name ?? 'No department' }} ·
                                        {{ $draft->sections->count() }} {{ Str::plural('section', $draft->sections->count()) }} ·
                                        {{ $questionCount }} {{ Str::plural('question', $questionCount) }} ·
                                        {{ $draft->updated_at?->diffForHumans() }}
                                    </p>
                                </div>
                                <span class="inline-flex items-center gap-1 text-sm font-semibold text-vytte-700 dark:text-vytte-300">
                                    Continue <span aria-hidden="true">→</span>
                                </span>
                            </a>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center">
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No assessments in progress</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Start one and it will appear here until you publish it.</p>
                            <a href="{{ route('admin.assessments.create') }}" class="mt-4 inline-block rounded-xl bg-vytte-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700">
                                + New Assessment
                            </a>
                        </li>
                    @endforelse
                </ul>
            </div>
        </section>

        <div class="space-y-4">
            {{-- ===== PUBLISHED CATALOGUE ===== --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800" aria-labelledby="catalogue-heading">
                <h2 id="catalogue-heading" class="text-sm font-bold text-slate-900 dark:text-white">Published catalogue</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    @foreach ([
                        'Live assessments' => $catalogue['live'],
                        'Replaced by newer versions' => $catalogue['superseded'],
                        'Questions in the library' => $catalogue['questions'],
                        'Departments covered' => $catalogue['departments'],
                    ] as $label => $value)
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                            <dd class="font-semibold text-slate-900 dark:text-white">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <a href="{{ route('admin.catalogue-releases.index') }}" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-vytte-700 hover:underline dark:text-vytte-300">
                    Publishing <span aria-hidden="true">→</span>
                </a>
            </section>

            {{-- ===== PLATFORM HEALTH ===== --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800" aria-labelledby="health-heading">
                <h2 id="health-heading" class="text-sm font-bold text-slate-900 dark:text-white">Platform health</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($health as $check)
                        <li class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                                <span class="{{ $check['ok'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}" aria-hidden="true">{{ $check['ok'] ? '✓' : '!' }}</span>
                                {{ $check['label'] }}
                            </span>
                            <span class="text-xs {{ $check['ok'] ? 'text-slate-500 dark:text-slate-400' : 'font-semibold text-amber-700 dark:text-amber-300' }}">{{ $check['detail'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- ===== RECENT ACTIVITY ===== --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800" aria-labelledby="activity-heading">
                <h2 id="activity-heading" class="text-sm font-bold text-slate-900 dark:text-white">Recent activity</h2>
                <ul class="mt-3 space-y-2.5">
                    @forelse ($recentActivity as $entry)
                        <li class="text-xs">
                            <p class="text-slate-700 dark:text-slate-200">{{ \App\Support\AuditEventLabel::for($entry->event) }}</p>
                            <p class="text-slate-400">{{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at?->diffForHumans() }}</p>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500 dark:text-slate-400">Nothing yet.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-admin-layout>
