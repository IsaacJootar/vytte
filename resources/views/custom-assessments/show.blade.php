<x-app-layout title="Custom Assessment">
    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('custom-assessments.index') }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-300">← Custom assessments</a>
            <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $design->title }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $design->status }} · Workspace-only content</p>
        </div>
        <form method="POST" action="{{ route('custom-assessments.status', $design) }}" class="flex gap-2">
            @csrf
            @method('PATCH')
            <select name="status" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                @foreach (['DRAFT', 'ACTIVE', 'ARCHIVED'] as $status)
                    <option value="{{ $status }}" @selected($design->status === $status)>{{ $status }}</option>
                @endforeach
            </select>
            <button class="rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">Save</button>
        </form>
    </div>
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Purpose</h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $design->purpose }}</p>
            <h2 class="mt-5 text-sm font-bold text-slate-900 dark:text-white">Questions</h2>
            <ul class="mt-2 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                @forelse ($design->questions ?? [] as $question)
                    <li class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900">{{ $question }}</li>
                @empty
                    <li>No questions drafted yet.</li>
                @endforelse
            </ul>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Local scope</h2>
            <dl class="mt-3 space-y-2 text-xs text-slate-500">
                <div><dt class="font-bold text-slate-700 dark:text-slate-200">Scope</dt><dd>{{ $design->scope ?? '—' }}</dd></div>
                <div><dt class="font-bold text-slate-700 dark:text-slate-200">Setting</dt><dd>{{ $design->setting ?? '—' }}</dd></div>
                <div><dt class="font-bold text-slate-700 dark:text-slate-200">Target population</dt><dd>{{ $design->target_population ?? '—' }}</dd></div>
                <div><dt class="font-bold text-slate-700 dark:text-slate-200">Respondent</dt><dd>{{ $design->respondent_type ?? '—' }}</dd></div>
            </dl>
            <p class="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">Custom designs are descriptive workspace content and cannot claim an official Vytte score.</p>
        </div>
    </div>
</x-app-layout>
