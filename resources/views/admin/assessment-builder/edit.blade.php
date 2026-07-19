<x-admin-layout :title="$assessment->display_name">
    <div class="mb-5">
        <a href="{{ route('admin.assessments.show', $assessment) }}" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Back to assessment</a>
        <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Basic Information</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $assessment->display_name }}</p>
    </div>

    <x-assessment-wizard-steps :steps="$steps" :current-step="$currentStep" />

    @unless ($isEditable)
        <div class="mb-4 max-w-2xl rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">This assessment is published and cannot be edited</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                Published assessments are locked so that completed reports stay reproducible. To make changes, create a new version.
            </p>
        </div>
    @endunless

    <form method="POST" action="{{ route('admin.assessments.update', $assessment) }}" class="max-w-2xl space-y-5 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
        @csrf
        @method('PUT')

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
            <input id="display_name" name="display_name" value="{{ old('display_name', $assessment->display_name) }}" required maxlength="180" @disabled(! $isEditable)
                   class="mt-1.5 w-full rounded-xl border-slate-300 text-sm disabled:bg-slate-100 disabled:text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:disabled:bg-slate-800">
        </div>

        <div>
            <label for="description" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Description</label>
            <textarea id="description" name="description" rows="3" maxlength="2000" @disabled(! $isEditable)
                      class="mt-1.5 w-full rounded-xl border-slate-300 text-sm disabled:bg-slate-100 disabled:text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:disabled:bg-slate-800">{{ old('description', $assessment->description) }}</textarea>
        </div>

        <div>
            <label for="module_id" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Department or category</label>
            <select id="module_id" name="module_id" required @disabled(! $isEditable)
                    class="mt-1.5 w-full rounded-xl border-slate-300 text-sm disabled:bg-slate-100 disabled:text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:disabled:bg-slate-800">
                @foreach ($departments as $department)
                    <option value="{{ $department->module_id }}" @selected((int) old('module_id', $assessment->module_id) === (int) $department->module_id)>{{ $department->module_name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="purpose" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Intended use <span class="font-normal text-slate-400">(optional)</span></label>
            <textarea id="purpose" name="purpose" rows="2" maxlength="2000" @disabled(! $isEditable)
                      class="mt-1.5 w-full rounded-xl border-slate-300 text-sm disabled:bg-slate-100 disabled:text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:disabled:bg-slate-800">{{ old('purpose', $assessment->purpose) }}</textarea>
        </div>

        @if ($isEditable)
            <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5 dark:border-slate-700">
                <button type="submit" class="rounded-xl bg-vytte-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700">Save changes</button>
                <a href="{{ route('admin.assessments.show', $assessment) }}" class="text-sm font-semibold text-slate-600 hover:underline dark:text-slate-300">Cancel</a>
            </div>
        @endif
    </form>
</x-admin-layout>
