<x-app-layout title="Question contributions">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h1 class="text-xl font-bold text-slate-900 dark:text-white">Question contributions</h1><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Share expert questions for governed review without changing your assessments or the public library.</p></div>
        <a href="{{ route('contributions.create') }}" class="rounded-xl bg-vytte-600 px-4 py-2.5 text-sm font-semibold text-white">Suggest a question</a>
    </div>
    <div class="mt-5 space-y-3">
        @forelse ($contributions as $contribution)
            <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ $contribution->title }}</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $contribution->question_text }}</p><p class="mt-2 text-xs text-slate-400">{{ $contribution->module?->module_name ?: 'Department to be decided' }} · {{ str($contribution->response_format)->replace('_', ' ')->title() }}</p></div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ str($contribution->status)->replace('_', ' ')->title() }}</span>
                </div>
                @if ($contribution->review_notes)<p class="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-900 dark:text-slate-300"><span class="font-semibold">Reviewer note:</span> {{ $contribution->review_notes }}</p>@endif
            </div>
        @empty
            <x-empty-state title="No contributions yet" description="Your own assessment questions remain private unless you deliberately submit one here." />
        @endforelse
    </div>
    <div class="mt-4">{{ $contributions->links() }}</div>
</x-app-layout>
