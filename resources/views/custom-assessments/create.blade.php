<x-app-layout title="New Custom Assessment">
    <div class="mb-5">
        <a href="{{ route('custom-assessments.index') }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-300">← Custom assessments</a>
        <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">Create custom assessment</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Create a workspace-only draft for local context, surveys, or internal assessments.</p>
    </div>

    <x-plan-gate feature="workspace_custom_assessments">
        <form method="POST" action="{{ route('custom-assessments.store') }}" class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            @csrf
            <input name="title" placeholder="Assessment title" class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
            <textarea name="purpose" rows="3" placeholder="Purpose" class="mt-4 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required></textarea>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <input name="scope" placeholder="Scope" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <input name="setting" placeholder="Setting" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <input name="target_population" placeholder="Target population" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <input name="respondent_type" placeholder="Respondent type" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            </div>
            <textarea name="sections_text" rows="4" placeholder="Sections, one per line" class="mt-4 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
            <textarea name="questions_text" rows="6" placeholder="Questions, one per line" class="mt-4 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
            <textarea name="descriptive_outputs_text" rows="3" placeholder="Desired report outputs, one per line" class="mt-4 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
            <div class="mt-5 text-right"><button class="rounded-xl bg-vytte-700 px-5 py-2 text-sm font-bold text-white">Create draft</button></div>
        </form>
    </x-plan-gate>
</x-app-layout>
