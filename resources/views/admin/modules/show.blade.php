<x-admin-layout :title="$module->module_name">

    {{-- Header --}}
    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.modules.index') }}"
               class="inline-flex items-center gap-1 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 mb-2 transition-colors">
                ← Modules
            </a>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ $module->module_name }}</h1>
            <p class="text-xs text-slate-400 dark:text-slate-500 font-mono mt-0.5">{{ $module->module_code }} · {{ $module->target_type_code }}</p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            {{-- Toggle active --}}
            <form method="POST" action="{{ route('admin.modules.toggle', $module) }}">
                @csrf
                @method('PATCH')
                <button type="submit"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors
                            {{ $module->is_active
                                ? 'border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-700 hover:bg-slate-50 dark:hover:bg-slate-600'
                                : 'border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/20 hover:bg-green-100 dark:hover:bg-green-900/40' }}">
                    {{ $module->is_active ? 'Deactivate' : 'Reactivate' }}
                </button>
            </form>
            <a href="{{ route('admin.modules.translations.edit', [$module, 'fr']) }}"
               class="px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                Translations
            </a>
            <a href="{{ route('admin.modules.edit', $module) }}"
               class="px-3 py-1.5 text-xs font-semibold bg-vytte-700 text-white rounded-lg hover:bg-vytte-800 transition-colors">
                Edit
            </a>
        </div>
    </div>

    {{-- Module meta --}}
    <div class="section-card p-5 mb-5 grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div>
            <p class="text-xs text-slate-400 dark:text-slate-500 font-semibold mb-0.5">Status</p>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                {{ $module->is_active ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-500' }}">
                {{ $module->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>
        <div>
            <p class="text-xs text-slate-400 dark:text-slate-500 font-semibold mb-0.5">Primary Respondent</p>
            <p class="text-sm text-slate-700 dark:text-slate-200">{{ $module->primary_respondent ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 dark:text-slate-500 font-semibold mb-0.5">Est. Duration</p>
            <p class="text-sm text-slate-700 dark:text-slate-200">{{ $module->estimated_duration_minutes ? $module->estimated_duration_minutes . ' min' : '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 dark:text-slate-500 font-semibold mb-0.5">Collection Methods</p>
            <p class="text-sm text-slate-700 dark:text-slate-200">{{ $module->data_collection_methods ?? '—' }}</p>
        </div>
    </div>

    {{-- Question groups & questions --}}
    @forelse ($module->questionGroups as $group)
        <div class="section-card mb-4">

            {{-- Question group header --}}
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs text-slate-400 dark:text-slate-500 font-semibold">Question group {{ $group->group_number }}</p>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ $group->group_label }}</h2>
                </div>
                {{-- Inline edit question group label --}}
                <form method="POST" action="{{ route('admin.question-groups.update', $group) }}" class="flex items-center gap-2" x-data="{ editing: false }">
                    @csrf
                    @method('PUT')
                    <input type="text" name="group_label" value="{{ $group->group_label }}"
                           x-show="editing"
                           class="text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-vytte-500 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100"
                           style="display:none">
                    <button type="button" x-show="!editing" @click="editing = true"
                            class="text-xs text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 transition-colors">
                        Edit label
                    </button>
                    <button type="submit" x-show="editing"
                            class="text-xs font-semibold text-vytte-700 dark:text-vytte-400 hover:text-vytte-900 transition-colors"
                            style="display:none">
                        Save
                    </button>
                    <button type="button" x-show="editing" @click="editing = false"
                            class="text-xs text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                            style="display:none">
                        Cancel
                    </button>
                </form>
            </div>

            {{-- Questions --}}
            @if ($group->questions->isEmpty())
                <p class="px-5 py-4 text-sm text-slate-400 dark:text-slate-500 italic">No questions in this group.</p>
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($group->questions as $question)
                        <div class="px-5 py-3 flex items-start gap-3" x-data="{ editing: false }">
                            <div class="flex-shrink-0 w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center mt-0.5">
                                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400">{{ $question->question_number }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p x-show="!editing" class="text-sm text-slate-800 dark:text-slate-200 leading-snug">{{ $question->question_text }}</p>
                                <form x-show="editing" method="POST" action="{{ route('admin.questions.update', $question) }}" style="display:none">
                                    @csrf
                                    @method('PUT')
                                    <textarea name="question_text" rows="2"
                                              class="w-full text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-vytte-500 resize-none bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100">{{ $question->question_text }}</textarea>
                                    <div class="mt-1.5 flex items-center gap-2">
                                        <button type="submit" class="text-xs font-semibold text-vytte-700 dark:text-vytte-400">Save</button>
                                        <button type="button" @click="editing = false" class="text-xs text-slate-400 dark:text-slate-500">Cancel</button>
                                    </div>
                                </form>
                                <div class="mt-1 flex items-center gap-3 flex-wrap">
                                    <span class="text-[10px] font-mono text-slate-400 dark:text-slate-500">{{ $question->question_code }}</span>
                                    @if ($question->options->isNotEmpty())
                                        <span class="text-[10px] text-slate-300 dark:text-slate-600">{{ $question->options->count() }} options</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0 mt-0.5">
                                <span class="{{ $question->is_active ? 'text-green-600 dark:text-green-400' : 'text-slate-300 dark:text-slate-600' }} text-[10px] font-bold uppercase">
                                    {{ $question->is_active ? 'on' : 'off' }}
                                </span>
                                {{-- Toggle active --}}
                                <form method="POST" action="{{ route('admin.questions.toggle', $question) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="text-[10px] text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 underline underline-offset-2 transition-colors">
                                        {{ $question->is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                                <button type="button" x-show="!editing" @click="editing = true"
                                        class="text-[10px] text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 underline underline-offset-2 transition-colors">
                                    Edit
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <div class="section-card px-5 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
            No question groups configured for this module.
        </div>
    @endforelse

</x-admin-layout>
