<x-admin-layout title="New Assessment">
    <div class="mb-5">
        <a href="{{ route('admin.assessments.index') }}" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Back to assessments</a>
        <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">New Assessment</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Start with the basics. You can save and come back at any time.</p>
    </div>

    <x-assessment-wizard-steps :steps="$steps" :current-step="$currentStep" />

    <form method="POST" action="{{ route('admin.assessments.store') }}" class="max-w-2xl space-y-5 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
        @csrf

        <div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Basic Information</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Only the name and department are required to save a draft.</p>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950">
                <p class="text-sm font-semibold text-red-800 dark:text-red-200">Please fix the following:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700 dark:text-red-300">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <label for="display_name" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Assessment name</label>
            <input id="display_name" name="display_name" value="{{ old('display_name') }}" required maxlength="180"
                   placeholder="e.g. Outpatient Readiness Assessment"
                   class="mt-1.5 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
        </div>

        <div>
            <label for="description" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Description</label>
            <p class="text-xs text-slate-500 dark:text-slate-400">A short summary of what this assessment covers.</p>
            <textarea id="description" name="description" rows="3" maxlength="2000"
                      class="mt-1.5 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ old('description') }}</textarea>
        </div>

        <div>
            <label for="module_id" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Department or category</label>
            <p class="text-xs text-slate-500 dark:text-slate-400">Where this assessment belongs in the official Vytte library.</p>
            <select id="module_id" name="module_id" required
                    class="mt-1.5 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <option value="">Choose a department</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->module_id }}" @selected((int) old('module_id') === (int) $department->module_id)>{{ $department->module_name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="purpose" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Intended use <span class="font-normal text-slate-400">(optional)</span></label>
            <p class="text-xs text-slate-500 dark:text-slate-400">Who this is for and when it should be used.</p>
            <textarea id="purpose" name="purpose" rows="2" maxlength="2000"
                      class="mt-1.5 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ old('purpose') }}</textarea>
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5 dark:border-slate-700">
            <button type="submit" class="rounded-xl bg-vytte-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700">Save and continue</button>
            <a href="{{ route('admin.assessments.index') }}" class="text-sm font-semibold text-slate-600 hover:underline dark:text-slate-300">Cancel</a>
        </div>
    </form>
</x-admin-layout>
