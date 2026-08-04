<x-app-layout title="How scoring works">

    <div class="max-w-3xl">

        <div class="mb-6">
            <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">How scoring works</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">A plain-language look at where Vytte's numbers come from — the same rule that runs in the product, not a separate description of it.</p>
        </div>

        {{-- ===== Two kinds of score ===== --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800 mb-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Two kinds of score, never mixed</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-vytte-100 bg-vytte-50/60 p-4 dark:border-vytte-900 dark:bg-vytte-950/30">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Vytte's official score</p>
                    <ul class="mt-2 space-y-1.5 text-xs leading-5 text-slate-600 dark:text-slate-300 list-disc list-inside">
                        <li>Built only from the published assessment's standard questions — written, reviewed, and calibrated by Vytte.</li>
                        <li>Every question already has a tested scoring rule: which answer earns how many points.</li>
                        <li>Identical for every workspace using that template, which is what makes it comparable across facilities and over time.</li>
                        <li>Local questions can never change this number.</li>
                    </ul>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/40">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Your optional local score</p>
                    <ul class="mt-2 space-y-1.5 text-xs leading-5 text-slate-600 dark:text-slate-300 list-disc list-inside">
                        <li>Built only from questions your workspace wrote yourself, for your own context.</li>
                        <li>Appears as its own separate number in the report — never combined with the official score.</li>
                        <li>Different for every workspace, because the questions themselves are different for every workspace.</li>
                        <li>Only exists when you explicitly mark a question as scored.</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- ===== The seven local answer formats ===== --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800 mb-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">The seven ways a local question can be answered</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Only three can ever be scored — not because they're more important, but because they're the only ones with a rule simple and safe enough to apply automatically.</p>

            <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-700">
                @foreach ($localFormats as $format)
                    <div class="flex items-start justify-between gap-4 py-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $format['label'] }}</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $format['description'] }}</p>
                        </div>
                        @if ($format['can_score'])
                            <span class="flex-shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-800">Can be scored</span>
                        @else
                            <span class="flex-shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-500 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700">Context only</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ===== How the optional local score is calculated ===== --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800 mb-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">How the optional local score is calculated</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">These are the exact rules the app runs — not a summary of them.</p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">Yes / No, and Yes / No / Not applicable</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">You choose which answer is the better one. That answer scores 100; the other scores 0. "Not applicable" is left out of the score entirely — it is never counted as a failure.</p>
                    <div class="mt-2 flex gap-2">
                        @foreach ($yesNoExamples as $example)
                            <span class="rounded-lg bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600 dark:bg-slate-900 dark:text-slate-300">{{ $example['label'] }} → {{ number_format($example['score'], 0) }}</span>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">1–5 rating</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">You choose which end of the scale is better. The five points spread evenly across 0–100. Example below assumes 5 is the better end.</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($scaleExamples as $example)
                            <span class="rounded-lg bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600 dark:bg-slate-900 dark:text-slate-300">{{ $example['label'] }} → {{ number_format($example['score'], 0) }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

            <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">A question's own local score is only averaged with other <span class="font-semibold">scored</span> questions in that section. Unscored questions still show their answer in the report — they just never enter this average.</p>
        </div>

        {{-- ===== Governed contribution path ===== --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Want a question scored for everyone, not just your workspace?</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">That's what <a href="{{ route('contributions.create') }}" class="font-semibold text-vytte-700 hover:underline dark:text-vytte-300">Contribute Questions</a> is for. A publisher reviews the wording and evidence, defines a real calibrated scoring rule, and adds it to a future published template version — at which point it stops being local and becomes part of the standard, comparable set.</p>

            <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-700">
                @foreach ($contributionFormats as $format)
                    <div class="flex items-start justify-between gap-4 py-2.5">
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $format['label'] }}</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $format['description'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">A contribution never changes any existing published template, score, or benchmark by itself — only a publisher's deliberate decision to include it in a future version does that.</p>
        </div>

    </div>

</x-app-layout>
