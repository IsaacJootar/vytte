{{--
    The "Tailored by your team" question editor. Shared by the standalone editor and by
    step 2 of the setup wizard. Callers pass:
      $assessment   — the assessment being tailored
      $section      — its LocalCustomSection (or null)
      $wizard       — true inside the setup wizard (changes the buttons)
      $redirectTo   — where custom.save should return ('setup' to continue the wizard)
--}}
@php($wizard = $wizard ?? false)
<form method="POST" action="{{ route('assessments.custom.save', $assessment) }}"
      x-data="customSection(@js($section?->questions ?? []))">
    @csrf
    <input type="hidden" name="redirect_to" value="{{ $redirectTo ?? '' }}">

    <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800 mb-4">
        <label class="block">
            <span class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Section name</span>
            <input type="text" name="section_title" value="{{ $section->section_title ?? 'Tailored by your team' }}" maxlength="180"
                   class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
        </label>
    </div>

    <div class="space-y-3">
        <template x-for="(q, i) in questions" :key="q.id">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                <div class="flex items-start justify-between gap-3">
                    <span class="text-xs font-bold text-slate-400 dark:text-slate-500 mt-2" x-text="'Q' + (i + 1)"></span>
                    <button type="button" @click="remove(i)" class="text-xs font-semibold text-slate-400 hover:text-red-600 dark:hover:text-red-400">Remove</button>
                </div>
                <input type="hidden" :name="'questions['+i+'][id]'" :value="q.id">
                <input type="text" :name="'questions['+i+'][text]'" x-model="q.text" required maxlength="500"
                       placeholder="e.g. Is there a working referral vehicle on site?"
                       class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">

                <div class="mt-3 flex flex-wrap items-center gap-4">
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                        Answer type
                        <select :name="'questions['+i+'][type]'" x-model="q.response_type"
                                class="ml-1 rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                            <option value="YES_NO">Yes / No</option>
                            <option value="SCALE_5">1–5 scale</option>
                        </select>
                    </label>

                    <template x-if="q.response_type === 'YES_NO'">
                        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                            Good answer
                            <select :name="'questions['+i+'][good]'" x-model="q.good_answer"
                                    class="ml-1 rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                                <option value="YES">Yes is good</option>
                                <option value="NO">No is good</option>
                            </select>
                        </label>
                    </template>

                    <template x-if="q.response_type === 'SCALE_5'">
                        <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300">
                            <input type="checkbox" :name="'questions['+i+'][reversed]'" value="1" x-model="q.reversed"
                                   class="rounded border-slate-300 text-vytte-600 focus:ring-vytte-500">
                            1 is best (reverse the scale)
                        </label>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <button type="button" @click="add()"
            class="mt-3 inline-flex items-center gap-1.5 rounded-xl border border-dashed border-vytte-300 px-4 py-2.5 text-sm font-semibold text-vytte-700 dark:border-vytte-800 dark:text-vytte-400 hover:bg-vytte-50 dark:hover:bg-vytte-900/20 transition-colors">
        <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
        Add a question
    </button>

    <div class="mt-6 flex items-center gap-3">
        <button type="submit" class="rounded-xl bg-vytte-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-800 transition-colors">
            {{ $wizard ? 'Save & continue →' : 'Save questions' }}
        </button>
        @if ($wizard)
            <a href="{{ route('assessments.setup', ['assessment' => $assessment, 'step' => 3]) }}" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">Skip — no extra questions</a>
        @else
            <a href="{{ route('assessments.setup', $assessment) }}" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">Cancel</a>
        @endif
    </div>
</form>

<script>
    function customSection(initial) {
        return {
            questions: (initial || []).map(q => ({
                id: q.id, text: q.text ?? '', response_type: q.response_type ?? 'YES_NO',
                good_answer: q.good_answer ?? 'YES', reversed: !!q.reversed,
            })),
            add() {
                this.questions.push({ id: crypto.randomUUID(), text: '', response_type: 'YES_NO', good_answer: 'YES', reversed: false });
            },
            remove(i) { this.questions.splice(i, 1); },
            init() { if (this.questions.length === 0) { this.add(); } },
        };
    }
</script>
