<x-admin-layout title="New Assessment">
    <div class="mb-5">
        <a href="{{ route('admin.assessments.index') }}" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Back to assessments</a>
        <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">New Assessment</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Start with the basics. You can save and come back at any time.</p>
    </div>

    <x-assessment-wizard-steps :steps="$steps" :current-step="$currentStep" />

    <form method="POST" action="{{ route('admin.assessments.store') }}" class="section-card max-w-2xl space-y-6 p-6">
        @csrf

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

        <x-form-section title="Basic Information"
                        description="Only the name and department are required to save a draft.">
            <x-form-field label="Assessment name" for="display_name">
                <input id="display_name" name="display_name" value="{{ old('display_name') }}" required maxlength="180"
                       placeholder="e.g. Outpatient Readiness Assessment"
                       class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            </x-form-field>

            <x-form-field label="Description" for="description" hint="A short summary of what this assessment covers.">
                <textarea id="description" name="description" rows="3" maxlength="2000"
                          class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">{{ old('description') }}</textarea>
            </x-form-field>
        </x-form-section>

        <x-form-section title="Where it belongs"
                        description="Used to group the assessment in the official library and to offer the right scores."
                        :last="true">
            <x-form-field label="Department or category" for="module_id">
                <select id="module_id" name="module_id" required
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="">Choose a department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->module_id }}" @selected((int) old('module_id') === (int) $department->module_id)>{{ $department->module_name }}</option>
                    @endforeach
                </select>
            </x-form-field>

            <x-form-field label="Intended use" for="purpose" :optional="true" hint="Who this is for and when it should be used.">
                <textarea id="purpose" name="purpose" rows="2" maxlength="2000"
                          class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">{{ old('purpose') }}</textarea>
            </x-form-field>
        </x-form-section>

        <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5 dark:border-slate-700">
            <button type="submit" class="rounded-xl bg-vytte-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-vytte-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-500">
                Save and continue
            </button>
            <a href="{{ route('admin.assessments.index') }}" class="text-sm font-semibold text-slate-600 hover:underline dark:text-slate-300">Cancel</a>
        </div>
    </form>
</x-admin-layout>
