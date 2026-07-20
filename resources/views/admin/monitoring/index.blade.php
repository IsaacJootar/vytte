@php
    $toneFor = fn (string $status) => match ($status) {
        'ok' => 'strong',
        'warn' => 'moderate',
        'down' => 'weak',
        default => 'slate',
    };
    $wordFor = fn (string $status) => match ($status) {
        'ok' => 'Fine',
        'warn' => 'Needs a look',
        'down' => 'Broken',
        default => 'Unknown',
    };
@endphp

<x-admin-layout title="Platform Health">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Platform Health</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Whether Vytte itself is working properly. Checked fresh each time you open this page.
        </p>
    </div>

    @if (count($needsAttention) > 0)
        <section class="mb-4" aria-labelledby="attention-heading">
            <h2 id="attention-heading" class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Needs your attention
            </h2>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($needsAttention as $check)
                    <div class="rounded-2xl border p-5
                        {{ $check['status'] === 'down'
                            ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950'
                            : 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950' }}">
                        <p class="text-sm font-bold {{ $check['status'] === 'down' ? 'text-red-900 dark:text-red-100' : 'text-amber-900 dark:text-amber-100' }}">
                            {{ $check['label'] }} — {{ $check['headline'] }}
                        </p>
                        <p class="mt-1 text-sm {{ $check['status'] === 'down' ? 'text-red-800 dark:text-red-200' : 'text-amber-800 dark:text-amber-200' }}">
                            {{ $check['detail'] }}
                        </p>
                        @if ($check['action'])
                            <p class="mt-2 text-sm font-semibold {{ $check['status'] === 'down' ? 'text-red-900 dark:text-red-100' : 'text-amber-900 dark:text-amber-100' }}">
                                {{ $check['action'] }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @else
        <div class="mb-4 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-900 dark:bg-emerald-950">
            <span class="text-lg" aria-hidden="true">✓</span>
            <div>
                <p class="text-sm font-bold text-emerald-900 dark:text-emerald-100">Everything is working</p>
                <p class="text-sm text-emerald-800 dark:text-emerald-200">Every check below came back fine.</p>
            </div>
        </div>
    @endif

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($checks as $check)
            <x-stat-card :tone="$toneFor($check['status'])"
                         :label="$check['label']"
                         :value="$wordFor($check['status'])"
                         :sub="$check['headline']" />
        @endforeach
    </div>

    <section class="section-card" aria-labelledby="detail-heading">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-700">
            <h2 id="detail-heading" class="text-sm font-bold text-slate-900 dark:text-white">What each check means</h2>
        </div>
        <ul class="divide-y divide-slate-100 dark:divide-slate-700">
            @foreach ($checks as $check)
                <li class="flex flex-wrap items-start justify-between gap-3 px-5 py-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $check['label'] }}</p>
                        <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">{{ $check['detail'] }}</p>
                        @if ($check['action'])
                            <p class="mt-1 text-sm font-medium text-amber-700 dark:text-amber-300">{{ $check['action'] }}</p>
                        @endif
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold
                        {{ match ($check['status']) {
                            'ok' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
                            'warn' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
                            'down' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
                            default => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                        } }}">
                        {{ $check['headline'] }}
                    </span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="mt-4 section-card" aria-labelledby="failures-heading">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-700">
            <h2 id="failures-heading" class="text-sm font-bold text-slate-900 dark:text-white">Recent background work that failed</h2>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                Work Vytte tried to do on its own and could not finish — sending an email, building a report.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">When</th>
                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">Queue</th>
                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">What went wrong</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse ($recentFailures as $failure)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-500 dark:text-slate-400">
                                {{ \Illuminate\Support\Carbon::parse($failure->failed_at)->diffForHumans() }}
                            </td>
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $failure->queue }}</td>
                            <td class="px-5 py-3">
                                <p class="max-w-xl truncate text-xs text-slate-600 dark:text-slate-300">
                                    {{ str($failure->exception)->before("\n") }}
                                </p>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-10 text-center">
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Nothing has failed</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Vytte has completed all its background work successfully.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-admin-layout>
