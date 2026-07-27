<x-app-layout title="Tailored by your team">
    <div class="max-w-3xl mx-auto">
        <a href="{{ route('assessments.setup', $assessment) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 mb-3">
            ← Back to setup
        </a>

        <div class="mb-5">
            <p class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide">Tailored by your team</p>
            <h1 class="mt-0.5 text-xl font-bold text-slate-900 dark:text-white">Add your own questions</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-2xl">
                Add questions that matter to your context, alongside the Vytte template. They are
                <span class="font-semibold">scored on their own (0–100)</span> and shown in a separate
                <span class="font-semibold">“Tailored by your team”</span> section of the report — the official Vytte score is untouched.
            </p>
        </div>

        <x-help-callout id="custom-section" title="How it works">
            <ol class="text-xs text-slate-600 dark:text-slate-300 space-y-1 list-decimal list-inside">
                <li>Write a question and choose how it's answered — <span class="font-semibold">Yes / No</span> or a <span class="font-semibold">1–5 scale</span>.</li>
                <li>Mark which answer is the <span class="font-semibold">good</span> one, so it can be scored.</li>
                <li>Save, then answer your questions — the section gets its own 0–100 score in the report.</li>
            </ol>
        </x-help-callout>

        @include('assessments.partials.custom-questions-form', ['wizard' => false, 'redirectTo' => ''])
    </div>
</x-app-layout>
