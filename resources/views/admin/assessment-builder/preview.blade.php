<x-admin-layout title="Respondent preview">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.assessments.logic', $assessment) }}" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Back to logic</a>
            <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Test the respondent experience</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $assessment->display_name }}</p>
        </div>
        <x-assessment-status-badge :status="$assessment->status" />
    </div>

    <x-assessment-wizard-steps :steps="$steps" :current-step="$currentStep" />

    <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-900 dark:bg-sky-950">
        <p class="text-sm font-semibold text-sky-900 dark:text-sky-100">Try every important answer path before publishing</p>
        <p class="mt-1 text-sm text-sky-800 dark:text-sky-200">
            Answers entered here are a temporary simulation and are never saved.
            {{ $isFrozen
                ? 'This uses the exact frozen content that was published.'
                : 'This uses the current draft and the same branching engine as the live assessment.' }}
        </p>
    </div>

    <livewire:assessment-preview-simulator :assessment="$assessment" />

    <div class="mt-5 flex flex-wrap justify-between gap-3">
        <a href="{{ route('admin.assessments.logic', $assessment) }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">← Logic</a>
        <a href="{{ route('admin.assessments.review', $assessment) }}" class="rounded-xl bg-vytte-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700">Continue to review →</a>
    </div>
</x-admin-layout>
