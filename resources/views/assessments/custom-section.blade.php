<x-app-layout title="Local questions">
    <div class="max-w-3xl mx-auto">
        <a href="{{ route('assessments.setup', $assessment) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 mb-3">
            ← Back to setup
        </a>

        <div class="mb-5">
            <p class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 uppercase tracking-wide">Local to this assessment</p>
            <h1 class="mt-0.5 text-xl font-bold text-slate-900 dark:text-white">Add local questions</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-2xl">
                Add questions specific to this setting. Their answers appear in the same report, and eligible answer
                types can contribute to an optional local score.
            </p>
        </div>

        <x-help-callout id="custom-section" title="How it works">
            <ol class="text-xs text-slate-600 dark:text-slate-300 space-y-1 list-decimal list-inside">
                <li>Write a question and choose how it's answered — from the seven supported formats.</li>
                <li>For Yes/No, Yes/No/Not applicable, and 1–5 rating, you may include it in the optional local score.</li>
                <li>Save, then answer your questions — scored answers appear as their own local score in the report.</li>
            </ol>
        </x-help-callout>

        @include('assessments.partials.custom-questions-form', ['wizard' => false, 'redirectTo' => ''])
    </div>
</x-app-layout>
