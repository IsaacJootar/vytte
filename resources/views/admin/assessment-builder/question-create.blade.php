<x-admin-layout title="New Question">
    <div class="mb-5">
        <a href="{{ route('admin.assessments.build', $assessment) }}" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Back to {{ $assessment->display_name }}</a>
        <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Write a new question</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Adding to <span class="font-semibold">{{ $section->section_name }}</span>.</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 max-w-2xl rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950">
            <p class="text-sm font-semibold text-red-800 dark:text-red-200">Please fix the following:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700 dark:text-red-300">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.assessments.questions.store', [$assessment, $section]) }}"
          x-data="{ format: '{{ old('format', 'yes_no') }}' }"
          class="max-w-2xl space-y-6 section-card p-6 dark:border-slate-700 dark:bg-slate-800">
        @csrf

        <div>
            <label for="question_text" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Question</label>
            <p class="text-xs text-slate-500 dark:text-slate-400">Write it exactly as the person answering will read it.</p>
            <textarea id="question_text" name="question_text" rows="3" required maxlength="5000"
                      placeholder="e.g. Is emergency oxygen available and working today?"
                      class="mt-1.5 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ old('question_text') }}</textarea>
        </div>

        <fieldset>
            <legend class="text-sm font-semibold text-slate-700 dark:text-slate-200">How should people answer?</legend>
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                @foreach ($formats as $format)
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-900/40"
                           :class="format === '{{ $format['key'] }}' ? 'border-vytte-500 bg-vytte-50 dark:bg-vytte-900/20' : ''">
                        <input type="radio" name="format" value="{{ $format['key'] }}" x-model="format" @checked(old('format', 'yes_no') === $format['key'])
                               class="mt-0.5 border-slate-300 text-vytte-600 dark:border-slate-600">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $format['label'] }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $format['description'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div x-show="format === 'multiple_choice'" x-cloak>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Answer choices</label>
            <p class="text-xs text-slate-500 dark:text-slate-400">Add at least two. Leave unused boxes empty.</p>
            <div class="mt-2 space-y-2">
                @for ($i = 0; $i < 6; $i++)
                    <input name="choices[]" value="{{ old('choices.'.$i) }}" maxlength="180" placeholder="Choice {{ $i + 1 }}"
                           class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                @endfor
            </div>
        </div>

        <div x-show="format === 'number'" x-cloak>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Number settings <span class="font-normal text-slate-400">(optional)</span></label>
            <p class="text-xs text-slate-500 dark:text-slate-400">Limits help people enter a sensible value.</p>
            <div class="mt-2 grid gap-3 sm:grid-cols-3">
                <input name="numeric_min" value="{{ old('numeric_min') }}" type="number" step="any" placeholder="Smallest allowed"
                       class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <input name="numeric_max" value="{{ old('numeric_max') }}" type="number" step="any" placeholder="Largest allowed"
                       class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <input name="numeric_unit" value="{{ old('numeric_unit') }}" maxlength="40" placeholder="Unit, e.g. beds"
                       class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            </div>
        </div>

        <p class="rounded-xl bg-slate-50 p-3 text-xs text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
            Scoring and evidence are set later, once the questions are in place.
        </p>

        <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5 dark:border-slate-700">
            <button type="submit" class="rounded-xl bg-vytte-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700">Add question</button>
            <a href="{{ route('admin.assessments.build', $assessment) }}" class="text-sm font-semibold text-slate-600 hover:underline dark:text-slate-300">Cancel</a>
        </div>
    </form>
</x-admin-layout>
